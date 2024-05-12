<?php

namespace Sunnysideup\CronJobs\Recipes\Entries;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Recipes\UpdateRecipe;
use Sunnysideup\CronJobs\RecipeTasks\SiteUpdateRecipeTaskBaseClass;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataList;

class CustomRecipe extends UpdateRecipe
{
    public function getDescription(): string
    {
        return 'Custom / Manual set steps';
    }

    public function getType(): string
    {
        return 'Custom';
    }

    public function canRun(): bool
    {
        return true;
    }

    public function canRunHoursOfTheDay(): array
    {
        return [0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23];

    }
    public function minIntervalInMinutesBetweenRuns(): int
    {
        return 0;
    }

    public function maxIntervalInMinutesBetweenRuns(): int
    {
        return 86400 * 999999;

    }

    public function run(?HTTPRequest $request)
    {
        parent::run($request);
    }

    /**
     * @return SiteUpdateRecipeTaskBaseClass
     */
    public function runOneStep(string $className, ?int $updateID = 0)
    {
        $step = parent::runOneStep($className, $updateID);

        $runNextObject = $this->getBaseStepList()
            ->filter(['RunnerClassName' => $className])
            ->first()
        ;
        if ($runNextObject) {
            $runNextObject->delete();
        }

        return $step;
    }

    protected function getForceRun(): bool
    {
        return true;
    }

    protected function runEvenIfCMSIsStopped(): bool
    {
        return true;
    }

    protected function getSteps(): array
    {
        $array = $this->getBaseStepList()
            ->column('RunnerClassName')
        ;
        $parentArrays = parent::getSteps();
        array_unshift($array, ...$parentArrays);

        return $array;
    }

    protected function getBaseStepList(): DataList
    {
        return SiteUpdateRunNext::get()
            ->sort(['ID' => 'DESC'])
            ->filter(['RecipeOrStep' => 'Step'])
        ;
    }
}
