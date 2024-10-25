<?php

namespace Sunnysideup\CronJobs\Recipes\Entries;

use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataList;
use Sunnysideup\CronJobs\RecipeSteps\Finalise\CleanUpSiteUpdatesStep;

class CleanUpSiteUpdatesRecipe extends SiteUpdateRecipeBaseClass
{
    public function getDescription(): string
    {
        return 'Clean up old recipes and steps';
    }

    public function getType(): string
    {
        return 'Cleanup';
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
        return 5;
    }

    public function maxIntervalInMinutesBetweenRuns(): int
    {
        return 10;
    }

    protected function getForceRun(): bool
    {
        return true;
    }

    public function runEvenIfUpdatesAreStopped(): bool
    {
        return true;
    }

    public function getSteps(): array
    {
        $array = [
            CleanUpSiteUpdatesStep::class,
        ];
        $parentArrays = parent::getSteps();
        // add the parents to the start of the array
        array_unshift($array, ...$parentArrays);

        return $array;
    }


}
