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
use Sunnysideup\CronJobs\Api\WorkOutWhatToRunNext;
use Sunnysideup\CronJobs\Model\SiteUpdateConfig;

abstract class SiteUpdateRecipeBaseClass
{
    use LogSuccessAndErrorsTrait;

    use BaseMethodsForRecipesAndSteps;

    use Configurable;

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

    public function getGroup(): string
    {
        return 'Recipe';
    }




    public function SubLinks(): ?ArrayList
    {
        $al = ArrayList::create();
        foreach (static::STEPS as $className) {
            $obj = Injector::inst()->get($className);
            if ($obj->canRun()) {
                $al->push($obj);
            }
        }

        return $al;
    }

    public function canRunCalculated(): bool
    {
        // are updates running at all?
        if($this->areUpdatesRunningAtAll()) {
            if ($this->canRun()) {
                if ($this->CanRunAtThisHour()) {
                    if ($this->IsThereEnoughTimeSinceLastRun()) {
                        if ($this->IsAnythingRunning($this) === false || $this->canRunAtTheSameTimeAsOtherRecipes()) {
                            return true;
                        } else {
                            $this->logAnything('Can not run ' . $this->getType() . ' because something else is running');
                        }
                    } else {
                        $this->logAnything('Can not run ' . $this->getType() . ' because there is not enough time since last run');
                    }
                } else {
                    $this->logAnything('Can not run ' . $this->getType() . ' because it is not the right time of day');
                }
            } else {
                $this->logAnything('Can not run ' . $this->getType() . ' because canRun is FALSE');
            }
        } else {
            $this->logAnything('Can not run ' . $this->getType() . ' because updated are not allowed right now is FALSE');
        }
        return false;
    }

    protected function canRunAtThisHour(): bool
    {
        $hourOfDay = date('H');
        $hoursOfTheDay = $this->canRunHoursOfTheDay();
        if(empty($hoursOfTheDay) || in_array($hourOfDay, $hoursOfTheDay)) {
            return true;
        }
        return false;
    }

    public function IsThereEnoughTimeSinceLastRun(): bool
    {
        $lastRunTs = $this->LastCompleted(true);
        $nowTs = time();
        $diff = round(($nowTs - $lastRunTs) / 60);
        // echo "diff: $diff\n";
        // echo "lastRunTs: $lastRunTs\n";
        // echo "now: $now\n";
        if($diff > $this->minIntervalInMinutesBetweenRuns() * 60) {
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
        if($over > 0) {
            return $over;
        }
        return 0;
    }

    public function run(?HttpRequest $request)
    {
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
        $this->logHeader('End Recipe ' . $this->getType());
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

    /**
     *
     * return list of steps to run
     * @return array
     */
    protected function getSteps(): array
    {
        $array = static::STEPS;
        foreach($this->Config()->get('always_run_at_the_start_steps') as $className) {
            if(! in_array($className, $array)) {
                array_unshift($array, $className);
            }
        }
        foreach($this->Config()->get('always_run_at_the_end_steps') as $className) {
            if(! in_array($className, $array)) {
                $array[] = $className;
            }
        }

        return $array;
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
