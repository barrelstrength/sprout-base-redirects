<?php

namespace barrelstrength\sproutbasereports\migrations;

use craft\db\Migration;
use craft\db\Query;
use yii\base\NotSupportedException;

/**
 * m191109_000000_add_redirect_tracking_columns migration.
 */
class m191109_000000_add_redirect_tracking_columns extends Migration
{
    /**
     * @inheritdoc
     *
     * @throws NotSupportedException
     */
    public function safeUp(): bool
    {
        $table = '{{%sproutseo_redirects}}';

        if (!$this->db->columnExists($table, 'lastRemoteIpAddress')) {
            $this->addColumn($table, 'lastRemoteIpAddress', $this->string()->after('count'));
        }

        if (!$this->db->columnExists($table, 'lastReferrer')) {
            $this->addColumn($table, 'lastReferrer', $this->string()->after('lastRemoteIpAddress'));
        }

        if (!$this->db->columnExists($table, 'lastUserAgent')) {
            $this->addColumn($table, 'lastUserAgent', $this->string()->after('lastReferrer'));
        }

        if (!$this->db->columnExists($table, 'dateLastUsed')) {
            $this->addColumn($table, 'dateLastUsed', $this->string()->after('lastUserAgent'));
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m191109_000000_add_redirect_tracking_columns cannot be reverted.\n";
        return false;
    }
}
