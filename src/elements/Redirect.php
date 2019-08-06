<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\elements;

use barrelstrength\sproutbase\SproutBase;
use barrelstrength\sproutbaseredirects\elements\actions\ChangePermanentMethod;
use barrelstrength\sproutbaseredirects\elements\actions\ChangeTemporaryMethod;
use barrelstrength\sproutbaseredirects\enums\RedirectMethods;
use barrelstrength\sproutbaseredirects\SproutBaseRedirects;
use barrelstrength\sproutbaseredirects\elements\db\RedirectQuery;
use barrelstrength\sproutbaseredirects\records\Redirect as RedirectRecord;
use barrelstrength\sproutbaseredirects\elements\actions\SetStatus;
use barrelstrength\sproutredirects\SproutRedirects;
use barrelstrength\sproutseo\SproutSeo;
use Craft;
use craft\base\Plugin;
use craft\helpers\UrlHelper;
use craft\elements\actions\Delete;
use craft\elements\actions\Edit;
use craft\base\Element;
use craft\elements\db\ElementQueryInterface;
use yii\base\Exception;
use yii\base\Model;

/**
 * SproutBaseRedirects - Redirect element type
 *
 * @property string $absoluteNewUrl
 */
class Redirect extends Element
{
    /**
     * @var string
     */
    public $oldUrl;

    /**
     * @var string
     */
    public $newUrl;

    /**
     * @var int
     */
    public $method;

    /**
     * @var bool
     */
    public $regex = false;

    /**
     * @var int
     */
    public $count = 0;

    public function init()
    {
        $this->setScenario(Model::SCENARIO_DEFAULT);

        parent::init();
    }

    /**
     * Returns the element type name.
     *
     * @return string
     */
    public static function displayName(): string
    {
        return Craft::t('sprout-base-redirects', 'Sprout SEO Redirects');
    }

    /**
     * @inheritDoc IElementType::hasStatuses()
     *
     * @return bool
     */
    public static function hasStatuses(): bool
    {
        return true;
    }

    /**
     * @return bool
     */
    public static function isLocalized(): bool
    {
        return true;
    }

    /**
     * Returns whether the current user can edit the element.
     *
     * @return bool
     */
    public function getIsEditable(): bool
    {
        return true;
    }

    /**
     * Use the name as the string representation.
     *
     * @return string
     */
    public function __toString()
    {
        if ($this->oldUrl) {
            return (string)$this->oldUrl;
        }
        return (string)$this->id ?: static::class;
    }

    /**
     * @inheritdoc
     */
    public function getSupportedSites(): array
    {
        // limit to just the one site this element is set to so that we don't propagate when saving
        return [$this->siteId];
    }

    /**
     * Returns the element's CP edit URL.
     *
     * @return null|string
     * @throws \craft\errors\SiteNotFoundException
     * @throws \yii\base\InvalidConfigException
     */
    public function getCpEditUrl()
    {
        $pluginHandle = Craft::$app->request->getBodyParam('criteria.pluginHandle');

        $url = UrlHelper::cpUrl($pluginHandle.'/redirects/edit/'.$this->id);

        if (Craft::$app->getIsMultiSite() && $this->siteId != Craft::$app->getSites()->getCurrentSite()->id) {
            $url .= '/'.$this->getSite()->handle;
        }

        return $url;
    }

    /**
     * @inheritdoc
     *
     * @return RedirectQuery The newly created [[RedirectQuery]] instance.
     */
    public static function find(): ElementQueryInterface
    {
        return new RedirectQuery(static::class);
    }

    /**
     * Returns the attributes that can be shown/sorted by in table views.
     *
     * @param string|null $source
     *
     * @return array
     */
    public static function defineTableAttributes($source = null): array
    {
        $attributes = [
            'oldUrl' => Craft::t('sprout-base-redirects', 'Old Url'),
            'newUrl' => Craft::t('sprout-base-redirects', 'New Url'),
            'method' => Craft::t('sprout-base-redirects', 'Method'),
            'count' => Craft::t('sprout-base-redirects', 'Count'),
            'test' => Craft::t('sprout-base-redirects', 'Test')
        ];

        return $attributes;
    }

    /**
     * @inheritdoc
     */
    protected static function defineSortOptions(): array
    {
        $attributes = [
            'oldUrl' => Craft::t('sprout-base-redirects', 'Old Url'),
            'newUrl' => Craft::t('sprout-base-redirects', 'New Url'),
            'method' => Craft::t('sprout-base-redirects', 'Method'),
            'elements.dateCreated' => Craft::t('sprout-base-redirects', 'Date Created'),
            'elements.dateUpdated' => Craft::t('sprout-base-redirects', 'Date Updated'),
        ];

        return $attributes;
    }

    /**
     * Returns this element type's sources.
     *
     * @param string|null $context
     *
     * @return array
     */
    protected static function defineSources(string $context = null): array
    {
        $sources = [
            [
                'key' => '*',
                'label' => Craft::t('sprout-base-redirects', 'All redirects'),
                'structureId' => SproutBaseRedirects::$app->redirects->getStructureId(),
                'structureEditable' => true,
                'criteria' => [
                    'method' => [301, 302]
                ]
            ]
        ];

        $methods = SproutBaseRedirects::$app->redirects->getMethods();

        foreach ($methods as $code => $method) {

            $key = 'method:'.$code;

            $sources[] = [
                'key' => $key,
                'label' => $method,
                'criteria' => ['method' => $code],
                'structureId' => SproutBaseRedirects::$app->redirects->getStructureId(),
                'structureEditable' => true
            ];
        }

        return $sources;
    }

    /**
     * @inheritDoc
     *
     * @param string|null $source
     *
     * @return array|null
     */
    protected static function defineActions(string $source = null): array
    {
        $actions = [];

        // Set Status
        $actions[] = SetStatus::class;

        // Edit
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Edit::class,
            'label' => Craft::t('sprout-base-redirects', 'Edit Redirect'),
        ]);

        // Change Permanent Method
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => ChangePermanentMethod::class,
            'successMessage' => Craft::t('sprout-base-redirects', 'Redirects updated.'),
        ]);

        // Change Temporary Method
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => ChangeTemporaryMethod::class,
            'successMessage' => Craft::t('sprout-base-redirects', 'Redirects updated.'),
        ]);

        // Delete
        $actions[] = Craft::$app->getElements()->createAction([
            'type' => Delete::class,
            'confirmationMessage' => Craft::t('sprout-base-redirects', 'Are you sure you want to delete the selected redirects?'),
            'successMessage' => Craft::t('sprout-base-redirects', 'Redirects deleted.'),
        ]);

        return $actions;
    }

    public static function defineSearchableAttributes(): array
    {
        return ['oldUrl', 'newUrl', 'method', 'regex'];
    }

    /**
     * @param string $attribute
     *
     * @return string
     */
    protected function tableAttributeHtml(string $attribute): string
    {
        switch ($attribute) {
            case 'newUrl':

                return $this->newUrl ?? '/';

            case 'test':
                // no link for regex
                if ($this->regex) {
                    return ' - ';
                }
                // Send link for testing
                $site = Craft::$app->getSites()->getSiteById($this->siteId);

                if ($site === null) {
                    return ' - ';
                }

                $baseUrl = Craft::getAlias($site->getBaseUrl());
                $oldUrl = $baseUrl.$this->oldUrl;

                return "<a href='{$oldUrl}' target='_blank' class='go'>Test</a>";
        }

        return parent::tableAttributeHtml($attribute);
    }

    /**
     * Returns the HTML for an editor HUD for the given element.
     *
     * @return string
     * @throws Exception
     * @throws \Twig_Error_Loader
     */
    public function getEditorHtml(): string
    {
        $methodOptions = SproutBaseRedirects::$app->redirects->getMethods();

        $html = Craft::$app->view->renderTemplate('sprout-base-redirects/redirects/_editor', [
            'redirect' => $this,
            'methodOptions' => $methodOptions
        ]);

        // Everything else
        $html .= parent::getEditorHtml();

        return $html;
    }

    /**
     * @inheritdoc
     */
    public function beforeSave(bool $isNew): bool
    {
        // Set the structure ID for Element::attributes() and afterSave()
        $this->structureId = SproutBaseRedirects::$app->redirects->getStructureId();

        return parent::beforeSave($isNew);
    }

    /**
     * Update "oldUrl" and "newUrl" to starts with a "/"
     *
     */
    public function beforeValidate(): bool
    {
        if ($this->oldUrl && !$this->regex) {
            $this->oldUrl = SproutBaseRedirects::$app->redirects->removeSlash($this->oldUrl);
        }

        if ($this->newUrl) {
            $this->newUrl = SproutBaseRedirects::$app->redirects->removeSlash($this->newUrl);

            // In case the value was a backslash: /
            if (empty($this->newUrl)) {
                $this->newUrl = null;
            }
        } else {
            $this->newUrl = null;
        }

        return parent::beforeValidate();
    }

    /**
     * @inheritdoc
     * @throws Exception if reasons
     */
    public function afterSave(bool $isNew)
    {
        // Get the Redirect record
        if (!$isNew) {
            $record = RedirectRecord::findOne($this->id);

            if (!$record) {
                throw new Exception('Invalid Redirect ID: '.$this->id);
            }
        } else {
            $record = new RedirectRecord();
            $record->id = $this->id;
        }

        $record->oldUrl = $this->oldUrl;
        $record->newUrl = $this->newUrl;
        $record->method = $this->method;
        $record->regex = $this->regex;
        $record->count = $this->count;

        $record->save(false);

        $structureId = SproutBaseRedirects::$app->redirects->getStructureId();
        if ($isNew) {
            Craft::$app->structures->appendToRoot($structureId, $this);
        }

        parent::afterSave($isNew);
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        return [
            [['oldUrl'], 'required'],
            ['method', 'validateMethod'],
            ['method', 'validateEdition'],
            ['oldUrl', 'uniqueUrl']
        ];
    }

    /**
     * Add validation so a user can't save a 404 in "enabled" status
     *
     * @param $attribute
     */
    public function validateMethod($attribute)
    {
        if ($this->enabled && $this->$attribute == RedirectMethods::PageNotFound) {
            $this->addError($attribute, 'Cannot enable a 404 Redirect. Update Redirect method.');
        }
    }

    /**
     * Add validation for Sprout Redirects editions
     *
     * @param $attribute
     */
    public function validateEdition($attribute)
    {
        $sproutRedirectsIsPro = SproutBase::$app->settings->isEdition('sprout-redirects', SproutRedirects::EDITION_PRO);

        $sproutSeoIsPro = SproutBaseRedirects::$app->settings->isSproutSeoPro();

        if ((!$sproutSeoIsPro && !$sproutRedirectsIsPro) && (int)$this->method !== RedirectMethods::PageNotFound) {

            $count = SproutBaseRedirects::$app->redirects->getTotalNon404Redirects();

            if ($count >= 3) {
                $this->addError($attribute, 'Upgrade to PRO to manage additional redirect rules');
            }
        }
    }

    /**
     * @return string
     * @throws \yii\base\InvalidConfigException
     */
    public function getAbsoluteNewUrl(): string
    {
        $baseUrl = Craft::getAlias($this->getSite()->getBaseUrl());

        // @todo - remove ltrim after we update to saving and skipping beginning slashes
        $path = ltrim($this->newUrl, '/');

        return $baseUrl.$path;
    }

    /**
     * Add validation to unique oldUrl's
     *
     * @param $attribute
     */
    public function uniqueUrl($attribute)
    {
        $redirect = self::find()
            ->siteId($this->siteId)
            ->where(['oldUrl' => $this->$attribute])
            ->one();

        if ($redirect && $redirect->id != $this->id) {
            $this->addError($attribute, 'This url already exists.');
        }
    }
}
