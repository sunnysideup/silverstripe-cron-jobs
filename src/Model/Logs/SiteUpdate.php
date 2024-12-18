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
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBHTMLText;
use Sunnysideup\CMSNiceties\Traits\CMSNicetiesTraitForReadOnly;
use Sunnysideup\CronJobs\Api\SiteUpdatesToGraph;
use Sunnysideup\CronJobs\Forms\CustomGridFieldDataColumns;
use Sunnysideup\CronJobs\Forms\SiteUpdateDropdown;
use Sunnysideup\CronJobs\Forms\SiteUpdateDropdownField;
use Sunnysideup\CronJobs\Traits\InteractionWithLogFile;
use Sunnysideup\CronJobs\View\Graph;

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
 * @property int $Attempts
 * @property int $MemoryTaken
 * @property string $RunnerClassName
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep[] SiteUpdateSteps()
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateNote[] ImportantLogs()
 */
class SiteUpdate extends DataObject
{
    use CMSNicetiesTraitForReadOnly;

    use LogTrait;

    use InteractionWithLogFile;

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
        'Attempts' => 'Int',
        'RamLoad' => 'Decimal(3,3)',
        'SysLoadA' => 'Decimal(3,3)',
        'SysLoadB' => 'Decimal(3,3)',
        'SysLoadC' => 'Decimal(3,3)',
        'RunnerClassName' => 'Varchar(255)',
    ];


    private static $has_many = [
        'SiteUpdateSteps' => SiteUpdateStep::class,
        'ImportantLogs' => SiteUpdateNote::class,
    ];

    private static $cascade_deletes = [
        'SiteUpdateSteps',
        'ImportantLogs',
    ];

    private static $field_labels = [
        'Title' => 'Update recipe name',
        'Stopped' => 'Stopped',
        'Created' => 'Started',
        'Description' => 'Description',
        'Status' => 'Status',
        'TimeTaken' => 'Seconds used',
        'MemoryTaken' => 'Megabytes used',
        'SysLoadA' => 'CPU use 1 Minute (1 = 100% CPU use)',
        'SysLoadB' => 'CPU use 5 Minutes (1 = 100% CPU use)',
        'SysLoadC' => 'CPU use 15 Minutes (1 = 100% CPU use)',
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
        'RunnerClassName' => [
            'field' => SiteUpdateDropdownField::class,
            'filter' => 'ExactMatchFilter',
        ],
        'Stopped' => 'ExactMatchFilter',
        'Status' => 'ExactMatchFilter',
        'HasErrors' => 'ExactMatchFilter',
    ];

    private static $defaults = [
        'Attempts' => 1,
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
        $startDate = SiteUpdateStep::get()->min('Created');
        $endDate = SiteUpdateStep::get()->max('Created');
        $fields->addFieldsToTab(
            'Root.Stats',
            [
                LiteralField::create(
                    'ActivityGraphAllTime',
                    Injector::inst()->create(Graph::class)
                        ->setStartDate($startDate)
                        ->setEndDate($endDate)
                        ->addSet($this->getTitle(), SiteUpdatesToGraph::create()->SiteUpdateToGraphData($this->getRunnerObject()))
                        ->setTitle('Activity over time')
                        ->render()
                ),
                LiteralField::create(
                    'ActivityGraph24Hours',
                    Injector::inst()->create(Graph::class)
                        ->setStartDate('-24 hours')
                        ->setEndDate('now')
                        ->addSet($this->getTitle(), SiteUpdatesToGraph::create()->SiteUpdateToGraphData($this->getRunnerObject(), '-24 hours'))
                        ->setTitle('Activity in the last 24 hours')
                        ->render()
                ),
                LiteralField::create(
                    'ActivityGraphLastThreeHours',
                    Injector::inst()->create(Graph::class)
                        ->setStartDate('-3 hours')
                        ->setEndDate('now')
                        ->addSet($this->getTitle(), SiteUpdatesToGraph::create()->SiteUpdateToGraphData($this->getRunnerObject(), '-24 hours'))
                        ->setTitle('Activity in the last 3 hours')
                        ->render()
                ),
            ]
        );
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
            $gridField->setTitle('Steps - last step first');

            // $gridField->getConfig()
            //     ->removeComponentsByType(GridFieldDataColumns::class)
            //     ->addComponent(new CustomGridFieldDataColumns());

        }
        $runnerObject = $this->getRunnerObject();
        if ($runnerObject) {
            $allSteps = $runnerObject->SubLinks(true);
            $steps = '<ul>';
            $number = 0;
            foreach ($allSteps as $step) {
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
            'Root.SiteUpdateSteps',
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
                    'Steps expected to run (in order)',
                    DBHTMLText::create_field('HTMLText', $steps)
                )->performDisabledTransformation(),

            ]
        );

        $this->addLogField($fields, 'Root.RawLogs');

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
        $this->recordsStandardValuesAndFixes(SiteUpdateNote::class);
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
        $this->deleteLogFile();
    }

    public function getProposedSteps(): array
    {
        $runnerObject = $this->getRunnerObject();
        return $runnerObject ? $runnerObject->getProposedSteps() : [];
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
        return $this->SiteUpdateSteps()->filter(['Status' => ['Completed', 'Skipped']])->count();
    }


}
