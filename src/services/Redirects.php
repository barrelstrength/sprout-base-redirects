<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\services;

use barrelstrength\sproutbaseredirects\elements\Redirect;
use barrelstrength\sproutbaseredirects\enums\RedirectMethods;
use barrelstrength\sproutbaseredirects\SproutBaseRedirects;
use barrelstrength\sproutbaseredirects\jobs\Delete404;
use Craft;
use craft\models\Site;
use yii\base\Component;
use craft\helpers\UrlHelper;
use yii\web\HttpException;
use yii\base\Exception;


/**
 *
 * @property array $methods
 * @property int   $structureId
 */
class Redirects extends Component
{
    /**
     * @param $event
     * @param $pluginHandle
     * @throws Exception
     * @throws \Throwable
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\ExitException
     * @throws \yii\base\InvalidConfigException
     */
    public function handleRedirectsOnException($event, $pluginHandle)
    {
        $request = Craft::$app->getRequest();

        // Only handle front-end site requests that are not live preview
        if (!$request->getIsSiteRequest() OR $request->getIsLivePreview()) {
            return;
        }

        $exception = $event->exception;

        // Rendering Twig can generate a 404 also: i.e. {% exit 404 %}
        if ($event->exception instanceof \Twig_Error_Runtime) {
            // If this is a Twig Runtime error, use the previous exception
            $exception = $exception->getPrevious();
        }

        /**
         * @var HttpException $exception
         */
        if ($exception instanceof HttpException && $exception->statusCode === 404) {

            $currentSite = Craft::$app->getSites()->getCurrentSite();
            $path = $request->getPathInfo();
            $absoluteUrl = UrlHelper::url($path);

            // Check if the requested URL needs to be redirected
            $redirect = SproutBaseRedirects::$app->redirects->findUrl($absoluteUrl, $currentSite);

            $plugin = Craft::$app->plugins->getPlugin($pluginHandle);
            $settings = $plugin->getSettings();

            if (!$redirect && isset($settings->enable404RedirectLog) && $settings->enable404RedirectLog) {
                // Save new 404 Redirect
                $redirect = SproutBaseRedirects::$app->redirects->save404Redirect($absoluteUrl, $currentSite);
            }

            if ($redirect) {
                SproutBaseRedirects::$app->redirects->logRedirect($redirect->id, $currentSite);

                if ($redirect->enabled && (int)$redirect->method !== 404) {
                    if (UrlHelper::isAbsoluteUrl($redirect->newUrl)){
                        Craft::$app->getResponse()->redirect($redirect->newUrl, $redirect->method);
                    }else{
                        Craft::$app->getResponse()->redirect($redirect->getAbsoluteNewUrl(), $redirect->method);
                    }
                    Craft::$app->end();
                }
            }
        }
    }

    /**
     * Find a regex url using the preg_match php function and replace
     * capture groups if any using the preg_replace php function also check normal urls
     *
     * Example: $absoluteUrl
     *   https://website.com
     *   https://website.com/es
     *   https://es.website.com
     *
     * @param      $absoluteUrl
     * @param Site $site
     *
     * @return Redirect|null
     */
    public function findUrl($absoluteUrl, $site)
    {
        $absoluteUrl = urldecode($absoluteUrl);
        $baseSiteUrl = Craft::getAlias($site->baseUrl);

        $redirects = Redirect::find()
            ->siteId($site->id)
            ->all();

        if (!$redirects) {
            return null;
        }

        /**
         * @var Redirect $redirect
         */
        foreach ($redirects as $redirect) {
            if ($redirect->regex) {
                // Use backticks as delimiters as they are invalid characters for URLs
                $oldUrlPattern = '`'.$redirect->oldUrl.'`';

                $currentPath = preg_replace('`^'.$baseSiteUrl.'`', '', $absoluteUrl);

                if (preg_match($oldUrlPattern, $currentPath)) {
                    // Replace capture groups if any
                    $redirect->newUrl = preg_replace($oldUrlPattern, $redirect->newUrl, $currentPath);
                    return $redirect;
                }
            } else {
                if ($baseSiteUrl.$redirect->oldUrl === $absoluteUrl) {
                    return $redirect;
                }
            }
        }

        return null;
    }

    /**
     * Get Redirect methods
     *
     * @return array
     */
    public function getMethods()
    {
        $methods = [
            Craft::t('sprout-base-redirects', RedirectMethods::Permanent) => 'Permanent',
            Craft::t('sprout-base-redirects', RedirectMethods::Temporary) => 'Temporary',
            Craft::t('sprout-base-redirects', RedirectMethods::PageNotFound) => 'Page Not Found'
        ];
        $newMethods = [];

        foreach ($methods as $key => $value) {
            $value = preg_replace('/([a-z])([A-Z])/', '$1 $2', $value);
            $newMethods[$key] = $key.' - '.$value;
        }

        return $newMethods;
    }

    /**
     * Update the current method in the record
     *
     * @param $ids
     * @param $newMethod
     *
     * @return int
     * @throws \yii\db\Exception
     */
    public function updateRedirectMethod($ids, $newMethod)
    {
        $response = Craft::$app->db->createCommand()->update(
            '{{%sproutseo_redirects}}',
            ['method' => $newMethod],
            ['in', 'id', $ids]
        )->execute();

        return $response;
    }

    /**
     * Get Method Update Response from elementaction
     *
     * @param bool
     *
     * @return string
     */
    public function getMethodUpdateResponse($status)
    {
        $response = null;
        if ($status) {
            $response = Craft::t('sprout-base-redirects', 'Redirect method updated.');
        } else {
            $response = Craft::t('sprout-base-redirects', 'Unable to update Redirect method.');
        }

        return $response;
    }

    /**
     * Remove Slash from URI
     *
     * @param string $uri
     *
     * @return array
     */
    public function removeSlash($uri)
    {
        $slash = '/';

        if (isset($uri[0]) && $uri[0] == $slash) {
            $uri = ltrim($uri, $slash);
        }

        return $uri;
    }

    /**
     * This service allows find the structure id from the sprout seo settings
     *
     * @return int
     */
    public function getStructureId()
    {
        /**
         * @var Settings $pluginSettings
         */
        $pluginSettings = null;
        /** @noinspection OneTimeUseVariablesInspection */
        $sproutSeo = Craft::$app->plugins->getPlugin('sprout-seo');

        if ($sproutSeo){
            $pluginSettings = $sproutSeo->getSettings();
        }else{
            $sproutRedirects = Craft::$app->plugins->getPlugin('sprout-redirects');
            $pluginSettings = $sproutRedirects->getSettings();
        }

        return $pluginSettings->structureId ?? null;
    }

    /**
     * Logs a redirect when a match is found
     *
     * @todo - escape this log data when we output it
     *         https://stackoverflow.com/questions/13199095/escaping-variables
     *
     * @param      $redirectId
     * @param Site $currentSite
     *
     * @return bool
     * @throws \Throwable
     */
    public function logRedirect($redirectId, Site $currentSite)
    {
        $log = [];

        try {
            $log['redirectId'] = $redirectId;
            $log['referralURL'] = Craft::$app->request->getReferrer();
            $log['ipAddress'] = $_SERVER['REMOTE_ADDR'];
            $log['dateCreated'] = date('Y-m-d h:m:s');

            SproutBaseRedirects::warning('404 - Page Not Found: '.json_encode($log));

            /**
             * @var Redirect $redirect
             */
            $redirect = Craft::$app->getElements()->getElementById($redirectId, Redirect::class, $currentSite->id);
            ++$redirect->count;

            Craft::$app->elements->saveElement($redirect, true);

        } catch (\Exception $e) {
            SproutBaseRedirects::error('Unable to log redirect: '.$e->getMessage());
        }

        return true;
    }

    /**
     * Save a 404 redirect and check total404Redirects setting
     *
     * @param      $absoluteUrl
     * @param Site $site
     *
     * @return Redirect|null
     * @throws Exception
     * @throws \Throwable
     */
    public function save404Redirect($absoluteUrl, $site)
    {
        $redirect = new Redirect();
        $seoSettings = SproutBaseRedirects::$app->settings->getPluginSettings();

        $baseUrl = Craft::getAlias($site->baseUrl);

        $baseUrlMatch = mb_strpos($absoluteUrl, $baseUrl) === 0;

        if (!$baseUrlMatch) {
            return null;
        }

        // Strip the base URL from our Absolute URL
        // We need to do this because the Base URL can contain
        // subfolders that are included in the path and we only
        // want to store the path value that doesn't include
        // the Base URL
        $uri = substr($absoluteUrl, strlen($baseUrl));

        $redirect->oldUrl = $uri;
        $redirect->newUrl = '/';
        $redirect->method = RedirectMethods::PageNotFound;
        $redirect->regex = 0;
        $redirect->enabled = 0;
        $redirect->count = 0;
        $redirect->siteId = $site->id;

        if (!Craft::$app->elements->saveElement($redirect, true)) {
            return null;
        }

        // delete new one
        if (isset($seoSettings->total404Redirects) && $seoSettings->total404Redirects && $redirect) {

            $count = Redirect::find()
                ->where('method=:method and sproutseo_redirects.id != :redirectId', [
                    ':method' => RedirectMethods::PageNotFound,
                    ':redirectId' => $redirect->id
                ])
                ->anyStatus()
                ->count();

            if ($count >= $seoSettings->total404Redirects) {
                $totalToDelete = $count - $seoSettings->total404Redirects;

                $delete404 = new Delete404();
                $delete404->totalToDelete = $totalToDelete <= 0 ? 1 : $totalToDelete + 1;
                $delete404->redirectIdToExclude = $redirect->id ?? null;
                $delete404->siteId = $site->id;

                // Call the delete redirects job
                Craft::$app->queue->push($delete404);
            }
        }

        return $redirect;
    }
}
