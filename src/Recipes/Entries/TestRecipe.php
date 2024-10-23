<?php

namespace Sunnysideup\CronJobs\Recipes\Entries;

use SilverStripe\Control\Director;
use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataList;
use Sunnysideup\CronJobs\RecipeSteps\Test\TestRecipeStep1;
use Sunnysideup\CronJobs\RecipeSteps\Test\TestRecipeStep2;
use Sunnysideup\CronJobs\RecipeSteps\Test\TestRecipeStep3;
use Sunnysideup\CronJobs\RecipeSteps\Test\TestRecipeStep4;
use Sunnysideup\CronJobs\RecipeSteps\Test\TestRecipeStep5;
use Sunnysideup\CronJobs\RecipeSteps\Test\TestRecipeStep6;
use Sunnysideup\CronJobs\RecipeSteps\Test\TestRecipeStep7;

class TestRecipe extends SiteUpdateRecipeBaseClass
{
    /**
     * @var array<class-string<\Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass>>
     */
    public const STEPS = [
        TestRecipeStep1::class,
        TestRecipeStep2::class,
        TestRecipeStep3::class,
        TestRecipeStep4::class,
        TestRecipeStep5::class,
        TestRecipeStep6::class,
        TestRecipeStep7::class,

    ];

    public function getDescription(): string
    {
        return 'Test Update Recipe';
    }

    public function getType(): string
    {
        return 'Test';
    }

    public function canRun(): bool
    {
        return Director::isDev();
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

    protected function getForceRun(): bool
    {
        return true;
    }

    public function runEvenIfUpdatesAreStopped(): bool
    {
        return true;
    }
}
