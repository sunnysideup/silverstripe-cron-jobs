<?php

namespace Sunnysideup\CronJobs\Traits;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Recipes\UpdateRecipe;
use Sunnysideup\CronJobs\Cms\SiteUpdatesAdmin;
use SilverStripe\Core\Injector\Injector;
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
     * @return null|UpdateRecipe
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

    protected function escapedClassNameForAdmin(): string
    {
        return str_replace('\\', '-', $this->ClassName);
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
}
