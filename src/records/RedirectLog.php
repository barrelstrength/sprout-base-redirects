<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\records;


use craft\db\ActiveRecord;


/**
 * SproutBaseRedirects - RedirectLog
 */
class RedirectLog extends ActiveRecord
{
    /**
     * @return string
     */
    public static function tableName()
    {
        return '{{%sproutseo_redirects_log}}';
    }
}
