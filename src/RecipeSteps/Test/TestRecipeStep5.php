<?php

namespace Sunnysideup\CronJobs\RecipeSteps\Test;

use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;

class TestRecipeStep5 extends SiteUpdateRecipeStepBaseClass
{
    public function getDescription(): string
    {
        return 'Test Step 5';
    }

    public function run(): int
    {
        return 0;

    }


}
