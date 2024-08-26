<?php

namespace Sunnysideup\CronJobs\Model\Logs;

use SilverStripe\Control\Controller;
use Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateNote;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use Sunnysideup\CronJobs\Traits\LogTrait;
use SilverStripe\Control\Director;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\FieldType\DBHTMLText;
use Sunnysideup\CMSNiceties\Traits\CMSNicetiesTraitForReadOnly;
use Sunnysideup\CronJobs\Forms\CustomGridFieldDataColumns;

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
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateNote[] ImportantLogs()
 */
class SiteUpdate extends DataObject
{
    use CMSNicetiesTraitForReadOnly;

    use LogTrait;

    use LogSuccessAndErrorsTrait;

    private static $table_name = 'SiteUpdate';

    private static $singular_name = 'Recipe Log';

    private static $plural_name = 'Recipe Logs';

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


    private static $has_many = [
        'SiteUpdateSteps' => SiteUpdateStep::class,
        'ImportantLogs' => SiteUpdateNote::class,
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
        'ImportantLogs' => 'Important Logs',
    ];



    private static $summary_fields = [
        'Created.Ago' => 'Started',
        'Status' => 'Status',
        'Stopped.NiceAndColourfull' => 'Stopped',
        'Title' => 'Recipe',
        'SiteUpdateSteps.Count' => 'Steps',
        'TimeTaken' => 'Time Taken',
        'MemoryTaken' => 'Memory (MBs)',
        'ImportantLogs.Count' => 'Logs',
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
        $gridField = $fields->dataFieldByName('SiteUpdateSteps');
        if($gridField) {

            // $gridField->getConfig()
            //     ->removeComponentsByType(GridFieldDataColumns::class)
            //     ->addComponent(new CustomGridFieldDataColumns());

        }
        $runnerObject = $this->getRunnerObject();
        if($runnerObject) {
            $allSteps = $runnerObject->SubLinks(true);
            $steps = '<ol>';
            foreach ($allSteps as $count => $step) {
                $steps .=
                    '<li>
                        <div style="display: flex;flex-direction: row;justify-content: space-between;"><div>'.$step->getTitle().' - '.$step->getDescription().'</div><div>'.$step->canRunNice()->NiceAndColourfull().'</div></div>
                        <hr />
                    </li>';
            }
            $steps .= '</ol>';
        } else {
            $steps = 'No steps found';
        }

        $fields->addFieldToTab(
            'Root.SiteUpdateSteps',
            ReadonlyField::create(
                'AllStepsHere',
                'All Steps (and if they can run on this site)',
                DBHTMLText::create_field('HTMLText', $steps)
            )
        );

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
        foreach($this->SiteUpdateSteps() as $step) {
            if($step->Status === 'Errors') {
                $step->Status = 'Errors';
            }
        }
        if(! $this->Status) {
            $this->Status = 'Errors';
        }
        $this->fixStartedAndStoppedOnBeforeWriteHelper();
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
            /** @var DataList  */
            $items = $this->SiteUpdateSteps()->filterAny(['Stopped' => false]) ;
            /** @var SiteUpdateStep $step  */
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
        $this->deleteImportantLogs();
        $this->deleteLogFile();
    }


}
