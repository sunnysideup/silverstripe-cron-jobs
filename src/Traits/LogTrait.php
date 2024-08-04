<?php

namespace Sunnysideup\CronJobs\Traits;

use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use Sunnysideup\CronJobs\Cms\SiteUpdatesAdmin;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use Sunnysideup\CMSNiceties\Forms\CMSNicetiesLinkButton;

trait LogTrait
{
    public function Title(): string
    {
        return $this->getTitle();
    }

    public function getTitle(): string
    {
        $obj = $this->MyRunnerObject();

        return $obj ? $obj->getTitle() : 'Error';
    }

    public function getDescription(): string
    {
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
        $obj = $this->MyRunnerObject();

        return $obj ? $obj->getGroup() : 'Error';
    }

    /**
     * @return null|SiteUpdateRecipeBaseClass
     */
    public function MyRunnerObject()
    {
        if (class_exists($this->RunnerClassName)) {
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
        $obj = $this->MyRunnerObject();
        if ($obj) {
            $fields->addFieldsToTab(
                'Root.GeneralInfo',
                [
                    ReadonlyField::create('LastRan', 'Last Completed', $obj->LastCompleted()),
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
            <div style="background-color: #300a24; padding: 20px; height: 600px; overflow-y: auto;">' . $data . '</div>'
        );
        $fields->addFieldsToTab(
            'Root.Log',
            [
                $logField,
            ]
        );
        $fields->replaceField(
            'RunnerClassName',
            ReadonlyField::create('RunnerClassNameNice', 'Run', Injector::inst()->get($this->RunnerClassName)->i18n_singular_name())
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
    }

    public function recordErrors(string $recordClassName)
    {
        $errors = $this->getErrors();
        if ($errors) {
            $error = $recordClassName::create();
            $error->Type = 'ERROR';
            $error->Message = $errors;
            $error->SiteUpdateID = $this->ID;
            $error->write();
        }
    }
}
