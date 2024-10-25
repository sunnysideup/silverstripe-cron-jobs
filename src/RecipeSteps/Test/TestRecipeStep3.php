<?php

namespace Sunnysideup\CronJobs\RecipeSteps\Test;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;

class TestRecipeStep3 extends SiteUpdateRecipeStepBaseClass
{
    public function getDescription(): string
    {
        return 'Test Step 3';
    }

    public function run(): int
    {
        $this->logAnything('Test Step 3');
        $this->logAnything($this->log->ID . '_'. (string) $this->log->ClassName);
        $this->logAnything('Attempts: '.$this->log->Attempts);
        if ((int) $this->log->Attempts === (int) 1) {
            user_error('This is an error', E_USER_ERROR);
            die('This is a die. If you run it again, it should bypass this on attempt 2.');
        } else {
            return 0;
        }
    }


}
