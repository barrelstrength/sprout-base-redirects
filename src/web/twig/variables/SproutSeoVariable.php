<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\web\twig\variables;

use barrelstrength\sproutbaseredirects\helpers\OptimizeHelper;
use barrelstrength\sproutbaseredirects\models\Settings;
use barrelstrength\sproutbaseredirects\SproutBaseRedirects;
use Craft;
use craft\base\Field;
use craft\elements\Asset;

use craft\models\Site;
use DateTime;
use craft\fields\PlainText;
use craft\fields\Assets;

/**
 * Class SproutBaseRedirectsVariable
 *
 * @package Craft
 */
class SproutBaseRedirectsVariable
{
    /**
     * @var SproutBaseRedirects
     */
    protected $plugin;

    /**
     * SproutBaseRedirectsVariable constructor.
     */
    public function __construct()
    {
        $this->plugin = Craft::$app->plugins->getPlugin('sprout-base-redirects');
    }

    /**
     * @return \craft\base\Model|null
     */
    public function getSettings()
    {
        return Craft::$app->plugins->getPlugin('sprout-base-redirects')->getSettings();
    }

    /**
     * @param $id
     *
     * @return \craft\base\ElementInterface|null
     */
    public function getElementById($id)
    {
        $element = Craft::$app->elements->getElementById($id);

        return $element != null ? $element : null;
    }


    /**
     * @param $string
     *
     * @return DateTime
     */
    public function getDate($string)
    {
        return new DateTime($string['date'], new \DateTimeZone(Craft::$app->getTimeZone()));
    }

    /**
     * @return mixed
     */
    public function getSiteIds()
    {
        $sites = Craft::$app->getSites()->getAllSites();

        return $sites;
    }
}
