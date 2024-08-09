<?php

namespace Sunnysideup\CronJobs\Traits;

use InvalidArgumentException;
use RuntimeException;
use SilverStripe\Control\Controller;
use Sunnysideup\CronJobs\Model\SiteUpdateConfig;
use Sunnysideup\CronJobs\SiteUpdatePage;
use Sunnysideup\CronJobs\Analysis\AnalysisBaseClass;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\ArrayData;
use Sunnysideup\CronJobs\Api\WorkOutWhatToRunNext;

trait BaseMethodsForRecipesAndSteps
{
    protected $log;

    protected $timeAtStart;

    protected $request;

    protected $mySiteUpdateID = 0;

    public function __destruct()
    {
        if ($this->log) {
            if (false === $this->log->Stopped) {
                $this->stopLog(1, 'Errors', 'Did not complete.');
            }
        }
    }

    public static function run_me(HTTPRequest $request)
    {
        $obj = self::inst();
        $recipeOrStep = 'Step';
        $isRecipe = false;
        if ($obj instanceof AnalysisBaseClass) {
            $obj->setRequest($request);

            return $obj->run($request);
        }

        if ($obj instanceof SiteUpdateRecipeStepBaseClass) {
            // all set
        } elseif ($obj instanceof SiteUpdateRecipeBaseClass) {
            $recipeOrStep = 'Recipe';
            $isRecipe = true;
        }

        $obj = SiteUpdateRunNext::create([
            'RecipeOrStep' => $recipeOrStep,
            'RunnerClassName' => get_class($obj),
        ]);
        $obj->write();

        $runItNow = '';
        if (false === $isRecipe) {
            $runItNow = 'Or run it now by browsing to: <a href="/dev/tasks/site-update-run">dev/tasks/site-update-run</a>.<br />';
        }

        return
            '<strong>' . $obj->getTitle() . '</strong> will run soon.<br />
            To run it straight away, please run (on the command line): <br />
            <pre>
            vendor/bin/sake dev/tasks/site-update-run
            </pre>
            ' . $runItNow . '
            <br />To stop it, please delete: <a href="' . $obj->CMSEditLink() . '">the update record</a>.
        ';
    }

    public static function inst()
    {
        return Injector::inst()->get(static::class);
    }

    /**
     * put in holding pattern until nothing else is running.
     *
     * @param SiteUpdateRecipeStepBaseClass|SiteUpdateRecipeBaseClass $obj
     */
    protected function IsAnythingRunning(null|SiteUpdateRecipeStepBaseClass|SiteUpdateRecipeBaseClass $obj = null): bool
    {
        $whatElseIsRunning = $this->whatElseIsRunning();
        if ($whatElseIsRunning->exists()) {
            $whatElseIsRunningArray = [];
            foreach ($whatElseIsRunning as $otherOne) {
                $whatElseIsRunningArray[] = $otherOne->getTitle() . ' (' . $otherOne->ID . '), ';
            }
            if($obj) {
                $this->logAnything($obj->getTitle() . ' is on hold --- ' . implode(', ', $whatElseIsRunningArray) . ' is/are still running');
            }
            // check again
            return true;
        }

        return false;
    }


    /**
     * list of other items running
     */
    protected function whatElseIsRunning(): DataList
    {
        /** @var SiteUpdate|SiteUpdateStep $className */
        $className = $this->getLogClassName();

        $logID = (int) $this->log?->ID ?: 0;
        return $className::get()->filter(['Stopped' => false])->exclude(['ID' => $logID]);
    }

    public function Link(): string
    {
        /** @var SiteUpdatePage $page */
        $page = SiteUpdatePage::get()->first();
        if($page) {
            $action = $this->getAction();

            return $page->Link($action . '/' . $this->getEscapedClassName() . '/');
        }
        return 'please-create-site-update-page';
    }

    public function Title(): string
    {
        return $this->getTitle();
    }

    public function getTitle(): string
    {
        $string = ClassInfo::shortName(static::class);

        return preg_replace('#(?<!\ )[A-Z]#', ' $0', $string);
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

    public function LastStarted(?bool $asTs = false): string|int
    {
        return $this->getLastStartedOrCompleted($asTs, true);
    }
    public function LastCompleted(?bool $asTs = false): string|int
    {
        return $this->getLastStartedOrCompleted($asTs, false);
    }

    protected function getLastStartedOrCompleted(?bool $asTs = false, ?bool $startedRatherThanCompleted = false): string|int
    {
        $list = $this->listOfLogsForThisRecipeOrStep();
        if($startedRatherThanCompleted) {
            // list = $list->filter(['Status' => 'Started']);
        } else {
            $list = $list?->exclude(['Status' => 'Started']);
        }
        if ($list && $list->exists()) {
            $field = 'LastEdited';
            if($startedRatherThanCompleted) {
                $field = 'Created';
            }
            $obj = $list->sort('ID', 'DESC')->first();
            if($obj) {
                if($asTs) {
                    return $obj ? strtotime($obj->$field) : 0;
                }
                return DBField::create_field(DBDatetime::class, $obj->$field)->Ago();
            }
        }
        if($asTs) {
            return 0;
        } else {
            return 'Never Ran Successfully';
        }
    }

    protected function listOfLogsForThisRecipeOrStep(): ?DataList
    {
        $className = $this->getLogClassName();
        if ($className && class_exists($className)) {
            return $className::get()
                ->filter(['RunnerClassName' => static::class]);
        }
        return null;
    }


    public function HasErrors(): bool
    {
        $list = $this->listOfLogsForThisRecipeOrStep();
        if ($list) {
            return $list
                ->filter(['Status' => ['Errors', 'NotCompleted']])
                ->exists();
        }

        return false;
    }

    public function CurrentlyRunning(): bool
    {
        $list = $this->listOfLogsForThisRecipeOrStep();
        if ($list) {
            return $list
                ->filter(['Status' => 'Started'])
                ->exists();
        }

        return false;
    }
    public function HoursOfTheDayNice(): string
    {
        if($this instanceof SiteUpdateRecipeBaseClass) {
            $array = $this->canRunHoursOfTheDay();
            if(empty($array)) {
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
        if($this instanceof SiteUpdateRecipeBaseClass) {
            return $this->minutesToTime($this->minIntervalInMinutesBetweenRuns());
        }

        return 'n/a';
    }

    public function MaxMinutesBetweenRunsNice(): string
    {
        if($this instanceof SiteUpdateRecipeBaseClass) {
            return $this->minutesToTime($this->maxIntervalInMinutesBetweenRuns());
        }
        return 'n/a';
    }

    protected function minutesToTime(int $minutes): string
    {
        if($minutes < 1) {
            return 'immediately';
        }
        if ($minutes < 60) {
            return $minutes . ' minutes';
        }

        $hours = round($minutes / 60);
        $remainingMinutes = $minutes % 60;

        if ($hours < 24) {
            return $remainingMinutes > 0
                ? "$hours hours $remainingMinutes minutes"
                : "$hours hours";
        }

        $days = round($hours / 24);
        $remainingHours = $hours % 24;

        return $remainingHours > 0
            ? "$days days $remainingHours hours"
            : "$days days ";
    }

    public function NumberOfLogs(): int
    {
        return $this->aggregateTaken('count');
    }

    public function AverageTimeTaken(): int
    {
        return $this->aggregateTaken('avg', 'TimeTaken');
    }

    public function AverageMemoryTaken(): int
    {
        return $this->aggregateTaken('avg', 'MemoryTaken');

    }

    public function MaxTimeTaken(): int
    {
        return $this->aggregateTaken('max', 'TimeTaken');
    }

    public function MaxMemoryTaken(): int
    {
        return $this->aggregateTaken('max', 'MemoryTaken');
    }

    protected function aggregateTaken(string $aggregateMethod, ?string $field = null): int
    {
        $list = $this->listOfLogsForThisRecipeOrStep();
        if ($list && $list->exists()) {
            $list = $list->filter(['Status' => 'Completed']);
            if ($list->exists()) {
                return round($field ? $list->$aggregateMethod($field) : $list->$aggregateMethod());
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
        } elseif ($this instanceof SiteUpdate) {
            $this->mySiteUpdateID = (int) $this->log->ID;
        }
        $id = $this->log->write();
        return $id;
    }

    public function stopLog(?int $errors = 0, ?string $status = 'Completed', ?string $notes = ''): ?int
    {
        $returnID = null;
        if ($this->log && $this->log->exists()) {
            if (!$this->log->Stopped) {
                $this->log->Stopped = true;
                $this->log->Status = $status;
                $this->log->Errors = $errors;
                $this->log->Notes = $notes;
                $this->log->MemoryTaken = round(memory_get_peak_usage(true) / 1024 / 1024);
                $this->log->TimeTaken = round(microtime(true) - $this->timeAtStart);
                $returnID = $this->log->write();
            }
        }
        if ('Errors' === $status) {
            $this->logError($notes, true);
        }

        return $returnID;
    }

    public static function my_child_links(): ArrayList
    {
        $array = ClassInfo::subclassesFor(static::class, false);
        $al = new ArrayList();
        foreach ($array as $class) {
            $obj = $class::inst();
            $arrayData = new ArrayData(
                [
                    'Title' => $obj->getTitle(),
                    'Link' => Director::absoluteURL($obj->Link()),
                    'Description' => trim($obj->getDescription()),
                    'LastStarted' => $obj->LastStarted(),
                    'LastCompleted' => $obj->LastCompleted(),
                    'HasErrors' => $obj->HasErrors(),
                    'SubLinks' => $obj->SubLinks(),
                    'NumberOfLogs' => $obj->NumberOfLogs(),
                    'AverageTimeTaken' => $obj->AverageTimeTaken(),
                    'AverageMemoryTaken' => $obj->AverageMemoryTaken(),
                    'HoursOfTheDayNice' => $obj->HoursOfTheDayNice(),
                    'MinMinutesBetweenRunsNice' => $obj->MinMinutesBetweenRunsNice(),
                    'MaxMinutesBetweenRunsNice' => $obj->MaxMinutesBetweenRunsNice(),
                    'MaxTimeTaken' => $obj->MaxTimeTaken(),
                    'MaxMemoryTaken' => $obj->MaxMemoryTaken(),
                ]
            );
            $al->push($arrayData);
        }

        return $al;
    }


    public function setRequest($request)
    {
        $this->request = $request;

        return $this;
    }

    protected function getRequest()
    {
        return $this->request;
    }

    public function getLog()
    {
        return $this->log;
    }

    protected function getLogClassSingleton()
    {
        return Injector::inst()->get($this->getLogClassName());
    }

    protected function getEscapedClassName(): string
    {
        return str_replace('\\', '-', static::class);
    }


}
