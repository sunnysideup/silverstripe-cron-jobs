<?php

declare(strict_types=1);

namespace Sunnysideup\CronJobs\RecipeSteps;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Traits\BaseMethodsForRecipesAndSteps;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use SilverStripe\Core\Config\Configurable;
use Sunnysideup\CronJobs\Traits\BaseMethodsForAllRunners;

abstract class SiteUpdateRecipeStepBaseClass
{
    use Configurable;

    use BaseMethodsForRecipesAndSteps;

    use LogSuccessAndErrorsTrait;

    use BaseMethodsForAllRunners;

    public const STOP_ERROR_RESPONSE = -1;

    protected $debug = false;

    protected static bool $hasHadStopErrorResponse = false;

    public static function has_had_stop_error_response(): bool
    {
        return self::$hasHadStopErrorResponse;
    }

    abstract public function run(): int;

    /**
     * we assume that runners run successfull,
     * but some can return false.
     */
    public function allowNextStepToRun(): bool
    {
        return !self::$hasHadStopErrorResponse;
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

    public function canRunCalculated(?bool $verbose = true, ?bool $returnReason = false): bool|string
    {
        // are updates running at all?
        if ($this->canRun()) {
            return true;
        } elseif ($verbose) {
            $this->logAnything('Can not run ' . $this->getType() . ' because canRun returned FALSE');
        }

        if ($returnReason) {
            return 'canRun returned FALSE';
        }

        return false;
    }

    public function getProposedSteps(): array
    {
        return [];
    }

    protected function stopError(string $message): int
    {
        $this->logError($message, true);
        self::$hasHadStopErrorResponse = true;
        return self::STOP_ERROR_RESPONSE;
    }
}
