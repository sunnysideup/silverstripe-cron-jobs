<?php

namespace Sunnysideup\CronJobs\RecipeSteps\Test;

use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;

class TestRecipeStep2 extends SiteUpdateRecipeStepBaseClass
{
    public function getDescription(): string
    {
        return 'Test Step 2: throw an error and restart.';
    }

    public function run(): int
    {
        return 0;
    }


}
