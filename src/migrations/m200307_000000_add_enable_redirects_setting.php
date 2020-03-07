<?php

namespace barrelstrength\sproutbaseredirects\migrations;

use craft\db\Migration;
use craft\db\Query;

class m200307_000000_add_enable_redirects_setting extends Migration
{
    /**
     * @return bool
     */
    public function safeUp(): bool
    {
        $redirectSettings = (new Query())
            ->select([
                'id',
                'settings'
            ])
            ->from('{{%sprout_settings}}')
            ->where([
                'model' => 'barrelstrength\sproutbaseredirects\models\Settings'
            ])
            ->one();

        $settingsArray = json_decode($redirectSettings['settings'], true);

        // Only do this once
        if (isset($settingsArray['enableRedirects'])) {
            return true;
        }

        $settingsArray['enableRedirects'] = 1;

        $this->update('{{%sprout_settings}}', [
            'settings' => json_encode($settingsArray)
        ], [
            'id' => $redirectSettings['id']
        ], [], false);

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m200307_000000_add_enable_redirects_setting cannot be reverted.\n";

        return false;
    }
}
