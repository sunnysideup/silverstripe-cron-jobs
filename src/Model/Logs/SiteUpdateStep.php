<?php

namespace Sunnysideup\CronJobs\Model\Logs;

use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use Sunnysideup\CronJobs\Traits\LogTrait;
use SilverStripe\Control\Director;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CMSNiceties\Traits\CMSNicetiesTraitForReadOnly;

/**
 * Class \Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep
 *
 * @property string $Notes
 * @property bool $Stopped
 * @property string $Type
 * @property int $Errors
 * @property string $Status
 * @property bool $AllowNextStep
 * @property int $TimeTaken
 * @property int $MemoryTaken
 * @property string $RunnerClassName
 * @property int $SiteUpdateID
 * @method \Sunnysideup\CronJobs\Model\Logs\SiteUpdate SiteUpdate()
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\CronJobs\Model\Logs\SiteUpdateStepNote[] SiteUpdateStepNotes()
 */
class SiteUpdateStep extends DataObject
{
    use CMSNicetiesTraitForReadOnly;

    use LogTrait;

    use LogSuccessAndErrorsTrait;

    private static $table_name = 'SiteUpdateStep';

    private static $singular_name = 'Update Step Log Entry';

    private static $plural_name = 'Update Step Log Entries';

    private static $db = [
        'Notes' => 'Text',
        'Stopped' => 'Boolean',
        'Type' => 'Varchar(255)',
        'Errors' => 'Int',
        'Status' => 'Enum("Started,Errors,NotCompleted,Completed,Skipped","Started")',
        'AllowNextStep' => 'Boolean',
        'TimeTaken' => 'Int',
        'MemoryTaken' => 'Int',
        'RunnerClassName' => 'Varchar(255)',
    ];

    private static $has_one = [
        'SiteUpdate' => SiteUpdate::class,
    ];

    private static $has_many = [
        'SiteUpdateStepNotes' => SiteUpdateStepNote::class,
    ];

    private static $summary_fields = [
        'Created.Ago' => 'Started',
        'SiteUpdate.Type' => 'Recipe',
        'Stopped.NiceAndColourfull' => 'Stopped',
        'Title' => 'Step',
        'Status' => 'Status',
        'AllowNextStep.NiceAndColourfull' => 'Allow Next Step?',
        'TimeTaken' => 'Seconds',
        'TimeNice' => 'Better Time',
        'MemoryTaken' => 'MBs',
    ];

    private static $field_labels = [
        'Title' => 'Step',
        'Notes' => 'Notes',
        'Stopped' => 'Stopped',
        'Status' => 'Status',
        'AllowNextStep' => 'Allow next step to run',
        'TimeTaken' => 'Seconds Used',
        'MemoryTaken' => 'Megabytes Used',
    ];

    private static $searchable_fields = [
        'Stopped' => 'ExactMatchFilter',
        'Status' => 'ExactMatchFilter',
        'Type' => 'PartialMatchFilter',
        'Notes' => 'PartialMatchFilter',
        'AllowNextStep' => 'ExactMatchFilter',
    ];

    private static $indexes = [
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
        $fields->removeByName([
            'RunnerClassName',
            'TimeTaken',
        ]);
        $readonlyFields = [
            'Status',
            'Type',
            'Errors',
            'TimeTaken',
            'MemoryTaken',
            'ErrorLog',
            'SiteUpdateID',
        ];
        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create(
                    'TimeNice',
                    'Time taken'
                ),
            ],
            'Errors'
        );
        $this->makeReadonOnlyForCMSFieldsAll($fields, $readonlyFields);

        return $fields;
    }
}
