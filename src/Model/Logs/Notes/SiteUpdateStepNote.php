<?php

namespace Sunnysideup\CronJobs\Model\Logs\Notes;

use Sunnysideup\CronJobs\Cms\SiteUpdatesAdmin;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;

/**
 * Class \Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateStepNote
 *
 * @property string $Type
 * @property string $Title
 * @property string $Message
 * @property int $SiteUpdateStepID
 * @method \Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep SiteUpdateStep()
 */
class SiteUpdateStepNote extends DataObject
{
    private static $table_name = 'SiteUpdateStepNote';

    private static $singular_name = 'Update Step Note';

    private static $plural_name = 'Update Step Notes';

    private static $db = [
        'Type' => 'Enum("created,deleted,changed,ERROR","ERROR")',
        'Title' => 'Varchar(50)',
        'Message' => 'Text',
    ];

    private static $has_one = [
        'SiteUpdateStep' => SiteUpdateStep::class,
    ];

    private static $summary_fields = [
        'Created.Ago' => 'When',
        // 'SiteUpdateStep.Title' => 'Update Recipe Step',
        'Type' => 'Type',
        'Title' => 'Subject',
    ];

    private static $default_sort = [
        'ID' => 'ASC',
    ];

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canEdit($member = null)
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create('Created', 'When'),
            ]
        );

        //...

        return $fields;
    }

    public function CMSEditLink(): string
    {
        return Injector::inst()->get(SiteUpdatesAdmin::class)->getCMSEditLinkForManagedDataObject($this);
    }

    protected function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $this->Message = strip_tags((string) $this->Message);
        $this->Title = substr((string) $this->Message, 0, 49);
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        if($this->Type === 'ERROR') {
            $this->SiteUpdate()->Status = 'ERROR';
            $this->SiteUpdate()->write();
        }
    }
}
