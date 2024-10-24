<?php

namespace Sunnysideup\CronJobs\RecipeSteps;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Traits\BaseMethodsForRecipesAndSteps;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DB;
use Sunnysideup\CronJobs\Traits\BaseMethodsForAllRunners;
use Sunnysideup\Flush\FlushNow;

abstract class SiteUpdateRecipeStepBaseClass
{
    use Configurable;

    use BaseMethodsForRecipesAndSteps;

    use LogSuccessAndErrorsTrait;

    use BaseMethodsForAllRunners;

    protected $debug = false;

    abstract public function run(): int;

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

    public function canRunCalculated(?bool $verbose = true): bool
    {
        // are updates running at all?
        if ($this->canRun()) {
            return true;
        } elseif ($verbose) {
            $this->logAnything('Can not run ' . $this->getType() . ' because canRun returned FALSE');
        }
        return false;
    }

    public function getProposedSteps(): array
    {
        return [];
    }
}
