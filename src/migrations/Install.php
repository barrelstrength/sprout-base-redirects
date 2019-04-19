<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\migrations;

use barrelstrength\sproutbaseredirects\models\Settings;
use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\models\Structure;
use barrelstrength\sproutbaseredirects\models\Settings as SproutRedirectsSettings;
use barrelstrength\sproutbase\migrations\Install as SproutBaseInstall;

/**
 *
 * @property \barrelstrength\sproutbaseredirects\models\Settings $sproutRedirectsSettingsModel
 * @property null|int                                            $structureId
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
        $this->insertDefaultSettings();
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
        $migration = new SproutBaseInstall();
        ob_start();
        $migration->safeUp();
        ob_end_clean();

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
    public function insertDefaultSettings()
    {
        $settingsRow = (new Query())
            ->select(['*'])
            ->from(['{{%sprout_settings}}'])
            ->where(['model' => SproutRedirectsSettings::class])
            ->one();

        if (is_null($settingsRow)){

            $settings = new Settings();
            $settings->structureId = $this->createStructureId();

            $settingsArray = [
                'model' => SproutRedirectsSettings::class,
                'settings' => json_encode($settings->toArray())
            ];

            $this->insert('{{%sprout_settings}}', $settingsArray);
        }
    }

    /**
     * @return int|null
     * @throws \craft\errors\StructureNotFoundException
     */
    private function createStructureId()
    {
        $maxLevels = 1;
        $structure = new Structure();
        $structure->maxLevels = $maxLevels;
        Craft::$app->structures->saveStructure($structure);

        return $structure->id;
    }
}
