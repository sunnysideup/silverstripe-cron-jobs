<?php

namespace Sunnysideup\CronJobs\Recipes;

use InvalidArgumentException;
use RuntimeException;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Convert;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;
use Sunnysideup\CronJobs\Traits\BaseMethodsForRecipesAndSteps;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
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


    private static $sys_load_maxes = [
        0.9,
        0.8,
        0.7,
    ];

    private static $ram_load_max = 0.8;

    private static $max_number_of_attempts = 12;

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

    public static function get_sys_load(): array
    {
        try {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $cores = (int) shell_exec('nproc');
                try {
                    $cores = (int) shell_exec('nproc');
                } catch (RuntimeException | InvalidArgumentException $e) {
                    $cores = 1;
                }
                return [
                    ($load[0] ?? 0) / $cores,
                    ($load[1] ?? 0) / $cores,
                    ($load[2] ?? 0) / $cores,
                ];
            }
        } catch (RuntimeException | InvalidArgumentException $e) {
            // do nothing
        }
        return [
            0,
            0,
            0,
        ];

    }

    public static function get_ram_usage(): float
    {
        try {
            $output = [];
            exec('free -m', $output);

            if (empty($output)) {
                return 0; // In case the command fails
            }

            foreach ($output as $line) {
                if (strpos($line, 'Mem:') === 0) {
                    $parts = preg_split('/\s+/', $line);
                    $total = (int) $parts[1]; // Total memory in MB
                    $available = (int) $parts[6]; // Available memory in MB

                    if ($total === 0) {
                        return 0; // Avoid division by zero
                    }

                    return ($available / $total);
                }
            }
        } catch (RuntimeException | InvalidArgumentException $e) {
            // do nothing
        }


        return 0;
    }

    public function canRunHoursOfTheDayClean(?bool $fill = false): array
    {
        $hoursOfTheDay = $this->canRunHoursOfTheDay();
        sort($hoursOfTheDay, SORT_NUMERIC);

        if (empty($hoursOfTheDay) && $fill) {
            // If no specific hours are defined, assume the job can run anytime
            $hoursOfTheDay = range(0, 23); // Full 24 hours
        }
        return $hoursOfTheDay;
    }

    /**
     * @var int
     */
    private static $max_execution_minutes_recipes = 240;

    /**
     * @var int
     */
    private static $max_execution_minutes_steps = 120;

    private static array $always_run_at_the_start_steps = [
        // CleanUpSiteUpdatesStep::class,
    ];

    private static array $always_run_at_the_end_steps = [];

    protected bool $ignoreLastRan = false;

    protected bool $ignoreTimeOfDay = false;

    protected bool $ignoreWhatElseIsRunning = false;

    protected ?SiteUpdate $myNotCompletePreviousRecipeLog = null;

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
        $expectedMin = $this->getExpectedMinimumEntriesPer24Hours();
        $expectedMax = $this->getExpectedMaximumEntriesPer24Hours();
        $expectedMinAdded = 0;
        $expectedMaxAdded = 0;
        $days = 0;
        while ($days < 100) {
            // add first
            $days++;
            $expectedMinAdded += $expectedMin;
            $expectedMaxAdded += $expectedMax;
            // expected for number of days
            $test = $this->getActualEntriesPer($days);
            // must hvae a min number of entries to be tested
            if ($expectedMinAdded > 1) {
                return $test >=  $expectedMinAdded;
            }
        }
        return false;
    }

    public function IsMeetingTargetNice(): DBBoolean
    {
        return DBBoolean::create_field('Boolean', $this->IsMeetingTarget());
    }


    public function getActualEntriesPer(?int $daysCovered = 1): int
    {
        $daysCovered = max(1, $daysCovered);
        $hoursBack = $daysCovered * 24;
        $minMultiplier = 0;
        $maxMultiplier = 1;
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

    public function canRunAdditionalCheck(): bool
    {
        return true;
    }

    protected $expectedMinimumOrMaximumEntriesPer24HoursCache = [];

    protected function getExpectedMinimumOrMaximumEntriesPer24Hours(string $minOrMax): float
    {
        if (empty($this->expectedMinimumOrMaximumEntriesPer24HoursCache)) {
            $hoursOfTheDay = $this->canRunHoursOfTheDayClean(true);

            // Sort the allowed hours to process them in order

            // Get the interval in hours between runs from the respective methods
            (float) $minHoursBetweenRuns = $this->getExpectedMinimumHoursBetweenRuns() + 0.001;
            (float) $maxHoursBetweenRuns = $this->getExpectedMaximumHoursBetweenRuns() + 0.001;
            // max to min on purpose.
            (float) $minRuns = 0;
            // min to max on purpose.
            (float) $maxRuns = 0;
            (int) $testHour = 0;
            (float) $runTimeMin = $minHoursBetweenRuns;
            (float) $runTimeMax = $maxHoursBetweenRuns;

            // Iterate through a 24-hour period to determine potential run times
            while ($testHour < 24 * 180) {
                (float) $endOfTestHour = $testHour + 1;
                // echo "
                // testHour: $testHour,
                // endOfTestHour: $endOfTestHour,
                // runTimeMin: $runTimeMin,
                // runTimeMax: $runTimeMax,
                // minRuns: $minRuns,
                // maxRuns: $maxRuns,
                // minHoursBetweenRuns: $minHoursBetweenRuns,
                // maxHoursBetweenRuns: $maxHoursBetweenRuns<br />";
                if (in_array($testHour % 24, $hoursOfTheDay)) {

                    // echo 'XXX';
                    if ($runTimeMin <  $testHour) {
                        $runTimeMin = $testHour;
                    }
                    $testA = (float) $runTimeMin >= (float) $testHour;
                    $testB = (float) (float) ($runTimeMin) < (float) ($testHour +  $maxHoursBetweenRuns) ;
                    $testC = (float) $runTimeMin < (float) $endOfTestHour;
                    while ($testA && ($testB || $testC)) {
                        // max to min on purpose.
                        // echo'A';
                        $minRuns++;
                        $runTimeMin += $maxHoursBetweenRuns;
                        if ((float) $runTimeMin > (float) $endOfTestHour) {
                            break;
                        }
                    }
                    if ($runTimeMax <  $testHour) {
                        $runTimeMax = $testHour;
                    }
                    $testA = (float) $runTimeMax >= (float) $testHour;
                    $testB = (float) (float) ($runTimeMax) < (float) ($testHour +  $minHoursBetweenRuns) ;
                    $testC = (float) $runTimeMax < (float) $endOfTestHour;
                    while ($testA && ($testB || $testC)) {
                        // echo'B';
                        $runTimeMax += $minHoursBetweenRuns;
                        $maxRuns++;
                        if ((float) $runTimeMax > (float) $endOfTestHour) {
                            break;
                        }

                    }

                }
                $testHour++;
            }
            // die('xxx');
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
            if ($obj->canRunCalculated(false) || $all) {
                $al->push($obj);
            }
        }

        return $al;
    }

    public function canRunCalculated(?bool $verbose = true, ?bool $returnReason = false): bool|string
    {
        $whyNot = '';
        if ($verbose) {
            $this->logAnything('Checking if we can run '.$this->getTitle());
        }
        // are updates running at all?
        if ($this->areUpdatesRunningAtAll()) {
            if ($this->canRun()) {
                if ($this->canRunAdditionalCheck()) {
                    if ($this->CanRunAtThisHour()) {
                        if ($this->IsThereEnoughTimeSinceLastRun()) {
                            if ($this->canRunNowBasedOnWhatElseIsRunning($verbose)) {
                                if ($this->canRunNowBasedOnSysLoad($verbose)) {
                                    return true;
                                } elseif ($verbose || $returnReason) {
                                    $whyNot = 'of system load';
                                }
                            } elseif ($verbose || $returnReason) {
                                $whyNot = 'something else is running';
                            }
                        } elseif ($verbose || $returnReason) {
                            $whyNot = 'there is not enough time since last run';
                        }
                    } elseif ($verbose || $returnReason) {
                        $whyNot = 'it is not the right time of day';
                    }
                } elseif ($verbose || $returnReason) {
                    $whyNot = 'canRunAdditionalCheck returns FALSE';
                }
            } elseif ($verbose || $returnReason) {
                $whyNot = 'because canRun returns FALSE';
            }
        } elseif ($verbose || $returnReason) {
            $whyNot = 'updated are not allowed right now is FALSE';
        }
        if ($verbose) {
            $this->logAnything('-- NO: ' . $whyNot);
        } elseif ($returnReason) {
            return 'Can not run right now because '.$whyNot;
        }
        return false;
    }

    protected function canRunAtThisHour(): bool
    {
        if ($this->ignoreTimeOfDay) {
            return true;
        }
        $hourOfDay = $this->getCurrentHour();
        $hoursOfTheDay = $this->canRunHoursOfTheDayClean();
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
    public function IsoverTimeSinceLastRun(): bool
    {
        return $this->overTimeSinceLastRun() > 0;
    }

    public function IsOverTimeSinceLastRunSymbol(): string
    {
        return $this->getSymbolForBoolean(! $this->IsoverTimeSinceLastRun());
    }

    public function getCurrentHour(): int
    {
        return (int) date('G');
    }

    public function overTimeSinceLastRun(): int
    {
        $canRunHoursOfTheDay = $this->canRunHoursOfTheDayClean();
        if (in_array($this->getCurrentHour(), $canRunHoursOfTheDay) || empty($canRunHoursOfTheDay)) {
            $lastRunTs = $this->LastCompleted(true);
            $nowTs = time();
            $diff = round(($nowTs - $lastRunTs) / 60);
            $over = $diff > $this->maxIntervalInMinutesBetweenRuns();
            if ($over > 0) {
                return $diff;
            }
        }
        return 0;
    }

    protected function canRunNowBasedOnWhatElseIsRunning(?bool $verbose = false): bool
    {
        // if ($verbose) {
        //     $this->logAnything(
        //         '-- Anything else running ? '. ($this->IsAnythingRunning($verbose) ? 'YES' : 'NO').'. '
        //     );
        //     $this->logAnything(
        //         '-- Can run at the same time as other recipes ? '. ($this->canRunAtTheSameTimeAsOtherRecipes() ? 'YES' : 'NO').'. '
        //     );
        //     $this->logAnything(
        //         '-- Another version is currently running ? '. ($this->AnotherVersionIsCurrentlyRunning() ? 'YES' : 'NO').'.'
        //     );

        // }
        if ($this->IsAnythingRunning($verbose) === false) {
            return true;
        } elseif ($this->canRunAtTheSameTimeAsOtherRecipes() || $this->ignoreWhatElseIsRunning) {
            // two of the same should never run
            return $this->AnotherVersionIsCurrentlyRunning() === false;
        }

        return false;
    }

    protected function canRunNowBasedOnSysLoad(?bool $verbose = false): bool
    {
        $outcome = self::can_run_now_based_on_sys_load();
        if ($outcome === true) {
            return true;
        }
        if ($verbose) {
            $this->logAnything('Can not run now because '.$outcome);
        }
        return false;
    }

    public static function can_run_now_based_on_sys_load(): bool|string
    {
        $sysLoad =  self::get_sys_load();
        $sysLoadMaxes = Config::inst()->get(static::class, 'sys_load_maxes');
        if ($sysLoad[0] < $sysLoadMaxes[0] && $sysLoad[1] < $sysLoadMaxes[1] && $sysLoad[2] < $sysLoadMaxes[2]) {
            $ramLoad =  self::get_ram_usage();
            if ($ramLoad < Config::inst()->get(static::class, 'ram_load_max')) {
                return true;
            } else {
                return 'RAM load is too high: '.$ramLoad;
            }
        } else {
            return 'System load is too high: '.implode(', ', $sysLoad);
        }
    }


    /**
     *
     * returns true on completion (not necessarily success)
     * @param mixed $request
     * @param mixed $verbose
     * @return bool
     */
    public function run(?HttpRequest $request = null, ?bool $verbose = true): bool
    {
        $this->logHeader('Start Recipe ' . $this->getType() . ' at ' . date('l jS \of F Y h:i:s A'));
        $errors = 0;
        $status = 'Completed';
        $notes = '';
        if ($this->canRunCalculated($verbose)) {
            $updateID = $this->startLog();
            register_shutdown_function(function () use ($updateID) {
                $this->fatalHandler($updateID);
            });
            /** @var null|SiteUpdate $this->myNotCompletePreviousRecipeLog */
            $this->myNotCompletePreviousRecipeLog = $this->LastRunIfNotCompletedLog();
            if ($this->myNotCompletePreviousRecipeLog) {
                $this->log->Attempts = $this->myNotCompletePreviousRecipeLog->Attempts + 1;
            }
            $this->log->write();
            if ($this->log->Attempts > 1) {
                $this->logAnything('This is attempt number: ' . $this->log->Attempts);
                if ($this->log->Attempts > $this->Config()->max_number_of_attempts) {
                    $this->logError('This is attempt number: ' . $this->log->Attempts. ' - stopping now', true);
                    $this->myNotCompletePreviousRecipeLog = null;
                }
            }
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
                    }
                    $this->recordTimeAndMemory();
                }
            }
            $this->stopLog($errors, $status, $notes);
            $this->logHeader('End ' . $this->getTitle());
            return true;
        }
        return false;

    }


    public function fatalHandler(?int $siteUpdateID = 0): void
    {

        $error = error_get_last();

        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR], true)) {
            if (! $this->log && $siteUpdateID) {
                $this->log = SiteUpdate::get()->byID($siteUpdateID);
            }
            $errfile = 'unknown file';
            $errline = 0;
            $errstr  = 'shutdown';
            $errno   = $error['type'] ?? 0;
            $errfile = $error['file'] ?? 'unknown file';
            $errline = $error['line'] ?? 0;
            $errstr  = $error['message'] ?? 'shutdown';
            $errorFormatted = "Error [$errno]: $errstr in $errfile on line $errline";
            $this->stopLog(1, 'NotCompleted', $errorFormatted);
            if ($this->log) {
                $this->log->logAnything('Fatal error: '.$errfile.' on line '.$errline.' with message '.$errstr);
            } else {
                $this->logAnything('Fatal error: '.$errfile.' on line '.$errline.' with message '.$errstr);
            }
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
            if ($obj->canRunCalculated(true)) {

                $this->logHeader('Starting ' . $obj->getTitle());

                $obj->startLog($updateID);
                $alreadyRan = false;
                if ($this->myNotCompletePreviousRecipeLog) {
                    $this->logAnything('Checking if this step was already started in a previous run of this recipe.');
                    if ($this->myNotCompletePreviousRecipeLog->hasCompletedStep($className)) {
                        $obj->stopLog(0, 'Skipped', 'This step was already completed in a previous run of this recipe.');
                        $alreadyRan = true;
                    } elseif ($this->myNotCompletePreviousRecipeLog->hasNotCompletedStep($className)) {
                        $obj->logAnything('This step was started in a previous run of this recipe, but did not complete.');
                        $myNotCompletePreviousRecipeStepLog = $obj->LastRunIfNotCompletedLog();
                        $log = $obj->getLog();
                        if ($myNotCompletePreviousRecipeStepLog) {
                            $log->Attempts = ((int) $myNotCompletePreviousRecipeStepLog->Attempts ?: 1) + 1;
                            $log->write();
                            $this->logAnything('This is now attempt '.$log->Attempts.' for the step.'.$log->ClassName.'_'.$log->ID);
                        } else {
                            $this->logAnything('Could not find the previous step log. for the step.'.$log->ClassName.'_'.$log->ID);
                        }
                        $obj->setLog($log);
                    } else {
                        $obj->logAnything('This step was not started in a previous run of this recipe.');
                    }
                } else {
                    $obj->logAnything('This step was not started in a previous run of this recipe.');
                }
                if ($alreadyRan === false) {
                    $errors = (int) $obj->run();
                    $obj->stopLog($errors);
                }
                $this->logHeader('--- Finished ' . $obj->getTitle());

                return $obj;
            } else {
                $this->logAnything('Not allowed to run: ' . $className . ' as a step');
                return null;
            }
        } else {
            $this->logAnything('Could not find: ' . $className . ' as a step', 'deleted');
            return null;
        }
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

    public function getProposedSteps(): array
    {
        $steps = $this->getSteps();
        foreach ($steps as $key => $step) {
            $singleton = Injector::inst()->get($step);
            if ($singleton->canRunCalculated(false) !== true) {
                unset($steps[$key]);
            }
        }
        return $steps;
    }
}
