<?php

namespace Sunnysideup\CronJobs\Recipes;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Convert;
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
use Sunnysideup\CronJobs\Api\Converters;
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

    abstract public function runEvenIfUpdatesAreStopped(): bool;
    /**
     * @var mixed[]
     */
    protected const STEPS = [];


    /**
     * @var int
     */
    private static $max_execution_minutes_recipes = 240;

    /**
     * @var int
     */
    private static $max_execution_minutes_steps = 120;

    private static array $always_run_at_the_start_steps = [
        MarkOldTasksAsError::class,
    ];

    private static array $always_run_at_the_end_steps = [];

    protected bool $ignoreLastRan = false;

    protected bool $ignoreTimeOfDay = false;

    protected bool $ignoreWhatElseIsRunning = false;

    public function setIgnoreAll(): static
    {
        $this->setIgnoreLastRan();
        $this->setIgnoreTimeOfDay();
        $this->setIgnoreWhatElseIsRunning();
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

    public function setIgnoreWhatElseIsRunning(): static
    {
        $this->ignoreWhatElseIsRunning = true;
        return $this;
    }

    public function getGroup(): string
    {
        return 'Recipe';
    }

    /**
     * to do - this does not work if olders logs are deleted!
     * @return bool
     */
    public function IsMeetingTarget(): bool
    {
        if ($this->overTimeSinceLastRun() > 0) {
            return false;
        }
        $expectedMin = $this->getExpectedMinimumEntriesPer24Hours();
        $expectedMax = $this->getExpectedMaximumEntriesPer24Hours();
        $multiplier = 1;
        while ($expectedMin > 0 && $expectedMin < 1) {
            // one week
            $multiplier++;
            $expectedMin = $expectedMin * $multiplier;
        }
        $expectedMax = $expectedMax * $multiplier;
        $test = $this->getActualEntriesPer(round($multiplier));
        return $test >= $expectedMin;
    }

    public function IsMeetingTargetNice(): DBBoolean
    {
        return DBBoolean::create_field('Boolean', $this->IsMeetingTarget());
    }


    public function getActualEntriesPer(?int $daysCovered = 1): int
    {
        $daysCovered = max(1, $daysCovered);
        $hoursBack = $daysCovered * 24;
        $minMultiplier = 1;
        $maxMultiplier = 2;
        if ($daysCovered > 2) {
            $minMultiplier = 0;
            $maxMultiplier = 1;
        }
        $last24Hours = $this->listOfLogsForThisRecipeOrStep()->filter([
            'Status' => 'Completed',
            'HasErrors' => false,
            'Created:GreaterThan' => date('Y-m-d H:i:s', strtotime('-'.($hoursBack * $maxMultiplier).' hours')),
            'Created:LessThanOrEqual' => date('Y-m-d H:i:s', strtotime('-'.($hoursBack * $minMultiplier).' hours')),
        ]);
        return (int) $last24Hours->count();
    }
    public function getExpectedMinimumEntriesPer24Hours(): float
    {
        // note that we turn min and max around here!
        return $this->getExpectedMinimumOrMaximumEntriesPer24Hours('min');
    }

    public function getExpectedMaximumEntriesPer24Hours(): float
    {
        return $this->getExpectedMinimumOrMaximumEntriesPer24Hours('max');
    }

    protected $expectedMinimumOrMaximumEntriesPer24HoursCache = [];

    protected function getExpectedMinimumOrMaximumEntriesPer24Hours(string $minOrMax): float
    {
        if (empty($this->expectedMinimumOrMaximumEntriesPer24HoursCache)) {
            $hoursOfTheDay = $this->canRunHoursOfTheDay();

            // Sort the allowed hours to process them in order
            sort($hoursOfTheDay);

            if (empty($hoursOfTheDay)) {
                // If no specific hours are defined, assume the job can run anytime
                $hoursOfTheDay = range(0, 23); // Full 24 hours
            }


            // Get the interval in hours between runs from the respective methods
            $minHoursBetweenRuns = $this->getExpectedMinimumHoursBetweenRuns();
            $maxHoursBetweenRuns = $this->getExpectedMaximumHoursBetweenRuns();
            // max to min on purpose.
            $minRuns = $maxHoursBetweenRuns;
            // min to max on purpose.
            $maxRuns = $minHoursBetweenRuns;
            $testHour = 0;
            $runTimeMin = 0;
            $runTimeMax = 0;

            // Iterate through a 24-hour period to determine potential run times
            while ($minRuns < 1 || $testHour < 24) {
                if (in_array($testHour, $hoursOfTheDay)) {
                    $endOfTestHour = $testHour + 1;
                    while ($runTimeMin < $endOfTestHour) {
                        if ($runTimeMin >= $testHour) {
                            $minRuns++;
                        }
                        // max to min on purpose.
                        $runTimeMin += $maxHoursBetweenRuns;
                    }

                    while ($runTimeMax < $endOfTestHour) {
                        if ($runTimeMax >= $testHour) {
                            $maxRuns++;
                        }
                        // min to max on purpose.
                        $runTimeMax += $minHoursBetweenRuns;
                    }
                }
                $testHour++;
            }

            // Normalize the runs to a per-24-hour scale (since we're iterating through each hour as a starting point)
            // return $testHour;
            $divider = $testHour / 24;
            $minRuns = $minRuns / $divider;
            $maxRuns = $maxRuns / $divider;

            $this->expectedMinimumOrMaximumEntriesPer24HoursCache = [
                'min' => $minRuns,
                'max' => $maxRuns,
            ];
        }

        return $this->expectedMinimumOrMaximumEntriesPer24HoursCache[$minOrMax];

    }

    public function getExpectedMinimumHoursBetweenRuns(): float
    {
        return $this->minIntervalInMinutesBetweenRuns() / 60;
    }

    public function getExpectedMaximumHoursBetweenRuns(): float
    {
        return $this->maxIntervalInMinutesBetweenRuns() / 60;
    }



    public function SubLinks(?bool $all = false): ?ArrayList
    {
        $al = ArrayList::create();
        foreach ($this->getSteps() as $className) {
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
        $diffInMinutes = round(($nowTs - $lastRunTs) / 60);
        // echo "diff: $diff\n";
        // echo "lastRunTs: $lastRunTs\n";
        // echo "now: $now\n";
        if ($diffInMinutes > $this->minIntervalInMinutesBetweenRuns()) {
            return true;
        }
        return false;
    }

    public function overTimeSinceLastRunNice(): string
    {
        return $this->secondsToTime($this->overTimeSinceLastRun() * 60);
    }
    public function overTimeSinceLastRun(): int
    {
        $lastRunTs = $this->LastCompleted(true);
        $nowTs = time();
        $diff = round(($nowTs - $lastRunTs) / 60);
        $over = $diff > $this->maxIntervalInMinutesBetweenRuns();
        if ($over > 0) {
            return $diff;
        }
        return 0;
    }

    public function canRunNowBasedOnWhatElseIsRunning(?bool $verbose = false): bool
    {
        if ($verbose) {
            $this->logAnything(
                '-- Anything else running ? '. ($this->IsAnythingRunning($verbose) ? 'YES' : 'NO').'. '.
                'Can run at the same time as other recipes ? '. ($this->canRunAtTheSameTimeAsOtherRecipes() ? 'YES' : 'NO').'. '.
                'Another version is currently running ? '. ($this->AnotherVersionIsCurrentlyRunning() ? 'YES' : 'NO').'.'
            );

        }
        if ($this->IsAnythingRunning(false) === false) {
            return true;
        } elseif ($this->canRunAtTheSameTimeAsOtherRecipes() || $this->ignoreWhatElseIsRunning) {
            // two of the same should never run
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
        WorkOutWhatToRunNext::stop_recipes_and_tasks_running_too_long();
        if ($this->canRunCalculated()) {
            $updateID = $this->startLog();
            $steps = $this->getSteps();
            foreach ($steps as $className) {
                $stepRunner = $this->runOneStep($className, $updateID);
                if ($stepRunner) {
                    if ($stepRunner->allowNextStepToRun() !== true) {
                        $errors = 1;
                        $status = 'Shortened';
                        $notes = 'This update recipe stopped early because a step prevented the next step from running.';
                        $log = $stepRunner->getLog();
                        $log->AllowedNextStep = false;
                        $log->write();
                        break;
                    } else {
                        $this->recordTimeAndMemory();
                    }
                }
            }
            $this->stopLog($errors, $status, $notes);
        }

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
        } else {
            $this->stopLog(1, 'NotCompleted', 'Unknown error');
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
            $this->logAnything('Not allowed to run: ' . $className . ' as a step');
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

    protected function secondsToTime(int $seconds): string
    {
        return Injector::inst()->get(Converters::class)->secondsToTime($seconds);
    }

}
