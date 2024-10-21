<?php

namespace Sunnysideup\CronJobs\Traits;

use InvalidArgumentException;
use RuntimeException;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBBoolean;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\ArrayData;
use Sunnysideup\CronJobs\Api\Converters;

trait BaseMethodsForRecipesAndSteps
{
    protected $log;

    protected $timeAtStart;

    protected $mySiteUpdateID = 0;

    public function __destruct()
    {
        if ($this->log) {
            if (false === $this->log->Stopped) {
                $this->stopLog(1, 'NotCompleted', 'Did not complete, as on destruction, it was not Stopped.');
            }
        }
    }

    public static function run_me(HTTPRequest $request)
    {
        $obj = self::inst();
        $recipeOrStep = 'Step';

        if ($obj instanceof SiteUpdateRecipeStepBaseClass) {
            // all set
        } elseif ($obj instanceof SiteUpdateRecipeBaseClass) {
            $recipeOrStep = 'Recipe';
        }

        $obj = SiteUpdateRunNext::create([
            'RecipeOrStep' => $recipeOrStep,
            'RunnerClassName' => get_class($obj),
        ]);
        $obj->write();
    }



    /**
     * put in holding pattern until nothing else is running.
     *
     * @param SiteUpdateRecipeStepBaseClass|SiteUpdateRecipeBaseClass $obj
     */
    protected function IsAnythingRunning(?bool $verbose = true): bool
    {
        if (Director::is_cli()) {
            $verbose = true;
        }
        $whatElseIsRunning = $this->whatElseIsRunning();
        if ($whatElseIsRunning->exists()) {
            $whatElseIsRunningArray = [];
            if ($verbose) {
                foreach ($whatElseIsRunning as $otherOne) {
                    $whatElseIsRunningArray[] = $otherOne->getTitle() . ' (' . $otherOne->ID . '), ';
                }
                $this->logAnything(implode(', ', $whatElseIsRunningArray) . ' --- is/are still running');
            }
            // check again
            return true;
        }

        return false;
    }


    protected function getLogClassSingleton()
    {
        return Injector::inst()->get($this->getLogClassName());
    }

    /**
     * list of other items running
     */
    protected function whatElseIsRunning(): DataList
    {
        $className = $this->getLogClassName();
        $logID = (int) $this->log?->ID ?: 0;
        return $className::get()->filter(['Stopped' => false])->exclude(['ID' => $logID]);
    }


    abstract public function getDescription(): string;

    public function getShortClassCode(): string
    {
        return strtolower($this->getType());
    }

    public function getType(): string
    {
        return ClassInfo::shortName(static::class);
    }

    public function CMSEditLink(): ?string
    {
        return $this->LastCompletedLog()?->CMSEditLink();
    }

    public function LastStarted(?bool $asTs = false): string|int
    {
        return $this->getLastStartedOrCompleted($asTs, true);
    }
    public function LastCompleted(?bool $asTs = false): string|int
    {
        return $this->getLastStartedOrCompleted($asTs, false);
    }

    public function LastCompletedNice(?bool $asTs = false): string|int
    {
        return 'Last completed '.$this->LastCompleted(false);
    }

    protected function getLastStartedOrCompleted(?bool $asTs = false, ?bool $startedRatherThanCompleted = false): string|int
    {
        $list = $this->listOfLogsForThisRecipeOrStep();
        if ($list && $list->exists()) {
            if ($startedRatherThanCompleted === false) {
                $list = $list?->exclude(['Status' => 'Started']);
            }
            $field = 'LastEdited';
            if ($startedRatherThanCompleted) {
                $field = 'Created';
            }
            $obj = $list->sort('ID', 'DESC')->first();
            if ($obj) {
                if ($asTs) {
                    return $obj ? strtotime($obj->$field) : 0;
                }
                return DBField::create_field(DBDatetime::class, $obj->$field)->Ago();
            }
        }
        if ($asTs) {
            return 0;
        } else {
            return 'Never Ran Successfully';
        }
    }

    public function listOfLogsForThisRecipeOrStep(): ?DataList
    {
        $className = $this->getLogClassName();
        if ($className && class_exists($className)) {
            return $className::get()
                ->filter(['RunnerClassName' => static::class]);
        }
        return null;
    }

    public function LastCompletedLog()
    {
        $list = $this->listOfLogsForThisRecipeOrStep();
        if ($list) {
            return $list
                ->excludeAny(['Status' => ['Started', 'Skipped']])
                ->sort(['ID' => 'DESC'])
                ->first();
        }
        return null;
    }

    public function LastRunHadErrors(): bool
    {
        $obj = $this->LastCompletedLog();
        if ($obj) {
            return $obj->Errors > 0;
        }

        return false;
    }

    public function LastRunHadErrorsSymbol(): string
    {
        return $this->getSymbolForBoolean(!$this->LastRunHadErrors());
    }


    public function LastRunHadErrorsNice(): DBBoolean
    {
        return DBBoolean::create_field('Boolean', $this->LastRunHadErrors());
    }

    public function HasHadErrors(): bool
    {
        $list = $this->listOfLogsForThisRecipeOrStep();
        if ($list) {
            return $list
                ->filter(['HasErrors' => true])
                ->exists();
        }

        return false;
    }

    public function IsMeetingTargetSymbol(): string
    {
        return $this->getSymbolForBoolean($this->IsMeetingTarget());
    }

    protected function getSymbolForBoolean(bool $boolean): string
    {
        return $boolean ? '✓' : '❌';
    }

    public function HasHadErrorsNice(): DBBoolean
    {
        return DBBoolean::create_field('Boolean', $this->HasHadErrors());
    }

    public function AnotherVersionIsCurrentlyRunning(): bool
    {
        return $this->IsCurrentlyRunning(true);
    }

    public function AnotherVersionIsCurrentlyRunningNice(): DBBoolean
    {
        return DBBoolean::create_field('Boolean', $this->AnotherVersionIsCurrentlyRunning());
    }

    public function IsCurrentlyRunning(?bool $excludeMe = false): bool
    {
        $list = $this->listOfLogsForThisRecipeOrStep();
        if ($list && $list->exists()) {
            $filter = ['Stopped' => false];
            if ($excludeMe && $this->log?->ID) {
                $filter = $filter + ['ID' => $this->log->ID];
            }
            return $list->filter($filter)->exists();
        }

        return false;
    }

    public function IsCurrentlyRunningNice(?bool $excludeMe = false): DBBoolean
    {
        return DBBoolean::create_field('Boolean', $this->IsCurrentlyRunning());
    }

    public function HoursOfTheDayNice(): string
    {
        if ($this instanceof SiteUpdateRecipeBaseClass) {
            $array = $this->canRunHoursOfTheDayClean();
            if (empty($array)) {
                return 'any time';
            }
            return $this->summariseHours($array);
        }
        return 'n/a';
    }

    protected function summariseHours(array $hours): string
    {
        sort($hours);
        $ranges = [];
        $start = $hours[0];
        $end = $hours[0];

        for ($i = 1; $i < count($hours); $i++) {
            if ($hours[$i] == $end + 1) {
                $end = $hours[$i];
            } else {
                $ranges[] = $start == $end ? sprintf('%02d:00', $start) : sprintf('%02d:00 - %02d:00', $start, $end + 1);
                $start = $hours[$i];
                $end = $hours[$i];
            }
        }

        $ranges[] = $start == $end ? sprintf('%02d:00', $start) : sprintf('%02d:00 - %02d:00', $start, $end + 1);

        return implode(', ', $ranges);
    }


    public function MinMinutesBetweenRunsNice(): string
    {
        if ($this instanceof SiteUpdateRecipeBaseClass) {
            return Injector::inst()->get(Converters::class)->MinutesToTime($this->minIntervalInMinutesBetweenRuns());
        }

        return 'n/a';
    }

    public function MaxMinutesBetweenRunsNice(): string
    {
        if ($this instanceof SiteUpdateRecipeBaseClass) {
            return Injector::inst()->get(Converters::class)->MinutesToTime($this->maxIntervalInMinutesBetweenRuns());
        }
        return 'n/a';
    }



    public function NumberOfLogs(): int
    {
        return $this->listOfLogsForThisRecipeOrStep()->count();
    }

    public function AverageTimeTaken(): int
    {
        return $this->aggregateTaken('avg', 'TimeTaken');
    }

    public function AverageTimeTakenNice(): string
    {
        return Injector::inst()->get(Converters::class)->SecondsToTime($this->AverageTimeTaken());
    }

    public function AverageMemoryTaken(): int
    {
        return $this->aggregateTaken('avg', 'MemoryTaken');
    }

    public function AverageSysLoadA(): string
    {
        return $this->aggregateTaken('avg', 'SysLoadA', 2).'%';
    }

    public function AverageSysLoadB(): string
    {
        return $this->aggregateTaken('avg', 'SysLoadB', 2).'%';
    }

    public function AverageSysLoadC(): string
    {
        return $this->aggregateTaken('avg', 'SysLoadC', 2).'%';
    }

    public function MaxTimeTaken(): int
    {
        return $this->aggregateTaken('max', 'TimeTaken');
    }

    public function MaxSysLoadA(): string
    {
        return $this->aggregateTaken('max', 'SysLoadA', 2).'%';
    }

    public function MaxSysLoadB(): string
    {
        return $this->aggregateTaken('max', 'SysLoadB', 2).'%';
    }

    public function MaxSysLoadC(): string
    {
        return $this->aggregateTaken('max', 'SysLoadC', 2).'%';
    }

    public function MaxTimeTakenNice(): string
    {
        return Injector::inst()->get(Converters::class)->SecondsToTime($this->MaxTimeTaken());
    }


    public function MaxMemoryTaken(): int
    {
        return $this->aggregateTaken('max', 'MemoryTaken');
    }

    protected function aggregateTaken(string $aggregateMethod, ?string $field = null, ?int $decimals = 0): int
    {
        $list = $this->listOfLogsForThisRecipeOrStep();
        if ($list && $list->exists()) {
            $list = $list->filter(['Status' => 'Completed', 'HasErrors' => false]);
            if ($list->exists()) {
                return round(($field ? $list->$aggregateMethod($field) : $list->$aggregateMethod()), $decimals);
            }
        }
        return 0;
    }

    public function XML_val(string $method, $arguments = [])
    {
        if (!is_array($arguments)) {
            $arguments = [$arguments];
        }

        return $this->{$method}(...$arguments);
    }

    public function hasValue($field, $arguments = null, $cache = true)
    {
        return (bool) $this->XML_val($field, $arguments);
    }

    public function startLog(?int $siteUpdateId = 0): int
    {
        $this->timeAtStart = microtime(true);

        $className = $this->getLogClassName();
        /** @var SiteUpdate|SiteUpdateStep $className */
        $this->log = $className::create();
        $this->log->Title = trim(strip_tags((string) $this->getTitle()));
        $this->log->Type = $this->getType();
        $this->log->Status = 'Started';
        $this->log->RunnerClassName = static::class;
        if ($siteUpdateId) {
            $this->log->SiteUpdateID = $siteUpdateId;
            $this->mySiteUpdateID = (int) $siteUpdateId;
        } elseif ($this->log instanceof SiteUpdate) {
            $this->mySiteUpdateID = (int) $this->log->ID;
            $this->log->NumberOfStepsExpectecToRun = count($this->getSteps());
        } else {
            user_error('No SiteUpdateID provided and this is not a SiteUpdate class.', E_USER_ERROR);
        }
        $id = $this->log->write();
        return $id;
    }


    public function recordTimeAndMemory(): ?int
    {
        $returnID = null;
        if ($this->log && $this->log->exists()) {
            $this->log->MemoryTaken = round(memory_get_peak_usage(true) / 1024 / 1024);
            $loadAverages = SiteUpdateRecipeBaseClass::get_sys_load();
            $this->log->SysLoadA = $loadAverages[0] ?? 0;
            $this->log->SysLoadB = $loadAverages[1] ?? 0;
            $this->log->SysLoadC = $loadAverages[2] ?? 0;
            $this->log->TimeTaken = round(microtime(true) - $this->timeAtStart);
            $returnID = $this->log->write();
        }
        return $returnID;
    }


    public function stopLog(?int $errors = 0, ?string $status = 'Completed', ?string $notes = ''): ?int
    {
        $returnID = null;
        if ($this->log && $this->log->exists()) {
            $this->recordTimeAndMemory();
            if (!$this->log->Stopped) {
                $this->log->Stopped = true;
                $this->log->Status = $status;
                $this->log->Errors = $errors;
                $this->log->Notes = $notes;
                $returnID = $this->log->write();
            }
        }
        if ('Errors' === $status) {
            $this->logError($notes, true);
        }

        return $returnID;
    }

    public function CanRunNice(): DBBoolean
    {
        return DBBoolean::create_field('Boolean', $this->CanRun());
    }

    public function CanRunCalculatedNice(): DBBoolean
    {
        return DBBoolean::create_field('Boolean', $this->CanRunCalculated(false));
    }

    public static function my_child_links(): ArrayList
    {
        $array = ClassInfo::subclassesFor(static::class, false);
        $al = new ArrayList();
        foreach ($array as $class) {
            $al->push($class::inst()->getKeyVarsAsArrayData());
        }

        return $al;
    }


    public function getKeyVarsAsArrayData(): ArrayData
    {
        if ($this instanceof SiteUpdateRecipeBaseClass) {
            $subLinksAsArrayList = new ArrayList();
            foreach ($this->SubLinks(true) as $subLink) {
                $subLinksAsArrayList->push($subLink->getKeyVarsAsArrayData());
            }
        } else {
            $subLinksAsArrayList = null;
        }
        // we need to list them here as the class is not viewable data.
        return new ArrayData(
            [
                'Title' => $this->getTitle(),
                'Link' => Director::absoluteURL($this->Link()),
                'CMSEditLink' => Director::absoluteURL($this->CMSEditLink() ?: '/admin/site-updates'),
                'Description' => trim($this->getDescription()),
                'CanRunNice' => $this->CanRunNice()->NiceAndColourfull(),
                'CanRunCalculated' => $this->CanRunCalculatedNice()->NiceAndColourfull(),
                'LastStarted' => $this->LastStarted(),
                'LastCompleted' => $this->LastCompleted(),
                'LastRunHadErrors' => $this->LastRunHadErrors(),
                'LastRunHadErrorsSymbol' => $this->LastRunHadErrorsSymbol(),
                'LastRunHadErrorsNice' => $this->LastRunHadErrorsNice()->NiceAndColourfullInvertedColours(),
                'HasHadErrorsNice' => $this->HasHadErrorsNice()->NiceAndColourfullInvertedColours(),
                'NumberOfLogs' => $this->NumberOfLogs(),
                'AverageTimeTakenNice' => $this->AverageTimeTakenNice(),
                'MaxTimeTakenNice' => $this->MaxTimeTakenNice(),
                'AverageMemoryTaken' => $this->AverageMemoryTaken(),
                'MaxMemoryTaken' => $this->MaxMemoryTaken(),
                'AverageSysLoadA' => $this->AverageSysLoadA(),
                'MaxSysLoadA' => $this->MaxSysLoadA(),
                'HoursOfTheDayNice' => $this->HoursOfTheDayNice(),
                'MinMinutesBetweenRunsNice' => $this->MinMinutesBetweenRunsNice(),
                'MaxMinutesBetweenRunsNice' => $this->MaxMinutesBetweenRunsNice(),
                'SubLinks' => $subLinksAsArrayList,
            ]
        );
    }


    public function getLog()
    {
        return $this->log;
    }



}
