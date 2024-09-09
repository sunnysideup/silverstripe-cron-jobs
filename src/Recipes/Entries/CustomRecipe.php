<?php

namespace Sunnysideup\CronJobs\Recipes\Entries;

use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataList;

class CustomRecipe extends SiteUpdateRecipeBaseClass
{
    public function getDescription(): string
    {
        return 'Custom / Manual Update Recipe';
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
        return [];
    }

    public function canRunAtTheSameTimeAsOtherRecipes(): bool
    {
        return true;
    }

    public function minIntervalInMinutesBetweenRuns(): int
    {
        return 0;
    }

    public function maxIntervalInMinutesBetweenRuns(): int
    {
        return 0;

    }

    public function run(?HTTPRequest $request)
    {
        parent::run($request);
    }

    /**
     * run the step and delete the "run next" instruction afterwards
     * @return SiteUpdateRecipeStepBaseClass|null
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

    protected function runEvenIfUpdatesAreStopped(): bool
    {
        return true;
    }

    public function getSteps(): array
    {
        $array = $this->getBaseStepList()
            ->column('RunnerClassName')
        ;
        $parentArrays = parent::getSteps();
        // add the parents to the start of the array
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
