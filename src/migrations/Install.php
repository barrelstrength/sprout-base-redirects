<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\migrations;

use Craft;
use craft\db\Migration;
use craft\models\Structure;
use barrelstrength\sproutbaseredirects\models\Settings as SproutRedirectsSettings;
use craft\services\Plugins;

/**
 *
 * @property \barrelstrength\sproutbaseredirects\models\Settings $sproutRedirectsSettingsModel
 * @property null|int                                            $structureId
 */
class Install extends Migration
{
    const PROJECT_CONFIG_HANDLE = 'sprout-base-redirects';
    // Public Properties
    // =========================================================================

    /**
     * @var string The database driver to use
     */
    public $driver;

    // Public Methods
    // =========================================================================

    /**
     * @return bool
     * @throws \Throwable
     */
    public function safeUp(): bool
    {
        $this->createTables();
        return true;
    }

    /**
     * @return bool|void
     * @throws \Throwable
     */
    public function safeDown()
    {
        $this->dropTable('{{%sproutseo_redirects}}');
    }

    // Protected Methods
    // =========================================================================

    /**
     * @throws \craft\errors\StructureNotFoundException
     * @throws \yii\base\ErrorException
     * @throws \yii\base\Exception
     * @throws \yii\base\NotSupportedException
     * @throws \yii\web\ServerErrorHttpException
     */
    protected function createTables()
    {
        $table = '{{%sproutseo_redirects}}';

        if (!$this->db->tableExists($table)) {
            $this->createTable($table, [
                'id' => $this->primaryKey(),
                'oldUrl' => $this->string()->notNull(),
                'newUrl' => $this->string(),
                'method' => $this->integer(),
                'regex' => $this->boolean()->defaultValue(false),
                'count' => $this->integer()->defaultValue(0),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndexes();
            $this->addForeignKeys();
        }

        $this->insertDefaultSettings();
    }

    protected function createIndexes()
    {
        $this->createIndex(null, '{{%sproutseo_redirects}}', 'id');
    }

    protected function addForeignKeys()
    {
        $this->addForeignKey(
            null,
            '{{%sproutseo_redirects}}', 'id',
            '{{%elements}}', 'id', 'CASCADE'
        );
    }

    /**
     * @throws \craft\errors\StructureNotFoundException
     * @throws \yii\base\ErrorException
     * @throws \yii\base\Exception
     * @throws \yii\base\NotSupportedException
     * @throws \yii\web\ServerErrorHttpException
     */
    protected function insertDefaultSettings()
    {
        $settings = $this->getSproutRedirectsSettingsModel();

        // Add our default plugin settings
        $pluginHandle = self::PROJECT_CONFIG_HANDLE;
        Craft::$app->getProjectConfig()->set(Plugins::CONFIG_PLUGINS_KEY.'.'.$pluginHandle.'.settings', $settings->toArray());
    }

    /**
     * @return SproutRedirectsSettings
     * @throws \craft\errors\StructureNotFoundException
     */
    private function getSproutRedirectsSettingsModel(): SproutRedirectsSettings
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $settings = new SproutRedirectsSettings();
        $pluginHandle = self::PROJECT_CONFIG_HANDLE;

        $sproutBaseRedirectSettings = $projectConfig->get('plugins.'.$pluginHandle.'.settings');

        if ($sproutBaseRedirectSettings &&
            isset($sproutBaseRedirectSettings['structureId']) &&
            is_numeric($sproutBaseRedirectSettings['structureId'])) {

            $settings->pluginNameOverride = $sproutBaseRedirectSettings['pluginNameOverride'];
            $settings->structureId = $sproutBaseRedirectSettings['structureId'];
            $settings->enable404RedirectLog = $sproutBaseRedirectSettings['enable404RedirectLog'];
            $settings->total404Redirects = $sproutBaseRedirectSettings['total404Redirects'];
            return $settings;
        }

        // Need to check for how we stored data in Sprout SEO schema and migrate things if we find them
        // @deprecate in future version
        $sproutSeoSettings = $projectConfig->get('plugins.sprout-seo.settings');

        if ($sproutSeoSettings &&
            isset($sproutSeoSettings['structureId']) &&
            is_numeric($sproutSeoSettings['structureId'])) {

            $settings->pluginNameOverride = $sproutSeoSettings['pluginNameOverride'];
            $settings->structureId = $sproutSeoSettings['structureId'];
            $settings->enable404RedirectLog = $sproutSeoSettings['enable404RedirectLog'];
            $settings->total404Redirects = $sproutSeoSettings['total404Redirects'];
            return $settings;
        }

        // If none of the above have an existing Structure ID, create a new one
        $settings->structureId = $this->getStructureId();
        return $settings;
    }

    /**
     * @return int|null
     * @throws \craft\errors\StructureNotFoundException
     */
    private function getStructureId()
    {
        $maxLevels = 1;
        $structure = new Structure();
        $structure->maxLevels = $maxLevels;
        Craft::$app->structures->saveStructure($structure);

        return $structure->id;
    }
}
