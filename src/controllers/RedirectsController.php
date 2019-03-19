<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\controllers;

use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbaseredirects\elements\Redirect;
use barrelstrength\sproutbaseredirects\SproutBaseRedirects;
use barrelstrength\sproutbaseredirects\models\Settings;
use barrelstrength\sproutredirects\SproutRedirects;
use craft\base\Plugin;
use craft\helpers\UrlHelper;
use craft\web\Controller;
use Craft;
use yii\web\NotFoundHttpException;
use yii\web\Response;
use yii\base\Exception;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;

/**
 * Redirects controller
 */
class RedirectsController extends Controller
{
    private $permissions = [];

    public function init()
    {
        $permissionNames = Settings::getSharedPermissions();
        $pluginHandle = Craft::$app->request->getSegment(1);
        $this->permissions = SproutBase::$app->settings->getSharedPermissions($permissionNames, 'sprout-redirects', $pluginHandle);

        parent::init();
    }

    /**
     * @param string|null $siteHandle
     *
     * @return Response
     * @throws ForbiddenHttpException
     * @throws \craft\errors\SiteNotFoundException
     */
    public function actionRedirectsIndexTemplate(string $pluginHandle, $siteHandle = null): Response
    {
        $this->requirePermission($this->permissions['sproutRedirects-editRedirects']);

        if ($siteHandle === null) {
            $primarySite = Craft::$app->getSites()->getPrimarySite();
            $siteHandle = $primarySite->handle;
        }

        $currentSite = Craft::$app->getSites()->getSiteByHandle($siteHandle);

        if (!$currentSite) {
            throw new ForbiddenHttpException(Craft::t('sprout-base-redirects', 'Something went wrong'));
        }

        /** @var Plugin $plugin */
        $plugin = Craft::$app->getPlugins()->getPlugin('sprout-redirects');
        $sproutRedirectsIsPro = $plugin !== null ? $plugin->is(SproutRedirects::EDITION_PRO) : false;

        /** @var Plugin $sproutSeoPlugin */
        $sproutSeoPlugin = Craft::$app->getPlugins()->getPlugin('sprout-seo');
        $sproutSeoPluginIsInstalled = $sproutSeoPlugin->isInstalled ?? false;

        return $this->renderTemplate('sprout-base-redirects/redirects/index', [
            'currentSite' => $currentSite,
            'pluginHandle' => $pluginHandle,
            'proFeaturesEnabled' => $sproutSeoPluginIsInstalled || $sproutRedirectsIsPro
        ]);
    }

    /**
     * Edit a Redirect
     *
     * @param null          $redirectId
     * @param null          $siteHandle
     * @param Redirect|null $redirect
     *
     * @return Response
     * @throws ForbiddenHttpException
     * @throws NotFoundHttpException
     * @throws \craft\errors\SiteNotFoundException
     */
    public function actionEditRedirectTemplate(string $pluginHandle, $redirectId = null, $siteHandle = null, Redirect $redirect = null): Response
    {
        $this->requirePermission($this->permissions['sproutRedirects-editRedirects']);

        if ($siteHandle === null) {
            $primarySite = Craft::$app->getSites()->getPrimarySite();
            $siteHandle = $primarySite->handle;
        }

        $currentSite = Craft::$app->getSites()->getSiteByHandle($siteHandle);

        if (!$currentSite) {
            throw new ForbiddenHttpException(Craft::t('sprout-base-redirects', 'Unable to identify current site.'));
        }

        $methodOptions = SproutBaseRedirects::$app->redirects->getMethods();

        // Now let's set up the actual redirect
        if ($redirect === null) {
            if ($redirectId !== null) {

                $redirect = Craft::$app->getElements()->getElementById($redirectId, Redirect::class, $currentSite->id);

                if (!$redirect) {
                    throw new NotFoundHttpException(Craft::t('sprout-base-redirects', 'Unable to find a Redirect with the given id: {id}', [
                        'id' => $redirectId
                    ]));
                }

                if (!$redirect) {
                    $redirect = new Redirect();
                    $redirect->id = $redirectId;
                }

                $redirect->siteId = $currentSite->id;
            } else {
                $redirect = new Redirect();
                $redirect->siteId = $currentSite->id;
            }
        }

        $redirect->newUrl = $redirect->newUrl ?? '';

        $continueEditingUrl = $pluginHandle.'/redirects/edit/{id}/'.$currentSite->handle;
        $saveAsNewUrl = $pluginHandle.'/redirects/new/'.$currentSite->handle;

        $crumbs = [
            [
                'label' => Craft::t('sprout-base-redirects', 'Redirects'),
                'url' => UrlHelper::cpUrl('redirects')
            ]
        ];

        $tabs = [
            [
                'label' => 'Redirect',
                'url' => '#tab1',
                'class' => null,
            ]
        ];

        /** @var SproutRedirects $plugin */
        $plugin = Craft::$app->getPlugins()->getPlugin('sprout-redirects');
        $sproutRedirectsEdition = $plugin->edition ?? 'lite';

        return $this->renderTemplate('sprout-base-redirects/redirects/_edit', [
            'currentSite' => $currentSite,
            'redirect' => $redirect,
            'methodOptions' => $methodOptions,
            'crumbs' => $crumbs,
            'tabs' => $tabs,
            'continueEditingUrl' => $continueEditingUrl,
            'saveAsNewUrl' => $saveAsNewUrl,
            'edition' => $sproutRedirectsEdition
        ]);
    }

    /**
     * Saves a Redirect
     *
     * @throws BadRequestHttpException
     * @throws Exception
     * @throws \Throwable
     */
    public function actionSaveRedirect()
    {
        $this->requirePostRequest();
        $this->requirePermission($this->permissions['sproutRedirects-editRedirects']);
        
        $redirectId = Craft::$app->getRequest()->getBodyParam('redirectId');
        $siteId = Craft::$app->getRequest()->getBodyParam('siteId');
        $oldUrl = Craft::$app->getRequest()->getRequiredBodyParam('oldUrl');
        $newUrl = Craft::$app->getRequest()->getBodyParam('newUrl');

        if ($redirectId) {
            $redirect = Craft::$app->getElements()->getElementById($redirectId, Redirect::class, $siteId);

            if (!$redirect) {
                throw new Exception(Craft::t('sprout-base-redirects', 'No redirect exists with the ID “{id}”', [
                    'id' => $redirectId
                ]));
            }

            if (!$redirect) {

                $redirect = new Redirect();
                $redirect->id = $redirectId;
            }
        } else {
            $redirect = new Redirect();
        }

        $defaultSiteId = Craft::$app->getSites()->getPrimarySite()->id;

        // Set the event attributes, defaulting to the existing values for
        // whatever is missing from the post data
        $redirect->siteId = $siteId ?? $defaultSiteId;
        $redirect->oldUrl = $oldUrl;
        $redirect->newUrl = $newUrl;
        $redirect->method = Craft::$app->getRequest()->getRequiredBodyParam('method');
        $redirect->regex = Craft::$app->getRequest()->getBodyParam('regex');

        if (!$redirect->regex) {
            $redirect->regex = 0;
        }

        $redirect->enabled = Craft::$app->getRequest()->getBodyParam('enabled');

        if (!Craft::$app->elements->saveElement($redirect)) {
            Craft::$app->getSession()->setError(Craft::t('sprout-base-redirects', 'Couldn’t save redirect.'));

            // Send the event back to the template
            Craft::$app->getUrlManager()->setRouteParams([
                'redirect' => $redirect
            ]);

            return null;
        }

        Craft::$app->getSession()->setNotice(Craft::t('sprout-base-redirects', 'Redirect saved.'));

        return $this->redirectToPostedUrl($redirect);
    }

    /**
     * Deletes a Redirect
     *
     * @throws BadRequestHttpException
     * @throws \Throwable
     */
    public function actionDeleteRedirect()
    {
        $this->requirePostRequest();
        $this->requirePermission($this->permissions['sproutRedirects-editRedirects']);

        $redirectId = Craft::$app->getRequest()->getRequiredBodyParam('redirectId');

        if (Craft::$app->elements->deleteElementById($redirectId)) {
            Craft::$app->getSession()->setNotice(Craft::t('sprout-base-redirects', 'Redirect deleted.'));
            $this->redirectToPostedUrl();
        } else {
            Craft::$app->getSession()->setError(Craft::t('sprout-base-redirects', 'Couldn’t delete redirect.'));
        }
    }
}
