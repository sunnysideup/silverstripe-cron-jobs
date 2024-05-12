<?php

namespace Sunnysideup\CronJobs\Recipes;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\RecipeTasks\SiteUpdateRecipeTaskBaseClass;
use Sunnysideup\CronJobs\RecipeTasks\Finalise\MarkOldTasksAsError;
use Sunnysideup\CronJobs\Traits\BaseClassTrait;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use Sunnysideup\CronJobs\Model\SiteUpdateConfig;
use Sunnysideup\Flush\FlushNow;

abstract class UpdateRecipe
{
    use FlushNow;
    use LogSuccessAndErrorsTrait;
    use BaseClassTrait;
    /**
     * @var mixed[]
     */
    protected const STEPS = [];

    /**
     * @var int
     */
    private const MAX_EXECUTION_MINUTES_STEPS = 60;

    /**
     * @var int
     */
    private const MAX_EXECUTION_MINUTES_RECIPES = 180;

    /**
     * @var int
     */
    private const MAX_QUEUE_TIME_IN_MINUTES = 15;

    public function getGroup(): string
    {
        return 'Recipe';
    }

    public static function get_recipes(): array
    {
        $classes = ClassInfo::subClassesFor(UpdateRecipe::class, false);
        $array = [];
        foreach ($classes as $class) {
            $obj = $class::inst();
            $array[$obj->getShortClassCode()] = $class;
        }

        return $array;
    }

    abstract public function getType(): string;

    abstract public function canRun(): bool;

    abstract protected function runEvenIfCMSIsStopped(): bool;
    abstract public function canRunHoursOfTheDay(): array;
    abstract public function maxIntervalInMinutesBetweenRuns(): int;
    abstract public function minIntervalInMinutesBetweenRuns(): int;


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

    protected function canRunCalculated()
    {
        if($this->updateCanRunAtAll()) {
            if ($this->canRun()) {
                if ($this->CanRunAtThisHour()) {
                    if ($this->IsThereEnoughTimeSinceLastRun()) {
                        if ($this->IsAnthingElseRunnning($this)) {
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
    }

    protected function canRunAtThisHour(): bool
    {
        $hourOfDay = date('H');
        $hoursOfTheDay = $this->canRunHoursOfTheDay();
        if(in_array($hourOfDay, $hoursOfTheDay)) {
            return true;
        }
        return false;
    }


    protected function isThereEnoughTimeSinceLastRun(): bool
    {
        $lastRunTs = $this->LastStartedTs();
        $now = time();
        $diff = $now - $lastRunTs;
        if($diff > $this->minIntervalInMinutesBetweenRuns() * 60) {
            return true;
        }
        return false;
    }

    public function run(?HttpRequest $request)
    {
        $this->flushNowLine();
        $this->flushNow('Start Recipe ' . $this->getType() . ' at ' . date('l jS \of F Y h:i:s A'), 'brown');
        $this->flushNowLine();
        $errors = 0;
        $status = 'Completed';
        $notes = '';
        $this->clearOldLogs($this->getForceRun());
        if ($this->canRun() && $this->updateCanRunAtAll()) {
            $ready = $this->IsAnthingElseRunnning($this);
            if ($ready) {
                $updateID = $this->startLog(0);
                $steps = $this->getSteps();
                foreach ($steps as $className) {
                    $obj = $this->runOneStep($className, $updateID);
                    if ($obj && !$obj->allowNextTaskToRun()) {
                        $errors = 1;
                        $status = 'Shortened';
                        $notes = 'This update recipe stopped early, most likely there was an issue retrieving data from the AR API or there was nothing to do.';

                        break;
                    }
                }
            } else {
                $updateID = $this->startLog(0);
                $status = 'Skipped';
                $notes .= 'Ran out of time';
            }
        } else {
            $updateID = $this->startLog(0);
            $status = 'Skipped';
            $notes .= 'Can not run right now.
                canRun = ' . ($this->canRun() ? 'TRUE' : 'FALSE') . ' and
                updateCanRunAtAll = ' . ($this->updateCanRunAtAll() ? 'TRUE' : 'FALSE');
        }

        $this->stopLog($errors, $status, $notes);
        $this->flushNowLine();
        $this->flushNow('End Recipe ' . $this->getType(), 'brown');
        $this->flushNowLine();
        $this->flushNowNewLine();
    }

    /**
     * @param int $updateID
     *
     * @return null|SiteUpdateRecipeTaskBaseClass
     */
    public function runOneStep(string $className, ?int $updateID = 0)
    {
        if (class_exists($className)) {
            $obj = $className::inst();
            if ($obj->canRun()) {
                $ready = $this->IsAnthingElseRunnning($obj);
                if ($ready) {
                    $obj->startLog($updateID);
                    $errors = $obj->run();
                    $obj->stopLog($errors);
                }

                return $obj;
            }

            return null;
        }

        $this->flushNow('Could not find: ' . $className . ' as a step', 'deleted');

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

    protected function getSteps(): array
    {
        $array = static::STEPS;
        array_unshift($array, MarkOldTasksAsError::class);

        return $array;
    }

    protected function clearOldLogs(?bool $clearAll = false)
    {
        $array = [
            SiteUpdate::class => self::MAX_EXECUTION_MINUTES_RECIPES,
            SiteUpdateStep::class => self::MAX_EXECUTION_MINUTES_STEPS,
        ];
        foreach ($array as $className => $minutes) {
            if (false === $clearAll) {
                $mustBeCreatedBeforeDate = date(
                    'Y-m-d H:i:s',
                    strtotime('-' . $minutes . ' minutes')
                );
                $filter = [
                    'Stopped' => false,
                    'Created:LessThan' => $mustBeCreatedBeforeDate,
                ];
                self::log_anything('Checking for ' . Injector::inst()->get($className)->i18n_plural_name() . ' started before ' . $mustBeCreatedBeforeDate . ' and marking them as NotCompleted.');
            } else {
                $filter = [
                    'Stopped' => false,
                ];
                self::log_anything('Checking for ' . $className . ' not STOPPED and marking them as NotCompleted.');
            }

            $logs = $className::get()->filter($filter);
            foreach ($logs as $log) {
                self::log_anything('Found: -- ' . $log->getTitle() . ' with ID ' . $log->ID . '  -- ... marking as NotCompleted.');
                $log->Status = 'NotCompleted';
                $log->Stopped = true;
                $log->write();
            }
        }
    }

    /**
     * put in holding pattern until nothing else is running.
     *
     * @param SiteUpdateRecipeTaskBaseClass|UpdateRecipe $obj
     */
    protected function IsAnthingElseRunnning($obj): bool
    {
        if (true === $obj->IsAnythingRunning()) {
            if (true === $obj->IsAnythingRunning()) {
                $whatElseIsRunning = '';
                $otherOnes = $obj->WhatElseIsRunning();
                foreach ($otherOnes as $otherOne) {
                    $whatElseIsRunning .= $otherOne->getTitle() . ' (' . $otherOne->ID . '), ';
                }

                $this->logAnything($obj->getTitle() . ' is on hold --- ' . $whatElseIsRunning . ' is still running');
                $this->clearOldLogs();
            }
        }

        return true;
    }

    protected function getAction(): string
    {
        return 'runrecipe';
    }



    protected function updateCanRunAtAll(): bool
    {
        return $this->runEvenIfCMSIsStopped() || false === (bool) SiteUpdateConfig::inst()->StopSiteUpdates;
    }
}
