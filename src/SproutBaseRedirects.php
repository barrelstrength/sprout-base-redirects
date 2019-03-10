<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects;

use barrelstrength\sproutbase\base\BaseSproutTrait;
use barrelstrength\sproutbaseredirects\controllers\RedirectsController;
use barrelstrength\sproutbaseredirects\services\App;

use craft\events\RegisterTemplateRootsEvent;
use Craft;
use craft\web\View;
use yii\base\Module;
use craft\helpers\ArrayHelper;
use craft\i18n\PhpMessageSource;
use yii\base\Event;

use yii\web\HttpException;
use craft\web\ErrorHandler;
use craft\events\ExceptionEvent;
use craft\helpers\UrlHelper;

/**
 *
 * @property mixed $cpNavItem
 * @property array $cpUrlRules
 * @property array $siteUrlRules
 */
class SproutBaseRedirects extends Module
{
    use BaseSproutTrait;

    /**
     * @var string
     */
    public $handle;

    /**
     * Enable use of SproutBaseRedirects::$app-> in place of Craft::$app->
     *
     * @var \barrelstrength\sproutbaseredirects\services\App
     */
    public static $app;

    /**
     * Identify our plugin for BaseSproutTrait
     *
     * @var string
     */
    public static $pluginHandle = 'sprout-base-redirects';

    /**
     * @var string|null The translation category that this module translation messages should use. Defaults to the lowercase plugin handle.
     */
    public $t9nCategory;

    /**
     * @var string The language that the module messages were written in
     */
    public $sourceLanguage = 'en-US';

    /**
     * @todo - Copied from craft/base/plugin. Ask P&T if this is the best approach
     *
     * @inheritdoc
     */
    public function __construct($id, $parent = null, array $config = [])
    {
        // Set some things early in case there are any settings, and the settings model's
        // init() method needs to call Craft::t() or Plugin::getInstance().

        $this->handle = 'sprout-base-redirects';
        $this->t9nCategory = ArrayHelper::remove($config, 't9nCategory', $this->t9nCategory ?? strtolower($this->handle));
        $this->sourceLanguage = ArrayHelper::remove($config, 'sourceLanguage', $this->sourceLanguage);

        if (($basePath = ArrayHelper::remove($config, 'basePath')) !== null) {
            $this->setBasePath($basePath);
        }

        // Translation category
        $i18n = Craft::$app->getI18n();
        /** @noinspection UnSafeIsSetOverArrayInspection */
        if (!isset($i18n->translations[$this->t9nCategory]) && !isset($i18n->translations[$this->t9nCategory.'*'])) {
            $i18n->translations[$this->t9nCategory] = [
                'class' => PhpMessageSource::class,
                'sourceLanguage' => $this->sourceLanguage,
                'basePath' => $this->getBasePath().DIRECTORY_SEPARATOR.'translations',
                'allowOverrides' => true,
            ];
        }

        // Set this as the global instance of this plugin class
        static::setInstance($this);

        parent::__construct($id, $parent, $config);
    }

    public function init()
    {
        self::$app = new App();
        Craft::setAlias('@sproutbaseredirects', $this->getBasePath());

        // Setup Controllers
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            $this->controllerNamespace = 'sproutbaseredirects\\console\\controllers';
        } else {
            $this->controllerNamespace = 'sproutbaseredirects\\controllers';

            $this->controllerMap = [
                'redirects' => RedirectsController::class
            ];
        }

        // Setup Template Roots
        Event::on(View::class, View::EVENT_REGISTER_CP_TEMPLATE_ROOTS, function(RegisterTemplateRootsEvent $e) {
            $e->roots['sprout-base-redirects'] = $this->getBasePath().DIRECTORY_SEPARATOR.'templates';
        });

        Event::on(ErrorHandler::class, ErrorHandler::EVENT_BEFORE_HANDLE_EXCEPTION, function(ExceptionEvent $event) {

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

                // @todo - how could we get the structureId setting?
                $settings = self::$app->settings->getPluginSettings();

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
        });

        parent::init();
    }
}
