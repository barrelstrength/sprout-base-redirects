<?php

namespace barrelstrength\sproutbaseredirects\migrations;

use craft\db\Migration;
use craft\db\Table;
use yii\base\NotSupportedException;

/**
 * m191210_000000_update_dateLastUsed_column_type migration.
 */
class m191210_000000_update_dateLastUsed_column_type extends Migration
{
    /**
     * @inheritdoc
     *
     * @throws NotSupportedException
     */
    public function safeUp(): bool
    {
        $table = '{{%sproutseo_redirects}}';

        if ($this->db->columnExists($table, 'dateLastUsed')) {
            if ($this->db->getIsPgsql()) {
                // Manually construct the SQL for Postgres
                // (see https://github.com/yiisoft/yii2/issues/12077)
                $this->execute('alter table ' . $table . ' alter column [[dateLastUsed]] type timestamp(0) using [[dateLastUsed]]::timestamp(0), alter column [[dateLastUsed]] drop not null');
            } else {
                $this->alterColumn($table, 'dateLastUsed', $this->dateTime()->unsigned());
            }
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m191210_000000_update_dateLastUsed_column_type cannot be reverted.\n";
        return false;
    }
}
