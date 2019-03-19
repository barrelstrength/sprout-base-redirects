<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\models;


use craft\base\Model;

/**
 *
 * @property array $settingsNavItems
 */
class Settings extends Model
{
    /**
     * @var string
     */
    public $pluginNameOverride = '';

    /**
     * @var string
     */
    public $structureId = '';

    /**
     * @var bool
     */
    public $enable404RedirectLog = false;

    /**
     * @var int
     */
    public $total404Redirects = 250;

    /**
     * @var bool
     */
    public $enableMultilingualSitemaps = false;

    /**
     * @return array
     */
    public static function getSharedPermissions(): array
    {
        return [
            'editRedirects'
        ];
    }
}
