<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects;

use Craft;

abstract class SproutBaseRedirectsHelper
{
    /**
     * Register the Sprout Base module on the Craft::$app instance
     *
     * This should be called in the plugin init() method
     */
    public static function registerModule()
    {
        $moduleId = 'sprout-base-redirects';

        if (!Craft::$app->hasModule($moduleId)) {

            $sproutModule = new SproutBaseRedirects($moduleId);
            Craft::$app->setModule($moduleId, $sproutModule);

            // Have Craft load this module right away (so we can create templates)
            Craft::$app->getModule($moduleId);
        }
    }
}
