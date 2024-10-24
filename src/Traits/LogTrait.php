<?php

namespace Sunnysideup\CronJobs\Traits;

use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use Sunnysideup\CronJobs\Cms\SiteUpdatesAdmin;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use Sunnysideup\CMSNiceties\Forms\CMSNicetiesLinkButton;
use InvalidArgumentException;
use RuntimeException;
use SilverStripe\Control\Controller;
use Sunnysideup\CronJobs\Model\SiteUpdateConfig;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\HeaderField;
use SilverStripe\ORM\FieldType\DBDatetime;
use Sunnysideup\CronJobs\Api\BashColours;
use Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateNote;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;

trait LogTrait
{
    public function Title(): string
    {
        return $this->getTitle();
    }

    public function getTitle(): string
    {
        /** @var SiteUpdateRecipeBaseClass $obj */
        $obj = $this->getRunnerObject();

        return $obj ? $obj->getTitle() : 'Error - No Title';
    }

    public function getDescription(): string
    {
        /** @var SiteUpdateRecipeBaseClass|SiteUpdateRecipeStepBaseClass $obj */
        $obj = $this->getRunnerObject();

        return $obj ? trim($obj->getDescription()) : 'Error - No Description';
    }

    public function Minutes(): string
    {
        return $this->getMinutes();
    }

    public function getMinutes(): string
    {
        return '< ' . (floor($this->TimeTaken / 60) + 1);
    }

    public function TimeNice(): string
    {
        return $this->getTimeNice();
    }

    public function getTimeNice(): string
    {
        return $this->secondsToTime($this->TimeTaken);
    }

    public function getRamLoadNice(): string
    {
        return round(($this->RamLoad) * 100).'%';
    }

    public function getSysLoadNice(?string $letter = 'A'): string
    {
        $var = 'SysLoad' . strtoupper($letter);
        return round(($this->$var) * 100).'%';
    }

    public function getMemoryTakenNice(): string
    {
        return $this->MemoryTaken.' megabytes';
    }

    public function getCreatedNice(): string
    {
        return date('d M H:i', strtotime($this->Created));
    }

    public function getGroup(): string
    {
        /** @var SiteUpdateRecipeBaseClass|SiteUpdateRecipeStepBaseClass $obj */
        $obj = $this->getRunnerObject();

        return $obj ? $obj->getGroup() : 'Error - No Group';
    }

    /**
     * @return null|SiteUpdateRecipeBaseClass
     */
    public function getRunnerObject()
    {
        /** @var SiteUpdateRecipeBaseClass|SiteUpdateRecipeStepBaseClass $obj */
        if (class_exists((string) $this->RunnerClassName)) {
            return Injector::inst()->get($this->RunnerClassName);
        }

        return null;
    }

    public function CMSEditLink(): string
    {
        return Injector::inst()->get(SiteUpdatesAdmin::class)->getCMSEditLinkForManagedDataObject($this);
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canDelete($member = null)
    {
        if ($this instanceof SiteUpdateRunNext) {
            return parent::canDelete($member);
        }

        return false;
    }

    public function getShortClassCode(): string
    {
        if (! $this->RunnerClassName) {
            return 'error';
        }
        if (! class_exists($this->RunnerClassName)) {
            return 'error';
        }
        return ClassInfo::shortName($this->RunnerClassName);
    }

    protected function addGenericFields($fields)
    {
        $fields->removeByName(
            [
                'RunnerClassName',
                'TimeTaken',
                'HasErrors',
                'Type',
                'SysLoadA',
                'SysLoadB',
                'SysLoadC',
                'RamLoad',
            ]
        );
        $readonlyFields = [
            'AllowedNextStep',
            'Status',
            'Errors',
            'MemoryTaken',
            'SiteUpdateID',
            'Attempts',
        ];

        $this->makeReadonOnlyForCMSFieldsAll($fields, $readonlyFields);
        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create('Title', 'Name'),
                ReadonlyField::create('Description', 'Description'),
                ReadonlyField::create('Created', 'Started')
                    ->setDescription($this->dbObject('Created')->Ago()),
                ReadonlyField::create('LastEdited', 'Last active')
                    ->setDescription($this->dbObject('LastEdited')->Ago()),
                $fields->dataFieldByName('Status'),
                ReadonlyField::create('Attempts', 'Attempts'),
            ],
            'Stopped'
        );

        $fields->dataFieldByName('Stopped')->setDescription('If required,you can tick this box to allow other recipes to run.');

        /** @var SiteUpdateRecipeBaseClass|SiteUpdateRecipeStepBaseClass $obj */
        $obj = $this->getRunnerObject();
        if ($obj) {
            $fields->addFieldsToTab(
                'Root.ImportantLogs',
                [
                    ReadonlyField::create('HasErrorsNice', 'This run had errors?', $this->dbObject('HasErrors')->NiceAndColourfullInvertedColours()),
                    ReadonlyField::create('Errors', 'Error count'),
                    ReadonlyField::create('Notes', 'Notes / Errors'),
                    ReadonlyField::create('TimeTakenNice', 'Time taken for this run', $this->getTimeNice()),
                    ReadonlyField::create('RamLoadNice', 'Total Ram Load of Server', $this->getRamLoadNice()),
                    ReadonlyField::create('SysLoadANice', 'CPUs used previous minute', $this->getSysLoadNice('A')),
                    ReadonlyField::create('SysLoadBNice', 'CPUs used previous 5 minute', $this->getSysLoadNice('B')),
                    ReadonlyField::create('SysLoadCNice', 'CPUs used previous 15 minutes', $this->getSysLoadNice('C')),
                    ReadonlyField::create('MemoryTakenNice', 'Memory taken for this run', $this->getMemoryTakenNice()),
                ],
                'ImportantLogs'
            );
            if ($this instanceof SiteUpdate) {
                $fields->addFieldsToTab(
                    'Root.Schedule',
                    [
                        ReadonlyField::create('CanRunNice', 'Can run at all?', $obj->CanRunNice()->NiceAndColourfull()),
                        ReadonlyField::create('CanRunCalculatedNice', 'Can run right now?', $obj->CanRunCalculatedNice()->NiceAndColourfull()),
                        ReadonlyField::create('CurrentlyRunningNice', 'An instance is currently Running?', $obj->IsCurrentlyRunningNice()->NiceAndColourfullInvertedColours()),
                        ReadonlyField::create('HoursOfTheDayNice', 'Hours of the day it runs', $obj->HoursOfTheDayNice()),
                        ReadonlyField::create('IsMeetingTarget', 'Is it meeting its targets?', $obj->IsMeetingTargetNice()->NiceAndColourfull()),
                        ReadonlyField::create('OverDueMinutes', 'Minutes overdue to run again?', $obj->overTimeSinceLastRunNice()),
                        ReadonlyField::create('MinMinutesBetweenRunsNice', 'Minimum time between runs', $obj->MinMinutesBetweenRunsNice()),
                        ReadonlyField::create('getExpectedMaximumEntriesPer24Hours', 'Expected maximum runs per 24 hours', round($obj->getExpectedMaximumEntriesPer24Hours(), 3)),
                        ReadonlyField::create('MaxMinutesBetweenRunsNice', 'Maximum time between runs', $obj->MaxMinutesBetweenRunsNice()),
                        ReadonlyField::create('getExpectedMinimumEntriesPer24Hours', 'Expected minimum runs per 24 hours', round($obj->getExpectedMinimumEntriesPer24Hours(), 3)),
                        ReadonlyField::create('getActualEntriesPer', 'Successful runs in last 24 hour cycle', $obj->getActualEntriesPer()),
                        ReadonlyField::create('getActualEntriesPer30', 'Successful runs in last 30 days cycle', $obj->getActualEntriesPer(30)),
                    ]
                );
                $fields->addFieldsToTab(
                    'Root.LastRun',
                    [
                        ReadonlyField::create('StatusOfLastRun', 'Status of Last Run', $obj->LastStoppedRunLog()?->Status ?? 'n/a'),
                        ReadonlyField::create('LastStarted', 'Last time a run started', $obj->LastStarted()),
                        ReadonlyField::create('LastCompleted', 'Last time a run completed', $obj->LastCompleted()),
                        ReadonlyField::create('LastRunHadErrorsNice', 'Most recent run had errors', $obj->LastRunHadErrorsNice()->NiceAndColourfullInvertedColours()),
                    ]
                );
            }
            $fields->addFieldsToTab(
                'Root.Stats',
                [
                    // $fields->dataFieldByName('TimeNice'),
                    HeaderField::create('ErrorCounts', 'Errors'),
                    ReadonlyField::create('HasHadErrorsNice', 'Has had errors in any run?', $obj->HasHadErrorsNice()->NiceAndColourfullInvertedColours()),
                    //
                    HeaderField::create('TimeUse', 'Time use'),
                    ReadonlyField::create('AverageTimeTakenNice', 'Average time taken - any run', $obj->AverageTimeTakenNice()),
                    ReadonlyField::create('MaxTimeTakenNice', 'Max time taken - any run', $obj->MaxTimeTakenNice()),
                    //
                    HeaderField::create('MemoryUse', 'Memory use (in megabytes)'),
                    ReadonlyField::create('AverageMemoryTaken', 'Average memory taken - any run', $obj->AverageMemoryTaken()),
                    ReadonlyField::create('MaxMemoryTaken', 'Max memory taken - any run', $obj->MaxMemoryTaken()),
                    //
                    // HeaderField::create('CPUUse', 'CPU use'),
                    // ReadonlyField::create('AverageSysLoadA', 'Average CPU use over 1 minute', $obj->AverageSysLoadA()),
                    // ReadonlyField::create('MaxSysLoadA', 'Max CPU use over 1 minute', $obj->MaxSysLoadA()),
                    // //
                    // ReadonlyField::create('AverageSysLoadB', 'Average CPU use over 5 minutes', $obj->AverageSysLoadB()),
                    // ReadonlyField::create('MaxSysLoadB', 'Max CPU use over 5 minutes', $obj->MaxSysLoadB()),
                    // //
                    // ReadonlyField::create('AverageSysLoadC', 'Average CPU use over 15 minutes', $obj->AverageSysLoadC()),
                    // ReadonlyField::create('MaxSysLoadC', 'Max CPU use over 15 minutes', $obj->MaxSysLoadC()),
                ],
            );

        }
        $removeOptionsFields = [
            'ImportantLogs',
            'SiteUpdateSteps',
        ];
        foreach ($removeOptionsFields as $removeOptionsField) {
            $gfNotes = $fields->dataFieldByName($removeOptionsField);
            if ($gfNotes) {
                $gfNotes->getConfig()->removeComponentsByType(GridFieldAddExistingAutocompleter::class);
            }
        }
        if ($obj) {
            $fields->addFieldsToTab(
                'Root.RunNow',
                [
                    CMSNicetiesLinkButton::create('RunNow', 'Run Now', $obj->Link(), true),
                ]
            );
        }

    }

    protected function secondsToTime(int $inputSeconds)
    {
        if ($inputSeconds < 1) {
            return 'n/a';
        }
        $secondsInAMinute = 60;
        $secondsInAnHour = 60 * $secondsInAMinute;
        $secondsInADay = 24 * $secondsInAnHour;

        // Extract days
        $days = floor($inputSeconds / $secondsInADay);

        // Extract hours
        $hourSeconds = $inputSeconds % $secondsInADay;
        $hours = floor($hourSeconds / $secondsInAnHour);

        // Extract minutes
        $minuteSeconds = $hourSeconds % $secondsInAnHour;
        $minutes = floor($minuteSeconds / $secondsInAMinute);

        // Extract the remaining seconds
        $remainingSeconds = $minuteSeconds % $secondsInAMinute;
        $seconds = ceil($remainingSeconds);

        // Format and return
        $timeParts = [];
        $sections = [
            'day' => (int) $days,
            'hour' => (int) $hours,
            'minute' => (int) $minutes,
            'second' => (int) $seconds,
        ];

        foreach ($sections as $name => $value) {
            if ($value > 0) {
                $timeParts[] = $value . ' ' . $name . (1 === $value ? '' : 's');
            }
        }

        return implode(', ', $timeParts);
    }

    protected function recordsStandardValuesAndFixes(?string  $recordClassName = SiteUpdateNote::class, ?string $relFieldName = 'SiteUpdateID')
    {
        $this->LastEdited = DBDatetime::now()->Rfc2822();
        /** @var SiteUpdateStep $step */
        if (! $this->Status) {
            $this->Status = $this->Stopped ? 'NotCompleted' : 'Started';
        }
        if (!$this->Stopped && $this->Status === 'Started') {
            return null;
        }
        $logError = false;
        $reasons = [];
        if ($this->Stopped) {
            if ('NotCompleted' === $this->Status) {
                $reasons[] = 'Not completed';
                $logError = true;
            }
            if ($this->Errors > 0) {
                $reasons[] = 'Errors Recorded';
                $this->HasErrors = true;
            }
            $errorContents = $this->getLogContent();
            if ($this->hasErrorInLog($errorContents)) {
                $reasons[] = 'Has Error in Log';
                $logError = true;
            }
            if ('Started' === $this->Status) {
                $reasons[] = 'Mismatch in Stopped and Status (Stopped and Started)';
                $logError = true;
            }
            if ($recordClassName::get()->filter(['Type' => 'ERROR', $relFieldName => $this->ID, 'Important' => true,])->exists()) {
                $reasons[] = 'Important error in error log';
                $logError = true;
            }
            if ($this instanceof SiteUpdate) {
                $this->TotalStepsErrors = 0;
                /** @var SiteUpdateStep $step */
                foreach ($this->SiteUpdateSteps()->filter(['HasErrors' => true]) as $step) {
                    $this->TotalStepsErrors += $step->Errors;
                }
            }
            $startTS = strtotime($this->Created);
            $endTS = strtotime($this->LastEdited);
            $diffTs = $endTS - $startTS;
            if ($this->TimeTaken < $diffTs) {
                $this->TimeTaken = $diffTs;
            }
        } else {
            if ($this->Status !== 'Started') {
                $reasons[] = 'Mismatch in Stopped and Status (not Stopped and not Started)';
                $logError = true;
            }
        }
        if ($logError) {
            $this->Stopped = true;
            $this->HasErrors = true;
            // $this->write();
            // No need to write as this is called from onBeforeWrite!
            $error = $recordClassName::create();
            $error->Type = 'ERROR';
            $error->Message = implode(', ', $reasons). PHP_EOL. PHP_EOL .$errorContents;
            $error->$relFieldName = $this->ID;
            $error->write();
        }

    }

    public function logFilePath(): string
    {
        return Controller::join_links(
            SiteUpdateConfig::folder_path(),
            $this->getShortClassCode() . '_' . $this->ID . '-update.log'
        );

    }

    public function deleteAllFilesInFolder(?string $directory = '')
    {
        if (! $directory) {
            $directory = SiteUpdateConfig::folder_path();
        }
        if (file_exists($directory)) {
            if (!is_dir($directory)) {
                throw new InvalidArgumentException('The provided path is not a directory: '.$directory);
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

    }



    public function hasCompletedStep(string $stepClassName): bool
    {
        if (! $this instanceof SiteUpdate) {
            return false;
        }
        return $this->SiteUpdateSteps()->filter(['RunnerClassName' => $stepClassName, 'Status' => 'Completed', 'Stopped' => true])->exists();
    }

    public function hasNotCompletedStep(string $stepClassName): bool
    {
        if (! $this instanceof SiteUpdate) {
            return false;
        }
        return $this->SiteUpdateSteps()->filter(['RunnerClassName' => $stepClassName, 'Status' => 'NotCompleted', 'Stopped' => true])->exists();
    }


}
