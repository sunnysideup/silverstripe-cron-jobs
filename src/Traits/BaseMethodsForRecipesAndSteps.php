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
        $whatIsRunning = $this->WhatIsRunning();
        if ($whatIsRunning->exists()) {
            $whatIsRunningArray = [];
            foreach ($whatIsRunning as $otherOne) {
                $whatIsRunningArray[] = $otherOne->getTitle() . ' (' . $otherOne->ID . '), ';
            }
            if($obj) {
                $this->logAnything($obj->getTitle() . ' is on hold --- ' . implode(', ', $whatIsRunningArray) . ' is/are still running');
            }
            // check again
            return true;
        }

        return false;
    }


    /**
     * list of other items running
     */
    protected function WhatIsRunning(): DataList
    {
        /** @var SiteUpdate|SiteUpdateStep $className */
        $className = $this->getLogClassName();

        return $className::get()->filter(['Stopped' => false]);
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
        if ($list) {
            $field = 'LastEdited';
            if($startedRatherThanCompleted) {
                $field = 'Created';
            }
            $obj = $list->sort('ID', 'DESC')->first();
            if($asTs) {
                return $obj ? strtotime($obj->$field) : 0;
            }
            return DBField::create_field(DBDatetime::class, $obj->$field)->Ago();
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
                ->filter(['RunnerClassName' => static::class, 'Status' => 'Completed']);
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

    public function NumberOfLogs(): int
    {
        return $this->aggregateTaken('COUNT', 'ID');
    }

    public function AverageTimeTaken(): int
    {
        return $this->aggregateTaken('AVG', 'TimeTaken');
    }

    public function AverageMemoryTaken(): int
    {
        return $this->aggregateTaken('AVG', 'MemoryTaken');

    }

    public function MaxTimeTaken(): int
    {
        return $this->aggregateTaken('MAX', 'TimeTaken');
    }

    public function MaxMemoryTaken(): int
    {
        return $this->aggregateTaken('MAX', 'MemoryTaken');
    }

    protected function aggregateTaken(string $aggregateType, string $field): int
    {
        $list = $this->listOfLogsForThisRecipeOrStep();
        if($list) {
            return $list->aggregate($aggregateType.'("' . $field . '")');
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
        LogSuccessAndErrorsTrait::set_current_log_file_object($this->log);
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
        LogSuccessAndErrorsTrait::set_current_log_file_object(null);

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
                    'NumberOfLogs' => $obj->CountOfLogs(),
                    'AverageTimeTaken' => $obj->AverageTimeTaken(),
                    'AverageMemoryTaken' => $obj->AverageMemoryTaken(),
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
