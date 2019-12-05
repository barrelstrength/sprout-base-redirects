<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\services;

use barrelstrength\sproutbase\jobs\PurgeElements;
use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbaseredirects\models\Settings;
use barrelstrength\sproutbaseredirects\elements\Redirect;
use barrelstrength\sproutbaseredirects\enums\RedirectMethods;
use barrelstrength\sproutbaseredirects\models\Settings as RedirectsSettingsModel;
use barrelstrength\sproutbaseredirects\SproutBaseRedirects;
use Craft;
use craft\db\Query;
use craft\errors\DeprecationException;
use craft\errors\ElementNotFoundException;
use craft\errors\SiteNotFoundException;
use craft\events\ExceptionEvent;
use craft\helpers\Db;
use craft\models\Site;
use DateTime;
use Throwable;
use Twig\Error\RuntimeError as TwigRuntimeError;
use yii\base\Component;
use craft\helpers\UrlHelper;
use yii\base\ExitException;
use yii\base\InvalidConfigException;
use yii\web\HttpException;
use yii\base\Exception;


/**
 *
 * @property array      $methods
 * @property Settings   $redirectsSettings
 * @property string|int $totalNon404Redirects
 * @property int        $structureId
 */
class Redirects extends Component
{
    /**
     * Set to false to stop additional processing of redirects during this request
     *
     * @var bool
     */
    protected $processRedirect = true;

    /**
     * @param $event
     *
     * @throws Exception
     * @throws Throwable
     * @throws SiteNotFoundException
     * @throws ExitException
     * @throws InvalidConfigException
     */
    public function handleRedirectsOnException(ExceptionEvent $event)
    {
        $request = Craft::$app->getRequest();

        // Only handle front-end site requests that are not live preview
        if (!$request->getIsSiteRequest() OR $request->getIsLivePreview() OR $this->processRedirect === false) {
            return;
        }

        // Avoid counting redirects twice when Sprout SEO and Redirects are
        // both installed and both call `handleRedirectsOnException` each request
        $this->processRedirect = false;

        $exception = $event->exception;

        // Rendering Twig can generate a 404 also: i.e. {% exit 404 %}
        if ($exception instanceof TwigRuntimeError) {
            // If this is a Twig Runtime error, use the previous exception
            $exception = $exception->getPrevious();
        }

        /**
         * @var HttpException $exception
         */
        if ($exception instanceof HttpException && $exception->statusCode === 404) {

            /** @var Settings $settings */
            $settings = SproutBaseRedirects::$app->settings->getRedirectsSettings();

            $currentSite = Craft::$app->getSites()->getCurrentSite();

            if ($settings->redirectMatchStrategy === 'urlWithoutQueryStrings') {
                $path = $request->getPathInfo();
                $absoluteUrl = UrlHelper::url($path);
            } else {
                $absoluteUrl = $request->getAbsoluteUrl();
            }

            if ($settings->excludedUrlPatterns && $this->isExcludedUrlPattern($absoluteUrl, $settings)) {
                return;
            }

            // Check if the requested URL needs to be redirected
            $redirect = SproutBaseRedirects::$app->redirects->findUrl($absoluteUrl, $currentSite);

            if (!$redirect && isset($settings->enable404RedirectLog) && $settings->enable404RedirectLog) {
                // Save new 404 Redirect
                $redirect = SproutBaseRedirects::$app->redirects->save404Redirect($absoluteUrl, $currentSite, $settings);
            }

            if ($redirect) {

                SproutBaseRedirects::$app->redirects->incrementCount($redirect);

                if ($settings->queryStringStrategy === 'removeQueryStrings') {
                    $queryString = '';
                } else {
                    $queryString = '?'.$request->getQueryStringWithoutPath();
                }

                if ($redirect->enabled && (int)$redirect->method !== 404) {
                    if (UrlHelper::isAbsoluteUrl($redirect->newUrl)) {
                        Craft::$app->getResponse()->redirect(
                            $redirect->newUrl.$queryString, $redirect->method
                        );
                    } else {
                        Craft::$app->getResponse()->redirect(
                            $redirect->getAbsoluteNewUrl().$queryString, $redirect->method
                        );
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
     * @param $absoluteUrl
     * @param $site
     *
     * @return Redirect|null
     * @throws DeprecationException
     */
    public function findUrl($absoluteUrl, Site $site)
    {
        $absoluteUrl = urldecode($absoluteUrl);
        $baseSiteUrl = Craft::getAlias($site->getBaseUrl());

        $allRedirects = (new Query())
            ->select([
                'redirects.id',
                'redirects.oldUrl',
                'redirects.newUrl',
                'redirects.method',
                'redirects.matchStrategy',
                'redirects.count',
                'elements.enabled'
            ])
            ->from('{{%sproutseo_redirects}} as redirects')
            ->leftJoin('{{%elements}} as elements', '[[redirects.id]] = [[elements.id]]')
            ->leftJoin('{{%elements_sites}} as elements_sites', '[[redirects.id]] = [[elements_sites.elementId]]')
            ->leftJoin('{{%structureelements}} as structureelements', '[[redirects.id]] = [[structureelements.elementId]]')
            ->orderBy('[[structureelements.lft]] asc')
            ->where([
                '[[elements_sites.siteId]]' => $site->id,
                '[[structureelements.level]]' => 1
            ])
            ->all();

        if (!$allRedirects) {
            return null;
        }

        $redirects = [];
        $pageNotFoundRedirects = [];

        foreach ($allRedirects as $redirect) {
            if ($redirect['method'] === 404) {
                $pageNotFoundRedirects[] = $redirect;
            } else {
                $redirects[] = $redirect;
            }
        }

        // Group all 404 Redirects at the end of the array
        $orderedRedirects = array_merge($redirects, $pageNotFoundRedirects);

        /**
         * @var Redirect $redirect
         */
        foreach ($orderedRedirects as $redirect) {

            if ($redirect['matchStrategy'] === 'regExMatch') {
                // Use backticks as delimiters as they are invalid characters for URLs
                $oldUrlPattern = '`'.$redirect['oldUrl'].'`';

                // Remove the base URL so we just have the relative path for the redirect
                $currentPath = preg_replace('`^'.$baseSiteUrl.'`', '', $absoluteUrl);

                if (preg_match($oldUrlPattern, $currentPath)) {

                    // Make sure URLs that redirect to another domain end in a slash
                    if ($redirect['newUrl'] !== null && UrlHelper::isAbsoluteUrl($redirect['newUrl'])) {
                        $newUrl = parse_url($redirect['newUrl']);

                        // If path is set, we know that the base domain has a slash before the path
                        if (isset($newUrl['path'])) {
                            $newUrlPattern = $redirect['newUrl'];
                        } else if (strpos($newUrl['host'], '$') === false) {
                            $newUrlPattern = $redirect['newUrl'].'/';
                        } else {

                            // If the hostname has a $ it probably uses a a capture group
                            // and is going to generate an invalid new URL when using it
                            // as at this point it doesn't appear to have a path
                            $invalidNewUrlMessage = 'The New URL value "'.$redirect['newUrl'].'" in Redirect ID '.$redirect['id'].' needs to be updated. The host name ('.$newUrl['host'].') of an absolute URL cannot contain capture groups and must end with a slash.';
                            Craft::error('sprout-base-redirects', $invalidNewUrlMessage);
                            Craft::$app->getDeprecator()->log('Target New URL is invalid.', $invalidNewUrlMessage);

                            // End the request, to avoid potential Open Redirect security issue
                            return null;
                        }
                    } else {
                        // We have a relative path
                        $newUrlPattern = $redirect['newUrl'];
                    }

                    // Replace capture groups if any
                    $redirect['newUrl'] = preg_replace($oldUrlPattern, $newUrlPattern, $currentPath);

                    return new Redirect($redirect);
                }
            } else if ($baseSiteUrl.$redirect['oldUrl'] === $absoluteUrl) {
                // Update null value to return home page
                $redirect['newUrl'] = $redirect['newUrl'] ?? '/';

                return new Redirect($redirect);
            }
        }

        return null;
    }

    /**
     * Get Redirect methods
     *
     * @return array
     */
    public function getMethods(): array
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
    public function updateRedirectMethod($ids, $newMethod): int
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
    public function getMethodUpdateResponse($status): string
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
     * @return string
     */
    public function removeSlash($uri): string
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
     * @return mixed|null
     */
    public function getStructureId()
    {
        $settings = SproutBaseRedirects::$app->settings->getRedirectsSettings();

        return $settings->structureId ?? null;
    }

    /**
     * Increments the count of a redirect when hit
     *
     * @param Redirect $redirect
     *
     * @return bool
     * @throws Throwable
     */
    public function incrementCount(Redirect $redirect): bool
    {
        try {
            $count = ++$redirect->count;

            Craft::$app->db->createCommand()->update('{{%sproutseo_redirects}}',
                ['count' => $count],
                ['id' => $redirect->id]
            )->execute();
        } catch (\Exception $e) {
            SproutBaseRedirects::error('Unable to increment redirect: '.$e->getMessage());
        }

        return true;
    }

    /**
     * Save a 404 redirect and check total404Redirects setting
     *
     * @param                        $absoluteUrl
     * @param Site                   $site
     * @param RedirectsSettingsModel $settings
     *
     * @return Redirect|null
     * @throws ElementNotFoundException
     * @throws Exception
     * @throws Throwable
     */
    public function save404Redirect($absoluteUrl, Site $site, Settings $settings)
    {
        $request = Craft::$app->getRequest();

        $redirect = new Redirect();

        $baseUrl = Craft::getAlias($site->getBaseUrl());

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
        $redirect->matchStrategy = 0;
        $redirect->enabled = 0;
        $redirect->count = 0;
        $redirect->siteId = $site->id;
        $redirect->lastRemoteIpAddress = $settings->trackRemoteIp ? $request->getRemoteIp() : null;
        $redirect->lastReferrer = $request->getReferrer();
        $redirect->lastUserAgent = $request->getUserAgent();
        $redirect->dateLastUsed = Db::prepareDateForDb(new DateTime());

        if (!Craft::$app->elements->saveElement($redirect)) {
            Craft::warning($redirect->getErrors(), 'sprout-base-redirects');
            return null;
        }

        $this->purge404s([$redirect->id], $site->id);

        return $redirect;
    }

    /**
     * @return int|string
     */
    public function getTotalNon404Redirects()
    {
        $count = Redirect::find()
            ->where('method !=:method', [
                ':method' => RedirectMethods::PageNotFound
            ])
            ->anyStatus()
            ->count();

        return $count;
    }

    /**
     * @param int $plusTotal
     *
     * @return bool
     */
    public function canCreateRedirects($plusTotal = 0): bool
    {
        $sproutRedirectsIsPro = SproutBase::$app->settings->isEdition('sprout-redirects', SproutBaseRedirects::EDITION_PRO);
        $sproutSeoIsPro = SproutBase::$app->settings->isEdition('sprout-seo', SproutBaseRedirects::EDITION_PRO);

        if (!$sproutSeoIsPro && !$sproutRedirectsIsPro) {
            $count = SproutBaseRedirects::$app->redirects->getTotalNon404Redirects();
            if ($count >= 3 || $plusTotal + $count > 3) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param                        $absoluteUrl
     * @param RedirectsSettingsModel $settings
     *
     * @return bool
     */
    public function isExcludedUrlPattern($absoluteUrl, Settings $settings): bool
    {
        $excludedUrlPatterns = explode(PHP_EOL, $settings->excludedUrlPatterns);

        // Remove empty lines and comments
        $excludedUrlPatterns = array_filter($excludedUrlPatterns, static function($excludedUrlPattern) {
            return !(empty($excludedUrlPattern) || strpos($excludedUrlPattern, '#') === 0);
        });

        foreach ($excludedUrlPatterns as $excludedUrlPattern) {
            if (strpos($excludedUrlPattern, '#') === 0) {
                continue;
            }

            // Use backticks as delimiters as they are invalid characters for URLs
            $excludedUrlPattern = '`'.$excludedUrlPattern.'`';

            if (preg_match($excludedUrlPattern, $absoluteUrl)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array $excludedIds
     * @param null  $siteId
     * @param bool  $force
     */
    public function purge404s($excludedIds = [], $siteId = null, $force = false)
    {
        /** @var RedirectsSettingsModel $redirectSettings */
        $redirectSettings = SproutBase::$app->settings->getBaseSettings(RedirectsSettingsModel::class);
        $probability = (int)$redirectSettings->cleanupProbability;

        // See Craft Garbage collection treatment of probability
        // https://docs.craftcms.com/v3/gc.html
        /** @noinspection RandomApiMigrationInspection */
        if (!$force && mt_rand(0, 1000000) >= $probability) {
            return;
        }

        /// Loop through all Sites if we don't have a specific site to target
        if ($siteId === null) {
            $siteIds = Craft::$app->getSites()->getAllSiteIds();
        } else {
            $siteIds = [$siteId];
        }

        foreach ($siteIds as $currentSiteId) {

            $query = Redirect::find()
                ->where(['method' => RedirectMethods::PageNotFound])
                ->andWhere(['siteId' => $currentSiteId]);

            // Don't delete these Redirects
            if (!empty($excludedIds)) {
                $query->andWhere(['not in', 'sproutseo_redirects.id', $excludedIds]);
            }

            // orderBy works as string but doesn't recognize second DESC setting as array
            $query->orderBy('sproutseo_redirects.count DESC, sproutseo_redirects.dateUpdated DESC')
                ->anyStatus();

            $ids = $query->ids();

            $limitAdjustment = empty($excludedIds) ? 0 : 1;
            $idsToDelete = array_slice($ids, $redirectSettings->total404Redirects - $limitAdjustment);

            if (!empty($idsToDelete)) {

                $batchSize = 25;

                // Leave second argument blank and bust loop with break statement. Really. It's in the docs.
                // https://www.php.net/manual/en/control-structures.for.php
                for ($i = 0; ; $i++) {

                    // Get me a list of the IDs to delete for this iteration. If less
                    // than the batchSize, that specific number will be returned
                    $loopedIdsToDelete = array_slice($idsToDelete, ($i * $batchSize) + 1, $batchSize);

                    // Adjust final batch so we don't add 1
                    if (count($loopedIdsToDelete) < $batchSize) {
                        $loopedIdsToDelete = array_slice($idsToDelete, $i * $batchSize, $batchSize);
                    }

                    // End the for loop once we don't find any more ids in our current offset
                    if (empty($loopedIdsToDelete)) {
                        break;
                    }

                    // Create a job for this batch
                    $excludedIds = $excludedIds ?? null;
                    // Call the delete redirects job, give it some delay so we don't demand
                    // all the server resources. This is most important if anybody changes the
                    // Redirect Limit setting in a massive way
                    $delay = ($i - 1) * 20;

                    $purgeElements = new PurgeElements();
                    $purgeElements->elementType = Redirect::class;
                    $purgeElements->siteId = $currentSiteId;
                    $purgeElements->idsToDelete = $loopedIdsToDelete;
                    $purgeElements->idsToExclude = $excludedIds;

                    SproutBase::$app->utilities->purgeElements($purgeElements, $delay);
                }
            }
        }
    }
}
