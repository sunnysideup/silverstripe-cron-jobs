<?php

namespace Sunnysideup\CronJobs\Model\Logs;

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
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep[] SiteUpdateStep()
 * @method \SilverStripe\ORM\DataList|\Sunnysideup\CronJobs\Model\Logs\SiteUpdateStepError[] SiteUpdateStepErrors()
 */
class SiteUpdate extends DataObject
{
    use CMSNicetiesTraitForReadOnly;

    use LogTrait;

    use LogSuccessAndErrorsTrait;

    private static $table_name = 'SiteUpdate';

    private static $singular_name = 'Update Recipe Log Entry';

    private static $plural_name = 'Update Recipe Log Entries';

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
        'SiteUpdateStep.Count' => 'Steps',
        'SiteUpdateStepErrors.Count' => 'Errors',
    ];

    private static $field_labels = [
        'Title' => 'Update Recipe',
        'Stopped' => 'Stopped',
        'Created' => 'Started',
        'Description' => 'Description',
        'Status' => 'Status',
        'TimeTaken' => 'Seconds Used',
        'MemoryTaken' => 'Megabytes Used',
        'SiteUpdateStep' => 'Steps',
        'SiteUpdateStepErrors' => 'Errors',
    ];

    private static $has_many = [
        'SiteUpdateStep' => SiteUpdateStep::class,
        'SiteUpdateStepErrors' => SiteUpdateStepError::class,
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
        $this->addGenericFields($fields);
        if ($this->ErrorLog) {
            $data = $this->ErrorLog;
            $source = 'Saved';
        } else {
            $data = $this->getLogContent();
            $source = $this->logFileName();
        }

        $logField = LiteralField::create(
            'Logs',
            '<h2>Response from the lastest update only - stored in (' . $source . ')</h2>
            <div style="background-color: #300a24; padding: 20px; height: 600px; overflow-y: auto;">' . $this->getLogContent() . '</div>'
        );
        $fields->addFieldsToTab(
            'Root.Log',
            [
                $logField,
            ]
        );
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

        if ($logError) {
            $this->logError($contents, true);
        }
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        if ($this->Stopped) {
            /** @var DataList[SiteUpdateStep]  */
            $items = $this->SiteUpdateStep()->filterAny(['Stopped' => false]) ;
            foreach ($items as $step) {
                $step->Stopped = true;
                $step->Status = 'NotCompleted';
                $step->write();
            }
        }
    }

    protected function hasErrorInLog(string $contents): bool
    {
        $needle = '[Emergency]';

        return strpos($contents, $needle);
    }

    protected function getLogContent(): string
    {
        $fileName = $this->logFileName();
        if (file_exists($fileName)) {
            return $this->bashColorToHtml(file_get_contents($fileName));
        }

        return 'no file found.';
    }

    protected function logFileName(): string
    {
        $type = strtolower($this->Type);

        return Director::baseFolder() . '/updatelogs/' . $type . '-recipe-update.log';
    }

}
