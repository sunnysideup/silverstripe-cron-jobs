<?php

namespace Sunnysideup\CronJobs\Model\Logs;

use Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateStepNote;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use Sunnysideup\CronJobs\Traits\LogTrait;
use SilverStripe\Control\Director;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CMSNiceties\Traits\CMSNicetiesTraitForReadOnly;
use Sunnysideup\CronJobs\Forms\SiteUpdateStepDropdownField;

/**
 * Class \Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep
 *
 * @property string $Notes
 * @property bool $Stopped
 * @property string $Type
 * @property bool $HasErrors
 * @property int $Errors
 * @property string $Status
 * @property bool $AllowedNextStep
 * @property int $TimeTaken
 * @property int $MemoryTaken
 * @property string $RunnerClassName
 * @property int $SiteUpdateID
 * @method \Sunnysideup\CronJobs\Model\Logs\SiteUpdate SiteUpdate()
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateStepNote[] ImportantLogs()
 */
class SiteUpdateStep extends DataObject
{
    use CMSNicetiesTraitForReadOnly;

    use LogTrait;

    use LogSuccessAndErrorsTrait;

    private static $table_name = 'SiteUpdateStep';

    private static $singular_name = 'Step Log';

    private static $plural_name = 'Step Logs';

    private static $db = [
        'Notes' => 'Text',
        'Stopped' => 'Boolean',
        'Type' => 'Varchar(255)',
        'HasErrors' => 'Boolean',
        'Errors' => 'Int',
        'Status' => 'Enum("Started,NotCompleted,Completed,Skipped","Started")',
        'AllowedNextStep' => 'Boolean(1)',
        'TimeTaken' => 'Int',
        'MemoryTaken' => 'Int',
        'SysLoadA' => 'Float',
        'SysLoadB' => 'Float',
        'SysLoadC' => 'Float',
        'RunnerClassName' => 'Varchar(255)',
    ];

    private static $has_one = [
        'SiteUpdate' => SiteUpdate::class,
    ];

    private static $has_many = [
        'ImportantLogs' => SiteUpdateStepNote::class,
    ];

    private static $summary_fields = [
        'Created.Ago' => 'Started',
        'SiteUpdate.Title' => 'Recipe',
        'Stopped.NiceAndColourfull' => 'Stopped',
        'Title' => 'Step',
        'Status' => 'Status',
        'AllowedNextStep.NiceAndColourfull' => 'Allow Next Step?',
        'TimeTaken' => 'Seconds',
        'MemoryTaken' => 'Memory (MBs)',
        'HasErrors.NiceAndColourfullInvertedColours' => 'Errors',
    ];

    private static $field_labels = [
        'Title' => 'Step',
        'Notes' => 'Notes',
        'Stopped' => 'Stopped',
        'Status' => 'Status',
        'AllowedNextStep' => 'Allowed next step to run',
        'TimeTaken' => 'Seconds Used',
        'MemoryTaken' => 'Megabytes Used',
        'SysLoadA' => 'CPU use Latest 1 Minute',
        'SysLoadB' => 'CPU use Latest 5 Minutes',
        'SysLoadC' => 'CPU use Latest 15 Minutes',
        'ImportantLogs' => 'Important Logs',
    ];

    private static $searchable_fields = [
        'RunnerClassName' => [
            'field' => SiteUpdateStepDropdownField::class,
            'filter' => 'ExactMatchFilter',
        ],
        'Stopped' => 'ExactMatchFilter',
        'Status' => 'ExactMatchFilter',
        'HasErrors' => 'ExactMatchFilter',
        'Notes' => 'PartialMatchFilter',
        'AllowedNextStep' => 'ExactMatchFilter',
    ];

    private static $indexes = [
        'AllowedNextStep' => true,
        'Stopped' => true,
        'LastEdited' => true,
        'Type' => true,
        'RunnerClassName' => true,
    ];

    private static $casting = [
        'Title' => 'Varchar',
        'Description' => 'Varchar',
        'TimeNice' => 'Varchar',
    ];

    private static $default_sort = [
        'ID' => 'DESC',
    ];

    private static $defaults = [
        'AllowedNextStep' => true,
    ];

    public function canEdit($member = null)
    {
        if (Director::isDev()) {
            return true;
        }

        if ($this->Stopped) {
            return false;
        }

        return parent::canEdit($member);
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $this->addGenericFields($fields);

        return $fields;
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->recordErrorsOnBeforeWrite(SiteUpdateStepNote::class, 'SiteUpdateStepID');
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        $this->deleteLogFile();
        $this->deleteImportantLogs();
    }
}
