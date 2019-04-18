<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\services;

use barrelstrength\sproutbase\SproutBase;
use craft\base\Model;
use craft\db\Query;
use yii\base\Component;
use barrelstrength\sproutbaseredirects\models\Settings as RedirectsSettings;

use Craft;

/**
 *
 * @property null|\craft\base\Model $pluginSettings
 * @property int                    $descriptionLength
 */
class Settings extends Component
{
    /**
     * @return \craft\base\Model|null
     */
    public function getPluginSettings()
    {
        $sproutSeo = Craft::$app->getPlugins()->getPlugin('sprout-seo');
        $settings = null;
        if ($sproutSeo) {
            $settings = $sproutSeo->getSettings();
        } else {
            $sproutRedirects = Craft::$app->getPlugins()->getPlugin('sprout-redirects');
            if ($sproutRedirects) {
                $settings = $sproutRedirects->getSettings();
            }
        }

        return $settings;
    }

    /**
     * @return Model
     */
    public function getRedirectsSettings(): Model
    {
        $settings = SproutBase::$app->settings->getBaseSettings(RedirectsSettings::class);

        return $settings;
    }

    /**
     * @param array $settingsArray
     * @return int
     * @throws \yii\db\Exception
     */
    public function saveRedirectsSettings(array $settingsArray)
    {
        $result = SproutBase::$app->settings->saveBaseSettings($settingsArray,RedirectsSettings::class);

        return $result;
    }

    /**
     * @return array|bool
     */
    private function getRedirectsSettingsQuery()
    {
        $query = (new Query())
            ->select(['settings'])
            ->from(['{{%sproutbase_settings}}'])
            ->where(['model' => RedirectsSettings::class])
            ->one();

        return $query;
    }
}
