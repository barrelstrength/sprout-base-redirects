<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\services;

use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbaseredirects\models\Settings as SproutBaseRedirectSettings;
use craft\base\Model;
use yii\base\Component;
use yii\db\Exception;

/**
 *
 * @property null|Model $pluginSettings
 * @property Model      $redirectsSettings
 * @property int        $descriptionLength
 */
class Settings extends Component
{
    /**
     * @return SproutBaseRedirectSettings
     */
    public function getRedirectsSettings(): SproutBaseRedirectSettings
    {
        /** @var SproutBaseRedirectSettings $settings */
        $settings = SproutBase::$app->settings->getBaseSettings(SproutBaseRedirectSettings::class, 'sprout-redirects');

        return $settings;
    }

    /**
     * @param array $settingsArray
     *
     * @return mixed
     * @throws Exception
     */
    public function saveRedirectsSettings(array $settingsArray)
    {
        return SproutBase::$app->settings->saveBaseSettings($settingsArray, SproutBaseRedirectSettings::class);
    }
}
