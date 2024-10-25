<?php

namespace Sunnysideup\CronJobs\RecipeSteps\Test;

use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;

class TestRecipeStep1 extends SiteUpdateRecipeStepBaseClass
{
    public function getDescription(): string
    {
        return 'Test Step 1: sign of life for three minutes';
    }

    public function run(): int
    {
        $seconds = 0;
        while ($seconds < 3) {
            sleep(1);
            $seconds++;
            $this->logSignOfLife('Seconds passed: ' . $seconds);
        }
        return 0;
    }


}
