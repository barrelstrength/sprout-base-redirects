<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\elements\actions;

use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbaseredirects\elements\Redirect;
use barrelstrength\sproutbaseredirects\models\Settings as RedirectsSettingsModel;
use Craft;
use craft\base\ElementAction;
use craft\elements\db\ElementQueryInterface;
use Throwable;

/**
 * @property string $triggerLabel
 */
class ExcludeUrl extends ElementAction
{
    /**
     * @var string|null The confirmation message that should be shown before the elements get deleted
     */
    public $confirmationMessage;

    /**
     * @var string|null The message that should be shown after the elements get deleted
     */
    public $successMessage;

    /**
     * @inheritdoc
     */
    public function getTriggerLabel(): string
    {
        return Craft::t('sprout-base-redirects', 'Add to Excluded URLs');
    }

    /**
     * @inheritdoc
     */
    public function getConfirmationMessage()
    {
        return $this->confirmationMessage;
    }

    /**
     * @param ElementQueryInterface $query
     *
     * @return bool
     * @throws \Exception
     * @throws Throwable
     */
    public function performAction(ElementQueryInterface $query): bool
    {
        /** @var RedirectsSettingsModel $redirectSettings */
        $redirectSettings = SproutBase::$app->settings->getBaseSettings(RedirectsSettingsModel::class);

        /** @var Redirect[] $redirects */
        $redirects = $query->all();

        $transaction = Craft::$app->db->beginTransaction();

        try {
            foreach ($redirects as $redirect) {
                $oldUrl = $redirect->oldUrl;

                // Append the selected Old URL to the Excluded URL Pattern settings array
                $redirectSettings->excludedUrlPatterns .= PHP_EOL.$oldUrl;

                Craft::$app->elements->deleteElement($redirect, true);
            }

            SproutBase::$app->settings->saveBaseSettings($redirectSettings->getAttributes(), RedirectsSettingsModel::class);

            $transaction->commit();

            Craft::info('Form Saved.', __METHOD__);
        } catch (Throwable $e) {
            Craft::error('Unable to save form: '.$e->getMessage(), __METHOD__);
            $transaction->rollBack();
        }


        $this->setMessage(Craft::t('sprout-base-redirects', 'Added to Excluded URL Patterns setting.'));

        return true;
    }
}
