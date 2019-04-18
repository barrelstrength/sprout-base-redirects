<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\models;


use barrelstrength\sproutbase\base\SharedPermissionsInterface;
use craft\base\Model;

/**
 *
 * @property array  $sharedPermissions
 * @property string $mainPluginHandle
 * @property array  $settingsNavItems
 */
class Settings extends Model implements SharedPermissionsInterface
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
     * @return array
     */
    public function getSharedPermissions(): array
    {
        return [
            'editRedirects'
        ];
    }

    /**
     * @return string
     */
    public function getProjectConfigHandle(): string
    {
        return 'sprout-base-redirects';
    }
}
