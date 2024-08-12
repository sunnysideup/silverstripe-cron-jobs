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
        $obj = $this->MyRunnerObject();

        return $obj ? $obj->getTitle() : 'Error';
    }

    public function getDescription(): string
    {
        /** @var SiteUpdateRecipeBaseClass|SiteUpdateRecipeStepBaseClass $obj */
        $obj = $this->MyRunnerObject();

        return $obj ? trim($obj->getDescription()) : 'Error';
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
        $obj = $this->MyRunnerObject();

        return $obj ? $obj->getGroup() : 'Error';
    }

    /**
     * @return null|SiteUpdateRecipeBaseClass
     */
    public function MyRunnerObject()
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
        if(! $this->RunnerClassName) {
            return 'error';
        }
        if(! class_exists($this->RunnerClassName)) {
            return 'error';
        }
        return ClassInfo::shortName($this->RunnerClassName);
    }

    protected function addGenericFields($fields)
    {
        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create('Title', 'Step'),
                ReadonlyField::create('Description', 'Description'),
                ReadonlyField::create('Created', 'Created'),
                ReadonlyField::create('LastEdited', 'Last Edited'),
            ]
        );

        /** @var SiteUpdateRecipeBaseClass|SiteUpdateRecipeStepBaseClass $obj */
        $obj = $this->MyRunnerObject();
        if ($obj) {
            if($obj instanceof SiteUpdate) {
                $fields->addFieldsToTab(
                    'Root.GeneralInfo',
                    [
                        ReadonlyField::create('HoursOfTheDayNice', 'Hours of the day it runs', $obj->HoursOfTheDayNice()),
                        ReadonlyField::create('MinMinutesBetweenRunsNice', 'Minimum Number of Minutes between Runs', $obj->MinMinutesBetweenRunsNice()),
                        ReadonlyField::create('MaxMinutesBetweenRunsNice', 'Max Number of Minutes between Runs', $obj->MaxMinutesBetweenRunsNice()),
                    ]
                );
            }
            $fields->addFieldsToTab(
                'Root.GeneralInfo',
                [
                    ReadonlyField::create('NumberOfLogs', 'Last Completed', $obj->NumberOfLogs()),
                    ReadonlyField::create('LastStarted', 'Last Completed', $obj->LastStarted()),
                    ReadonlyField::create('LastCompleted', 'Last Completed', $obj->LastCompleted()),
                    ReadonlyField::create('AverageTimeTaken', 'Average Time Taken', $obj->AverageTimeTaken()),
                    ReadonlyField::create('AverageMemoryTaken', 'Average Memory Taken', $obj->AverageMemoryTaken()),
                    ReadonlyField::create('MaxTimeTaken', 'Max Time Taken', $obj->MaxTimeTaken()),
                    ReadonlyField::create('MaxMemoryTaken', 'Max Memory Taken', $obj->MaxMemoryTaken()),
                    ReadonlyField::create('HasErrors', 'Has Errors', $obj->HasErrors() ? 'YES' : 'NO'),
                    ReadonlyField::create('CurrentlyRunning', 'Currently Running', $obj->CurrentlyRunning() ? 'YES' : 'NO'),
                ]
            );
            $fields->addFieldsToTab(
                'Root.RunNow',
                [
                    CMSNicetiesLinkButton::create('RunNow', 'Run Now', $obj->Link()),
                ]
            );
        }
        if ($this->ErrorLog) {
            $data = $this->ErrorLog;
            $source = 'Saved';
        } else {
            $data = $this->getLogContent();
            $source = basename($this->logFilePath());
        }

        $logField = LiteralField::create(
            'Logs',
            '<h2>Response from the lastest update only - stored in (' . $source . ')</h2>
            <div style="background-color: #300a24; padding: 20px; height: 600px; overflow-y: auto; border-radius: 10px; color: #efefef;">' . $data . '</div>'
        );
        $fields->addFieldsToTab(
            'Root.Log',
            [
                $logField,
            ]
        );
        /**
         * @var
         *
         */
        $obj = $this->MyRunnerObject();
        $runnerClassNameNice = $obj ? $obj->getTitle() : 'Error';
        $fields->replaceField(
            'RunnerClassName',
            ReadonlyField::create('RunnerClassNameNice', 'Run', $runnerClassNameNice)
        );

    }

    protected function secondsToTime(int $inputSeconds)
    {
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


    protected function getErrors(): ?string
    {
        $contents = $this->getLogContent();
        $logError = false;
        if ($this->hasErrorInLog($contents)) {
            $logError = true;
            $this->ErrorLog = $contents;
            $this->Status = 'Errors';
        }

        if ('NotCompleted' === $this->Status) {
            $logError = true;
        }

        if ($this->Stopped && 'Started' === $this->Status) {
            $this->Status = 'Errors';
            $logError = true;
        }
        if($logError) {
            return $contents;
        }
        return null;
    }

    public function recordErrors(string $recordClassName = SiteUpdate::class, $relFieldName = 'SiteUpdateID')
    {
        $errors = $this->getErrors();
        if ($errors) {
            $error = $recordClassName::create();
            $error->Type = 'HAS ERROR IN LOG';
            $error->Message = $errors;
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
        if(! $directory) {
            $directory = SiteUpdateConfig::folder_path();
        }
        if(file_exists($directory)) {
            $this->deleteAllFilesInFolder($directory);
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
        unlink($this->logFilePath());
    }


    protected function hasErrorInLog(string $contents): bool
    {
        $needles = ['[Emergency]', '[Error]', '[CRITICAL]', '[ALERT]', '[ERROR]'];
        foreach($needles as $needle) {
            if (strpos($contents, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

}
