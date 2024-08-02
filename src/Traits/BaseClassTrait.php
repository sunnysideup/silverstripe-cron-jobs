<?php

namespace Sunnysideup\CronJobs\Traits;

use Sunnysideup\CronJobs\SiteUpdateUpdatePage;
use Sunnysideup\CronJobs\Analysis\AnalysisBaseClass;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Recipes\UpdateRecipe;
use Sunnysideup\CronJobs\RecipeTasks\SiteUpdateRecipeTaskBaseClass;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\ArrayData;

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

    public static function run_me(HTTPRequest $request)
    {
        $obj = self::inst();
        $recipeOrStep = 'Step';
        $isRecipe = false;
        if ($obj instanceof AnalysisBaseClass) {
            $obj->setRequest($request);

            return $obj->run($request);
        }

        if ($obj instanceof SiteUpdateRecipeTaskBaseClass) {
            // all set
        } elseif ($obj instanceof UpdateRecipe) {
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
            '<strong>' . $obj->getTitle() . '</strong> will run in the next 10 minutes or so.<br />
            To run it straight away, please run (on the command line): <br />
            <pre>
            vendor/bin/sake dev/tasks/SiteUpdateRun
            </pre>
            ' . $runItNow . '
            <br />To stop it, please delete: <a href="' . $obj->CMSEditLink() . '">the update record</a>.
        ';
    }

    public static function inst()
    {
        return Injector::inst()->get(static::class);
    }

    public function Link(): string
    {
        /** @var SiteUpdateUpdatePage $page */
        $page = SiteUpdateUpdatePage::get()->first();
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

    public function LastCompleted(): string
    {
        /** @var SiteUpdate|SiteUpdateStep $className */
        $className = $this->getLogClassName();
        if ($className) {
            $obj = $className::get()
                ->filter(['RunnerClassName' => static::class, 'Status' => 'Completed'])
                ->first();

            return $obj ? DBField::create_field(DBDatetime::class, $obj->LastEdited)->Ago() : 'Never Ran Successfully';
        }

        return 'n/a';
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
            $obj = $className::get()
                ->filter(['RunnerClassName' => static::class])
                ->exclude(['Status' => ['Started', 'Skipped', 'Shortened']])
                ->first();
            if ($obj) {
                return 'Completed' !== $obj->Status;
            }
        }

        return false;
    }

    public function CurrentlyRunning(): bool
    {
        /** @var SiteUpdate|SiteUpdateStep $className */
        $className = $this->getLogClassName();
        if ($className) {
            $obj = $className::get()
                ->filter(['RunnerClassName' => static::class])
                ->first();
            if ($obj) {
                return 'Started' === $obj->Status;
            }
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

        return $this->log->write();
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

    public static function my_child_links()
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
}
