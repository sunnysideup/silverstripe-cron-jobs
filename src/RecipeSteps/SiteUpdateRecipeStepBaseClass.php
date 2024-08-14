<?php

namespace Sunnysideup\CronJobs\RecipeSteps;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Traits\BaseMethodsForRecipesAndSteps;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DB;
use Sunnysideup\Flush\FlushNow;

abstract class SiteUpdateRecipeStepBaseClass
{
    use BaseMethodsForRecipesAndSteps;

    use LogSuccessAndErrorsTrait;

    use Configurable;

    protected $debug = false;

    abstract public function run(): int;

    public function SubLinks(): ?ArrayList
    {
        return null;
    }

    /**
     * we assume that runners run successfull,
     * but some can return false.
     */
    public function allowNextStepToRun(): bool
    {
        return true;
    }

    public function getLogClassName(): string
    {
        return SiteUpdateStep::class;
    }

    public function getGroup(): string
    {
        return 'Step';
    }

    public function canRun(): bool
    {
        return true;
    }

    protected function getAction(): string
    {
        return 'runstep';
    }

    public function canRunCalculated(): bool
    {
        // are updates running at all?
        if ($this->canRun()) {
            if ($this->IsAnythingRunning($this) === false) {
                return true;
            } else {
                $this->logAnything('Can not run ' . $this->getType() . ' because something else is running');
            }
        } else {
            $this->logAnything('Can not run ' . $this->getType() . ' because canRun returned FALSE');
        }
        return false;
    }
}
