<?php

namespace Sunnysideup\CronJobs\Model\Logs;

use SilverStripe\Control\Controller;
use Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateNote;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use Sunnysideup\CronJobs\Traits\LogTrait;
use SilverStripe\Control\Director;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CMSNiceties\Traits\CMSNicetiesTraitForReadOnly;

/**
 * Class \Sunnysideup\CronJobs\Model\Logs\SiteUpdate
 *
 * @property string $Notes
 * @property bool $Stopped
 * @property string $Status
 * @property string $Type
 * @property int $Errors
 * @property int $TimeTaken
 * @property int $MemoryTaken
 * @property string $ErrorLog
 * @property string $RunnerClassName
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep[] SiteUpdateSteps()
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateNote[] SiteUpdateNotes()
 */
class SiteUpdate extends DataObject
{
    use CMSNicetiesTraitForReadOnly;

    use LogTrait;

    use LogSuccessAndErrorsTrait;

    private static $table_name = 'SiteUpdate';

    private static $singular_name = 'Site Update';

    private static $plural_name = 'Site Updates';

    private static $db = [
        'Notes' => 'Text',
        'Stopped' => 'Boolean',
        'Status' => 'Enum("Started,Errors,NotCompleted,Completed,Skipped,Shortened","Started")',
        'Type' => 'Varchar(255)',
        'Errors' => 'Int',
        'TimeTaken' => 'Int',
        'MemoryTaken' => 'Int',
        'ErrorLog' => 'Text',
        'RunnerClassName' => 'Varchar(255)',
    ];

    private static $summary_fields = [
        'Created.Ago' => 'Started',
        'Status' => 'Status',
        'Stopped.NiceAndColourfull' => 'Stopped',
        'Title' => 'Recipe',
        'TimeTaken' => 'Seconds',
        'TimeNice' => 'Better Time',
        'MemoryTaken' => 'MBs',
        'SiteUpdateSteps.Count' => 'Steps',
        'SiteUpdateNotes.Count' => 'Notes',
    ];

    private static $field_labels = [
        'Title' => 'Update Recipe',
        'Stopped' => 'Stopped',
        'Created' => 'Started',
        'Description' => 'Description',
        'Status' => 'Status',
        'TimeTaken' => 'Seconds Used',
        'MemoryTaken' => 'Megabytes Used',
        'SiteUpdateSteps' => 'Steps',
        'SiteUpdateNotes' => 'Errors',
    ];

    private static $has_many = [
        'SiteUpdateSteps' => SiteUpdateStep::class,
        'SiteUpdateNotes' => SiteUpdateNote::class,
    ];

    private static $indexes = [
        'Stopped' => true,
        'LastEdited' => true,
        'Type' => true,
        'RunnerClassName' => true,
    ];

    private static $searchable_fields = [
        'Stopped' => 'ExactMatchFilter',
        'Type' => 'PartialMatchFilter',
        'Status' => 'ExactMatchFilter',
    ];

    private static $default_sort = [
        'ID' => 'DESC',
    ];

    private static $casting = [
        'Title' => 'Varchar',
        'Description' => 'Varchar',
        'Minutes' => 'Varchar',
        'TimeNice' => 'Varchar',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // add generic fields
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
            'RunnerClassName',
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

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->recordErrors(SiteUpdateNote::class);
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        $this->markStepsAsStoppedIfThisIsStopped();
    }

    protected function markStepsAsStoppedIfThisIsStopped()
    {
        if ($this->Stopped) {
            /** @var DataList[SiteUpdateStep]  */
            $items = $this->SiteUpdateSteps()->filterAny(['Stopped' => false]) ;
            foreach ($items as $step) {
                $step->Stopped = true;
                $step->Status = 'NotCompleted';
                $step->write();
            }
        }
    }

    public function onBeforeDelete()
    {
        parent::onBeforeDelete();
        $this->deleteFile();
    }


}
