<?php
/**
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

namespace barrelstrength\sproutbaseredirects\jobs;

use barrelstrength\sproutbaseredirects\elements\Redirect;
use craft\queue\BaseJob;
use Craft;

use barrelstrength\sproutbaseredirects\SproutBaseRedirects;
use craft\queue\QueueInterface;
use Throwable;
use yii\queue\Queue;

/**
 * Delete404 job
 */
class Delete404 extends BaseJob
{
    public $siteId;
    public $idsToDelete;
    public $redirectIdToExclude;

    /**
     * Returns the default description for this job.
     *
     * @return string
     */
    protected function defaultDescription(): string
    {
        return Craft::t('sprout-base-redirects', 'Deleting oldest 404 redirects');
    }

    /**
     * @param QueueInterface|Queue $queue
     *
     * @return bool
     * @throws Throwable
     */
    public function execute($queue): bool
    {
        $totalSteps = count($this->idsToDelete);

        foreach ($this->idsToDelete as $key => $id) {
            $step = $key + 1;
            $this->setProgress($queue, $step / $totalSteps);

            $element = Craft::$app->elements->getElementById($id, Redirect::class, $this->siteId);

            if ($element && !Craft::$app->elements->deleteElement($element, true)) {
                SproutBaseRedirects::error('Unable to delete the 404 Redirect using ID:'.$id);
            }
        }

        return true;
    }
}