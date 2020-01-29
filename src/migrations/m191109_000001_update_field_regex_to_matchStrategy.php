<?php

namespace barrelstrength\sproutbaseredirects\migrations;

use craft\db\Migration;
use craft\db\Query;
use yii\base\NotSupportedException;

/**
 * m191109_000001_update_field_regex_to_matchStrategy migration.
 */
class m191109_000001_update_field_regex_to_matchStrategy extends Migration
{
    /**
     * @inheritdoc
     *
     * @throws NotSupportedException
     */
    public function safeUp(): bool
    {
        $table = '{{%sproutseo_redirects}}';

        if ($this->db->columnExists($table, 'regex')) {

            if (!$this->db->columnExists($table, 'matchStrategy')) {
                $this->addColumn($table, 'matchStrategy', $this->string());
            }

            $redirects = (new Query())
                ->select('*')
                ->from([$table])
                ->all();

            foreach ($redirects as $redirect) {
                // Update regex = false => matchStrategy = exactMatch
                if ($redirect['regex'] == false) {
                    $this->update($table,
                        ['matchStrategy' => 'exactMatch'],
                        ['id' => $redirect['id']],
                        [],
                        false
                    );
                }

                if ($redirect['regex'] == true) {
                    // Update regex = true => matchStrategy = regExMatch
                    $this->update($table,
                        ['matchStrategy' => 'regExMatch'],
                        ['id' => $redirect['id']],
                        [],
                        false
                    );
                }
            }


            $this->dropColumn($table, 'regex');
        }

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m191109_000001_update_field_regex_to_matchStrategy cannot be reverted.\n";

        return false;
    }
}
