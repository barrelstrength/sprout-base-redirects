<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\migrations;

use barrelstrength\sproutseo\SproutSeo;
use Craft;
use craft\db\Migration;
use craft\models\Structure;
use barrelstrength\sproutbaseredirects\models\Settings as SproutRedirectsSettings;
use craft\services\Plugins;
use craft\services\ProjectConfig;

/**
 *
 * @property null|int $structureId
 */
class Install extends Migration
{
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
        $pluginHandle = 'sprout-redirects';
        Craft::$app->getProjectConfig()->set(Plugins::CONFIG_PLUGINS_KEY.'.'.$pluginHandle.'.settings', $settings->toArray());

        // Remove unused settings
        Craft::$app->getProjectConfig()->remove(Plugins::CONFIG_PLUGINS_KEY.'.sprout-base-redirects');
    }

    /**
     * @return SproutRedirectsSettings
     * @throws \craft\errors\StructureNotFoundException
     */
    private function getSproutRedirectsSettingsModel(): SproutRedirectsSettings
    {
        $projectConfig = Craft::$app->getProjectConfig();
        $settings = new SproutRedirectsSettings();

        $sproutRedirectSettings = $projectConfig->get('plugins.sprout-redirects.settings');

        // If we already have settings and a structureId defined for Sprout Redirects
        if ($sproutRedirectSettings &&
            isset($sproutRedirectSettings['structureId']) &&
            is_numeric($sproutRedirectSettings['structureId'])) {

            $settings->structureId = $sproutRedirectSettings['structureId'];
            return $settings;
        }

        // Need to fix how settings were stored in an earlier install
        // @deprecate in future version
        $sproutBaseRedirectSettings = $projectConfig->get('plugins.sprout-base-redirects.settings');

        if ($sproutBaseRedirectSettings &&
            isset($sproutBaseRedirectSettings['structureId']) &&
            is_numeric($sproutBaseRedirectSettings['structureId'])) {

            $settings->structureId = $sproutBaseRedirectSettings['structureId'];
            return $settings;
        }

        // Need to check for how we stored data in Sprout SEO schema and migrate things if we find them
        // @deprecate in future version
        $sproutSeoSettings = $projectConfig->get('plugins.sprout-seo.settings');

        if ($sproutSeoSettings &&
            isset($sproutSeoSettings['structureId']) &&
            is_numeric($sproutSeoSettings['structureId'])) {

            $settings->structureId = $sproutSeoSettings['structureId'];
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
