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
use Sunnysideup\CronJobs\Api\BashColours;
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


        $readonlyFields = [
            'AllowedNextStep',
            'Status',
            'Type',
            'Errors',
            'MemoryTaken',
            'SiteUpdateID',
        ];
        $this->makeReadonOnlyForCMSFieldsAll($fields, $readonlyFields);

        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create('Title', 'Name'),
                ReadonlyField::create('Description', 'Description'),
                ReadonlyField::create('Type', 'Type'),
                ReadonlyField::create('Created', 'Started')
                    ->setDescription($this->dbObject('Created')->Ago()),
                ReadonlyField::create('LastEdited', 'Last active')
                    ->setDescription($this->dbObject('LastEdited')->Ago()),
                $fields->dataFieldByName('Status'),
            ],
            'Stopped'
        );

        $fields->dataFieldByName('Stopped')->setDescription('If required,you can tick this box to allow other recipes to run.');

        /** @var SiteUpdateRecipeBaseClass|SiteUpdateRecipeStepBaseClass $obj */
        $obj = $this->getRunnerObject();
        if ($obj) {
            $fields->removeByName('TimeTaken');
            $fields->removeByName('HasErrors');
            if ($this instanceof SiteUpdate) {
                $fields->addFieldsToTab(
                    'Root.WhenDoesItRun',
                    [
                        HeaderField::create('MostRecentRunInfo', 'Most recent run'),
                        ReadonlyField::create('LastStarted', 'Started', $obj->LastStarted()),
                        ReadonlyField::create('LastCompleted', 'Completed', $obj->LastCompleted()),
                        ReadonlyField::create('CanRunNice', 'Can run at all?', $obj->CanRunNice()->NiceAndColourfull()),
                        ReadonlyField::create('CanRunCalculatedNice', 'Can run right now?', $obj->CanRunCalculatedNice()->NiceAndColourfull()),
                        ReadonlyField::create('CurrentlyRunningNice', 'An instance is currently Running?', $obj->IsCurrentlyRunningNice()->NiceAndColourfullInvertedColours()),
                        ReadonlyField::create('IsMeetingTarget', 'Is it meeting its targets?', $obj->IsMeetingTargetNice()->NiceAndColourfull()),
                        ReadonlyField::create('OverDueMinutes', 'Minutes overdue to run again?', $obj->overTimeSinceLastRun()),
                        ReadonlyField::create('getExpectedMinimumEntriesPer24Hours', 'Expected minimum runs per 24 hours', round($obj->getExpectedMinimumEntriesPer24Hours(), 3)),
                        ReadonlyField::create('getExpectedMaximumEntriesPer24Hours', 'Expected maximum runs per 24 hours', round($obj->getExpectedMaximumEntriesPer24Hours(), 3)),
                        ReadonlyField::create('getActualEntriesPer', 'Actuals runs in last 24 hour cycle', $obj->getActualEntriesPer()),
                        ReadonlyField::create('getActualEntriesPer30', 'Actuals runs in last 30 days cycle', $obj->getActualEntriesPer(30)),
                        ReadonlyField::create('HoursOfTheDayNice', 'Hours of the day it runs', $obj->HoursOfTheDayNice()),
                        ReadonlyField::create('MinMinutesBetweenRunsNice', 'Minimum time between runs', $obj->MinMinutesBetweenRunsNice()),
                        ReadonlyField::create('MaxMinutesBetweenRunsNice', 'Maximum time between runs', $obj->MaxMinutesBetweenRunsNice()),
                    ]
                );
            }
            $fields->addFieldsToTab(
                'Root.Stats',
                [
                    // $fields->dataFieldByName('TimeNice'),
                    HeaderField::create('ErrorCounts', 'Errors'),
                    ReadonlyField::create('HasHadErrorsNice', 'Has had errors in any run?', $obj->HasHadErrorsNice()->NiceAndColourfullInvertedColours()),
                    ReadonlyField::create('LastRunHadErrorsNice', 'Most recent run had errors', $obj->LastRunHadErrorsNice()->NiceAndColourfullInvertedColours()),
                    HeaderField::create('TimeUse', 'Time Use'),
                    ReadonlyField::create('AverageTimeTakenNice', 'Average time taken - any run', $obj->AverageTimeTakenNice()),
                    ReadonlyField::create('MaxTimeTakenNice', 'Max time taken - any run', $obj->MaxTimeTakenNice()),
                    HeaderField::create('MemoryUse', 'Memory Use (in megabytes)'),
                    ReadonlyField::create('AverageMemoryTaken', 'Average memory taken - any run', $obj->AverageMemoryTaken()),
                    ReadonlyField::create('MaxMemoryTaken', 'Max memory taken - any run', $obj->MaxMemoryTaken()),

                ],
            );
            $fields->addFieldsToTab(
                'Root.ImportantLogs',
                [
                    ReadonlyField::create('HasErrorsNice', 'This run had errors?', $this->dbObject('HasErrors')->NiceAndColourfullInvertedColours()),
                    ReadonlyField::create('Errors', 'Error count'),
                    ReadonlyField::create('Notes', 'Notes / Errors'),
                    ReadonlyField::create('TimeTakenNice', 'Time taken for this run', $this->getTimeNice()),
                    $fields->dataFieldByName('MemoryTaken')->setTitle('Memory taken for this run'),
                ],
                'ImportantLogs'
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
        $data = $this->getLogContent();
        $source = basename($this->logFilePath());

        $logField = LiteralField::create(
            'Logs',
            '<h2>Response from the lastest update only - stored in (' . $source . ')</h2>
            <div style="background-color: #300a24; padding: 20px; height: 600px; overflow-y: auto; border-radius: 10px; color: #efefef;">' . $data . '</div>'
        );
        $fields->addFieldsToTab(
            'Root.ImportantLogs',
            [
                $logField,
            ]
        );
        $obj = $this->getRunnerObject();
        $fields->removeByName(
            'RunnerClassName',
        );

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



    public function recordErrorsOnBeforeWrite(?string  $recordClassName = SiteUpdate::class, ?string $relFieldName = 'SiteUpdateID')
    {
        if (!$this->Stopped && $this->Status === 'Started') {
            return null;
        }
        $logError = false;
        $errorContents = $this->getLogContent();
        $reasons = [];
        if ($this->Errors > 0) {
            $reasons[] = 'Errors Recorded';
            $this->HasErrors = true;
        }
        if ($this->hasErrorInLog($errorContents)) {
            $reasons[] = 'Has Error in Log';
            $logError = true;
        }
        if ($this->Stopped && 'Started' === $this->Status) {
            $reasons[] = 'Mismatch in Stopped and Status (Stopped and Started)';
            $logError = true;
        }
        if (!$this->Stopped && $this->Status !== 'Started') {
            $reasons[] = 'Mismatch in Stopped and Status (not Stopped and not Started)';
            $logError = true;
        }
        if ('NotCompleted' === $this->Status) {
            $reasons[] = 'Not completed';
            $logError = true;
        }
        if ($recordClassName::get()->filter(['Type' => 'ERROR', $relFieldName => $this->ID, 'Important' => true,])->exists()) {
            $reasons[] = 'Important error in error log';
            $logError = true;
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


    protected function getLogContent(): string
    {
        $filePath = $this->logFilePath();
        if (file_exists($filePath)) {
            return BashColours::bash_to_html(file_get_contents($filePath));
        }

        return 'no file found here '.$filePath;
    }

    protected function deleteLogFile()
    {
        if (file_exists($this->logFilePath())) {
            unlink($this->logFilePath());
        }
    }

    protected function deleteImportantLogs()
    {
        foreach ($this->ImportantLogs() as $log) {
            $log->delete();
        }
    }


    protected function hasErrorInLog(string $contents): bool
    {
        $needles = ['[Emergency]', '[Error]', '[CRITICAL]', '[ALERT]', '[ERROR]'];
        foreach ($needles as $needle) {
            if (strpos($contents, $needle) !== false) {
                return true;
            }
        }
        return false;
    }


}
