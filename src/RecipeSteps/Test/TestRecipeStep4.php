<?php

namespace Sunnysideup\CronJobs\RecipeSteps\Test;

use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;

class TestRecipeStep4 extends SiteUpdateRecipeStepBaseClass
{
    public function getDescription(): string
    {
        return 'Test Step 2';
    }

    public function run(): int
    {
        return 0;

    }


}
