<?php

namespace Sunnysideup\CronJobs\Recipes;

use SilverStripe\Core\Config\Configurable;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;
use Sunnysideup\CronJobs\RecipeSteps\Finalise\MarkOldTasksAsError;
use Sunnysideup\CronJobs\Traits\BaseMethodsForRecipesAndSteps;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBBoolean;
use Sunnysideup\CronJobs\Api\WorkOutWhatToRunNext;
use Sunnysideup\CronJobs\Model\SiteUpdateConfig;
use Sunnysideup\CronJobs\Traits\BaseMethodsForAllRunners;

abstract class SiteUpdateRecipeBaseClass
{
    use Configurable;

    use LogSuccessAndErrorsTrait;

    use BaseMethodsForRecipesAndSteps;

    use BaseMethodsForAllRunners;


    abstract public function getType(): string;
    abstract public function getDescription(): string;

    abstract public function canRun(): bool;

    abstract public function canRunHoursOfTheDay(): array;
    abstract public function canRunAtTheSameTimeAsOtherRecipes(): bool;

    abstract public function minIntervalInMinutesBetweenRuns(): int;

    abstract public function maxIntervalInMinutesBetweenRuns(): int;

    abstract protected function runEvenIfUpdatesAreStopped(): bool;
    /**
     * @var mixed[]
     */
    protected const STEPS = [];


    /**
     * @var int
     */
    private static $max_execution_minutes_recipes = 180;

    /**
     * @var int
     */
    private static $max_execution_minutes_steps = 60;

    private static array $always_run_at_the_start_steps = [
        MarkOldTasksAsError::class,
    ];

    private static array $always_run_at_the_end_steps = [];

    protected bool $ignoreLastRan = false;

    protected bool $ignoreTimeOfDay = false;

    public function setIgnoreLastRanAndTimeOfDay(): static
    {
        $this->setIgnoreLastRan();
        $this->setIgnoreTimeOfDay();
        return $this;
    }

    public function setIgnoreLastRan(): static
    {
        $this->ignoreLastRan = true;
        return $this;
    }

    public function setIgnoreTimeOfDay(): static
    {
        $this->ignoreTimeOfDay = true;
        return $this;
    }

    public function getGroup(): string
    {
        return 'Recipe';
    }

    public function IsMeetingTarget(): bool
    {
        $expectedMin = $this->getExpectedMinimumEntriesPer24Hours();
        $expectedMax = $this->getExpectedMaximumEntriesPer24Hours();
        $multiplier = 1;
        if ($expectedMin < 1) {
            // one week
            $multiplier = $multiplier * 7;
            $expected = $expectedMin * $multiplier;
            if ($expected < 1) {
                // one month
                $multiplier = $multiplier * 4.3;
                $expected = $expectedMin * $multiplier;
                if ($expected < 1) {
                    // six months
                    $multiplier = $multiplier * 6;
                    $expected = $expectedMin * $multiplier;
                    if ($expected < 1) {
                        $multiplier = $multiplier * 2;
                        $expected = $expectedMin * $multiplier;
                    }
                }
            }
        }
        $expectedMax = $expectedMax * $multiplier;
        $test = $this->getActualEntriesPer(round($multiplier));
        return $test > $expectedMin && $test < $expectedMax;
    }

    public function IsMeetingTargetNice(): DBBoolean
    {
        return DBBoolean::create_field('Boolean', $this->IsMeetingTarget());
    }


    public function getActualEntriesPer(?int $daysBack = 1): int
    {
        $daysBack = max(1, $daysBack);
        $hoursBack = $daysBack * 24;
        $minMultiplier = 1;
        $maxMultiplier = 2;
        if ($daysBack > 2) {
            $minMultiplier = 0;
            $maxMultiplier = 1;
        }
        $last24Hours = SiteUpdate::get()->filter([
            'Status' => 'Completed',
            'Created:GreaterThan' => date('Y-m-d H:i:s', strtotime('-'.($hoursBack * $maxMultiplier).' hours')),
            'Created:LessThanOrEqual' => date('Y-m-d H:i:s', strtotime('-'.($hoursBack * $minMultiplier).' hours')),
        ]);
        return (int) $last24Hours->count();
    }
    public function getExpectedMinimumEntriesPer24Hours(): float
    {
        return $this->getExpectedMinimumOrMaximumEntriesPer24Hours('getExpectedMinimumEntriesPerHour');
    }

    public function getExpectedMaximumEntriesPer24Hours(): float
    {
        return $this->getExpectedMinimumOrMaximumEntriesPer24Hours('getExpectedMaximumEntriesPerHour');
    }


    protected function getExpectedMinimumOrMaximumEntriesPer24Hours(string $methodName): float
    {
        $hoursOfTheDay = $this->canRunHoursOfTheDay();
        $sum = 0;
        for ($i = 0; $i < 24; $i++) {
            if (in_array($i, $hoursOfTheDay) || count($hoursOfTheDay) === 0) {
                $sum += $this->$methodName();
            }
        }
        return $sum;
    }

    public function getExpectedMinimumEntriesPerHour(): float
    {
        return 60 / $this->maxIntervalInMinutesBetweenRuns();
    }

    public function getExpectedMaximumEntriesPerHour(): float
    {
        return 60 / $this->minIntervalInMinutesBetweenRuns();
    }



    public function SubLinks(?bool $all = false): ?ArrayList
    {
        $al = ArrayList::create();
        foreach (static::STEPS as $className) {
            $obj = Injector::inst()->get($className);
            if ($obj->canRun() || $all) {
                $al->push($obj);
            }
        }

        return $al;
    }

    public function canRunCalculated(?bool $verbose = true): bool
    {
        // are updates running at all?
        if ($this->areUpdatesRunningAtAll()) {
            if ($this->canRun()) {
                if ($this->CanRunAtThisHour()) {
                    if ($this->IsThereEnoughTimeSinceLastRun()) {
                        if ($this->canRunNowBasedOnWhatElseIsRunning($verbose)) {
                            return true;
                        } elseif ($verbose) {
                            $this->logAnything('Can not run ' . $this->getType() . ' because something else is running');
                        }
                    } elseif ($verbose) {
                        $this->logAnything('Can not run ' . $this->getType() . ' because there is not enough time since last run');
                    }
                } elseif ($verbose) {
                    $this->logAnything('Can not run ' . $this->getType() . ' because it is not the right time of day');
                }
            } elseif ($verbose) {
                $this->logAnything('Can not run ' . $this->getType() . ' because canRun is FALSE');
            }
        } elseif ($verbose) {
            $this->logAnything('Can not run ' . $this->getType() . ' because updated are not allowed right now is FALSE');
        }
        return false;
    }

    protected function canRunAtThisHour(): bool
    {
        if ($this->ignoreTimeOfDay) {
            return true;
        }
        $hourOfDay = date('H');
        $hoursOfTheDay = $this->canRunHoursOfTheDay();
        if (empty($hoursOfTheDay) || in_array($hourOfDay, $hoursOfTheDay)) {
            return true;
        }
        return false;
    }

    public function IsThereEnoughTimeSinceLastRun(): bool
    {
        if ($this->ignoreLastRan) {
            return true;
        }

        $lastRunTs = $this->LastCompleted(true);
        $nowTs = time();
        $diff = round(($nowTs - $lastRunTs) / 60);
        // echo "diff: $diff\n";
        // echo "lastRunTs: $lastRunTs\n";
        // echo "now: $now\n";
        if ($diff > $this->minIntervalInMinutesBetweenRuns() * 60) {
            return true;
        }
        return false;
    }

    public function overTimeSinceLastRun(): int
    {
        $lastRunTs = $this->LastCompleted(true);
        $nowTs = time();
        $diff = round(($nowTs - $lastRunTs) / 60);
        $over = $diff > $this->maxIntervalInMinutesBetweenRuns();
        if ($over > 0) {
            return $over;
        }
        return 0;
    }

    public function canRunNowBasedOnWhatElseIsRunning(?bool $verbose = false): bool
    {
        if ($verbose) {
            $this->logAnything(
                'Anything else running ? '. ($this->IsAnythingRunning($this, true) ? 'YES' : 'NO').'. '.
                'Can run at the same time as other recipes ? '. ($this->canRunAtTheSameTimeAsOtherRecipes() ? 'YES' : 'NO').'. '.
                'Another version is currently running ? '. ($this->AnotherVersionIsCurrentlyRunning() ? 'YES' : 'NO').'.'
            );

        }
        if ($this->IsAnythingRunning($this, false) === false) {
            return true;
        }

        if ($this->canRunAtTheSameTimeAsOtherRecipes()) {
            return $this->AnotherVersionIsCurrentlyRunning() === false;
        }

        return false;
    }


    public function run(?HttpRequest $request)
    {
        register_shutdown_function([$this, 'fatalHandler']);
        $this->logHeader('Start Recipe ' . $this->getType() . ' at ' . date('l jS \of F Y h:i:s A'));
        $errors = 0;
        $status = 'Completed';
        $notes = '';
        WorkOutWhatToRunNext::stop_recipes_and_tasks_running_too_long($this->getForceRun());
        $updateID = $this->startLog();
        if ($this->canRunCalculated()) {
            $steps = $this->getSteps();
            foreach ($steps as $className) {
                $stepRunner = $this->runOneStep($className, $updateID);
                if ($stepRunner && !$stepRunner->allowNextStepToRun()) {
                    $errors = 1;
                    $status = 'Shortened';
                    $notes = 'This update recipe stopped early because a step prevented the next step from running.';
                    $log = $stepRunner->getLog();
                    $log->AllowedNextStep = false;
                    $log->write();
                    break;
                }
            }
        } else {
            $status = 'Skipped';
        }

        $this->stopLog($errors, $status, $notes);
        $this->logHeader('End ' . $this->getTitle());
    }

    public function fatalHandler(): void
    {
        $errno   = E_CORE_ERROR;
        $errfile = 'unknown file';
        $errline = 0;
        $errstr  = 'shutdown';

        $error = error_get_last();

        if ($error !== null) {
            $errno   = $error['type'] ?? 0;
            $errfile = $error['file'] ?? 'unknown file';
            $errline = $error['line'] ?? 0;
            $errstr  = $error['message'] ?? 'shutdown';
            $errorFormatted = "Error [$errno]: $errstr in $errfile on line $errline";
            $this->stopLog(1, 'NotCompleted', $errorFormatted);
        }
    }

    /**
     * @param int $updateID
     *
     * @return null|SiteUpdateRecipeStepBaseClass
     */
    public function runOneStep(string $className, ?int $updateID = 0)
    {
        if (class_exists($className)) {
            $obj = $className::inst();
            if ($obj->canRunCalculated()) {
                $obj->startLog($updateID);
                $errors = (int) $obj->run();
                $obj->stopLog($errors);

                return $obj;
            }

            return null;
        }

        $this->logAnything('Could not find: ' . $className . ' as a step', 'deleted');

        return null;

    }

    public function getLogClassName(): string
    {
        return SiteUpdate::class;
    }

    protected function getForceRun(): bool
    {
        return false;
    }

    protected $steps = [];

    /**
     *
     * return list of steps to run
     * @return array
     */
    public function getSteps(): array
    {
        if (empty($this->steps)) {
            $array = static::STEPS;
            foreach ($this->Config()->get('always_run_at_the_start_steps') as $className) {
                if (! in_array($className, $array)) {
                    array_unshift($array, $className);
                }
            }
            foreach ($this->Config()->get('always_run_at_the_end_steps') as $className) {
                if (! in_array($className, $array)) {
                    $array[] = $className;
                }
            }

            $this->steps = $array;
        }
        return $this->steps;
    }


    protected function getAction(): string
    {
        return 'runrecipe';
    }

    protected function areUpdatesRunningAtAll(): bool
    {
        return $this->runEvenIfUpdatesAreStopped() || false === (bool) SiteUpdateConfig::inst()->StopSiteUpdates;
    }

}
