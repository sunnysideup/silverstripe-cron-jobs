<?php

namespace Sunnysideup\CronJobs\Model\Logs;

use GuzzleHttp\Psr7\Header;
use SilverStripe\Control\Controller;
use Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateNote;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use Sunnysideup\CronJobs\Traits\LogTrait;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\HeaderField;
use SilverStripe\Forms\HTMLEditor\HTMLEditorField;
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
 * @property int $TotalStepsErrors
 * @property int $TimeTaken
 * @property int $MemoryTaken
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
        'Status' => 'Enum("Started,NotCompleted,Completed,Skipped,Shortened","Started")',
        'Type' => 'Varchar(255)',
        'HasErrors' => 'Boolean',
        'Errors' => 'Int',
        'TotalStepsErrors' => 'Int',
        'NumberOfStepsExpectecToRun' => 'Int',
        'TimeTaken' => 'Int',
        'MemoryTaken' => 'Int',
        'RunnerClassName' => 'Varchar(255)',
    ];


    private static $has_many = [
        'SiteUpdateSteps' => SiteUpdateStep::class,
        'ImportantLogs' => SiteUpdateNote::class,
    ];


    private static $field_labels = [
        'Title' => 'Update recipe name',
        'Stopped' => 'Stopped',
        'Created' => 'Started',
        'Description' => 'Description',
        'Status' => 'Status',
        'TimeTaken' => 'Seconds used',
        'MemoryTaken' => 'Megabytes used',
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
        'HasErrors.NiceAndColourfullInvertedColours' => 'Errors',
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
        'HasErrors' => 'ExactMatchFilter',
    ];

    private static $default_sort = [
        'Stopped' => 'ASC',
        'ID' => 'DESC',
    ];

    private static $casting = [
        'Title' => 'Varchar',
        'Description' => 'Varchar',
        'Minutes' => 'Varchar',
        'TimeNice' => 'Varchar',
        'PercentageComplete' => 'Percentage',
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        // add generic fields
        $this->addGenericFields($fields);
        $fields->addFieldToTab(
            'Root.ImportantLogs',
            ReadonlyField::create(
                'TotalStepsErrors',
                'Errors in individual steps',
            ),
            'ImportantLogs'
        );
        $gridField = $fields->dataFieldByName('SiteUpdateSteps');
        if ($gridField) {

            // $gridField->getConfig()
            //     ->removeComponentsByType(GridFieldDataColumns::class)
            //     ->addComponent(new CustomGridFieldDataColumns());

        }
        $runnerObject = $this->getRunnerObject();
        if ($runnerObject) {
            $allSteps = $runnerObject->SubLinks(true);
            $steps = '<ul>';
            $number = 0;
            foreach ($allSteps as $count => $step) {
                if ($step->canRun() === false) {
                    continue;
                }
                $number++;
                $steps .=
                    '<li>
                    <div style="display: flex;flex-direction: row;justify-content: space-between; ">
                            <div><strong>'.($number).'. '.$step->getTitle().'</strong><br>'.$step->getDescription().'</div>
                        </div>
                        <hr />
                    </li>';
            }
            $steps .= '</ul>';
        } else {
            $steps = 'No steps found';
        }

        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create(
                    'PercentageCompleteNice',
                    'Precentage complete',
                    (round($this->getPercentageComplete(), 2) * 100) . '%'
                ),
            ]
        );

        $fields->addFieldsToTab(
            'Root.SiteUpdateSteps',
            [
                HeaderField::create(
                    'ExpectedSteps',
                    'What is expected to happen in this recipe?'
                ),
                ReadonlyField::create(
                    'NumberOfStepsExpectecToRun',
                    'Number of steps expected to run',
                ),
                HTMLEditorField::create(
                    'AllStepsHere',
                    'Steps expected to run',
                    DBHTMLText::create_field('HTMLText', $steps)
                )->performDisabledTransformation(),

            ]
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
        $this->TotalStepsErrors = 0;
        /** @var SiteUpdateStep $step */
        foreach ($this->SiteUpdateSteps()->filter('HasErrors', true) as $step) {
            $this->TotalStepsErrors += $step->Errors;
        }
        if (! $this->Status) {
            $this->Status = $this->Stopped ? 'NotCompleted' : 'Started';
        }
        $this->recordErrorsOnBeforeWrite(SiteUpdateNote::class);
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

    public function getProposedSteps(): array
    {
        $runnerObject = $this->getRunnerObject();
        $steps = $runnerObject ? $runnerObject->getSteps() : [];
        foreach ($steps as $key => $step) {
            $singleton = Injector::inst()->get($step);
            if ($singleton->canRun() !== true) {
                unset($steps[$key]);
            }
        }
        return $steps;
    }

    public function getPercentageComplete(): float
    {
        if ($this->NumberOfStepsExpectecToRun === 0) {
            $proposedStepsCount = count($this->getProposedSteps());
            $this->NumberOfStepsExpectecToRun = $proposedStepsCount;
        }
        if ($this->NumberOfStepsExpectecToRun === 0) {
            return 0;
        }
        return $this->getNumberOfStepsRan() / $this->NumberOfStepsExpectecToRun;
    }

    public function getNumberOfStepsRan(): int
    {
        return $this->SiteUpdateSteps()->filter(['Status' => 'Completed'])->count();
    }


}
