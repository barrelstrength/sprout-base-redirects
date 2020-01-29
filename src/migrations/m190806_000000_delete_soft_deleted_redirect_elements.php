<?php

namespace barrelstrength\sproutbaseredirects\migrations;

use barrelstrength\sproutbaseredirects\jobs\DeleteSoftDeletedRedirects;
use Craft;
use craft\db\Migration;

/**
 * m190806_000000_delete_soft_deleted_redirect_elements migration.
 */
class m190806_000000_delete_soft_deleted_redirect_elements extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        Craft::$app->getQueue()->push(new DeleteSoftDeletedRedirects());

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        echo "m190806_000000_delete_soft_deleted_redirect_elements cannot be reverted.\n";

        return false;
    }
}
