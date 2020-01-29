<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\validators;

use barrelstrength\sproutbaseredirects\enums\RedirectStatuses;
use Craft;
use yii\validators\Validator;

class StatusValidator extends Validator
{
    /**
     * @inheritdoc
     */
    public function validateAttribute($object, $attribute)
    {
        if (!in_array($object->$attribute, [RedirectStatuses::ON, RedirectStatuses::OFF], true)) {
            $this->addError($object, $attribute, Craft::t('sprout-base-redirects', 'The status must be either "ON" or "OFF".'));
        }
    }
}
