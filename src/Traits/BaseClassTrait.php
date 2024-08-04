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

trait BaseClassTrait
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
            $runItNow = 'Or run it now by browsing to: <a href="/dev/tasks/SiteUpdateRun">dev/tasks/SiteUpdateRun</a>.<br />';
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
    protected function IsAnythingElseRunnningAndStopIfNeeded(SiteUpdateRecipeStepBaseClass|SiteUpdateRecipeBaseClass $obj): bool
    {
        if (true === $obj->IsAnythingRunning()) {
            $whatElseIsRunning = [];
            $otherOnes = $obj->WhatElseIsRunning();
            foreach ($otherOnes as $otherOne) {
                $whatElseIsRunning[] = $otherOne->getTitle() . ' (' . $otherOne->ID . '), ';
            }

            $this->logAnything($obj->getTitle() . ' is on hold --- ' . implode(', ', $whatElseIsRunning) . ' is/are still running');
            WorkOutWhatToRunNext::stop_recipes_and_tasks_running_too_long();
            // check again
            return $obj->IsAnythingRunning();
        }

        return false;
    }

    /**
     * we check if a Recipe or a Recipe Step is running
     */
    public function IsAnythingRunning(): bool
    {
        return (bool) $this->WhatElseIsRunning()->exists();
    }

    /**
     * list of other items running
     */
    public function WhatElseIsRunning(): DataList
    {
        /** @var SiteUpdate|SiteUpdateStep $className */
        $className = $this->getLogClassName();

        return $className::get()->filter(['Stopped' => false]);
    }

    public function Link(): string
    {
        /** @var SiteUpdatePage $page */
        $page = SiteUpdatePage::get()->first();
        $action = $this->getAction();

        return $page->Link($action . '/' . $this->getEscapedClassName() . '/');
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

    public function LastCompleted(?bool $asTs = false): string|int
    {
        /** @var SiteUpdate|SiteUpdateStep $className */
        $className = $this->getLogClassName();
        if ($className && class_exists($className)) {
            $obj = $className::get()
                ->filter(['RunnerClassName' => static::class, 'Status' => 'Completed'])
                ->first();
            if($asTs) {
                return $obj ? strtotime($obj->LastEdited) : 0;
            }
            return $obj ? DBField::create_field(DBDatetime::class, $obj->LastEdited)->Ago() : 'Never Ran Successfully';
        }

        return 'Error, could not find class '.$className;
    }

    protected function LastStartedTs(): int
    {
        /** @var SiteUpdate|SiteUpdateStep $className */
        $className = $this->getLogClassName();
        if ($className) {
            $log = $className::get()->sort('ID', 'DESC')->first();
            return $log ? strtotime($log->Created) : 0;
        }
        return 0;
    }

    public function HasErrors(): bool
    {
        /** @var SiteUpdate|SiteUpdateStep $className */
        $className = $this->getLogClassName();
        if ($className) {
            return $className::get()
                ->filter(['RunnerClassName' => static::class, 'Status' => ['Errors', 'NotCompleted']])
                ->exists();
        }

        return false;
    }

    public function CurrentlyRunning(): bool
    {
        /** @var SiteUpdate|SiteUpdateStep $className */
        $className = $this->getLogClassName();
        if ($className) {
            return $className::get()
                ->filter(['RunnerClassName' => static::class, 'Status' => 'Started'])
                ->exists();
        }

        return false;
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
        $this->log->Description = trim(strip_tags((string) $this->getDescription()));
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
                    'LastCompleted' => $obj->LastCompleted(),
                    'HasErrors' => $obj->HasErrors(),
                    'SubLinks' => $obj->SubLinks(),
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

    protected function getLogClassSingleton()
    {
        return Injector::inst()->get($this->getLogClassName());
    }

    protected function getEscapedClassName(): string
    {
        return str_replace('\\', '-', static::class);
    }

    public function logFilePath(): string
    {
        return Controller::join_links(
            $this->logFileFolderPath(),
            $this->getShortClassCode() . '_' . $this->ID . '-update.log'
        );

    }

    public function deleteAllFilesInFolder(?string $directory = '')
    {
        if(! $directory) {
            $directory = $this->logFileFolderPath();
        }
        if (!is_dir($directory)) {
            throw new InvalidArgumentException('The provided path is not a directory.');
        }

        $files = glob($directory . '/*', GLOB_MARK);

        foreach ($files as $file) {
            if (is_dir($file)) {
                $this->deleteAllFilesInFolder($file);
                if (!rmdir($file)) {
                    throw new RuntimeException('Failed to delete directory ' . $file);
                }
            } else {
                if (!unlink($file)) {
                    throw new RuntimeException('Failed to delete file ' . $file);
                }
            }
        }

    }

    protected function logFileFolderPath(): string
    {
        return Controller::join_links(
            Director::baseFolder(),
            Config::inst()->get(SiteUpdateConfig::class, 'log_file_folder'),
        );

    }

}
