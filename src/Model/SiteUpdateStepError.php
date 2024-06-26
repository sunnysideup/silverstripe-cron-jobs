<?php

namespace Sunnysideup\CronJobs\Model\Logs;

use Sunnysideup\CronJobs\Cms\SiteUpdatesAdmin;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;

/**
 * Class \Sunnysideup\CronJobs\Model\Logs\SiteUpdateStepError
 *
 * @property string $Type
 * @property string $Title
 * @property string $Message
 * @property int $SiteUpdateID
 * @method \Sunnysideup\CronJobs\Model\Logs\SiteUpdate SiteUpdate()
 */
class SiteUpdateStepError extends DataObject
{
    private static $table_name = 'SiteUpdateStepError';

    private static $singular_name = 'Update Error / Success';

    private static $plural_name = 'Update Errors / Successes';

    private static $db = [
        'Type' => 'Enum("Success,Warning,ERROR","ERROR")',
        'Title' => 'Varchar(255)',
        'Message' => 'Text',
    ];

    private static $has_one = [
        'SiteUpdate' => SiteUpdate::class,
    ];

    private static $summary_fields = [
        'Created.Ago' => 'Started',
        'SiteUpdate.Type' => 'Update Recipe',
        'Type' => 'Type',
        'Title' => 'Subject',
    ];

    private static $default_sort = [
        'ID' => 'DESC',
    ];

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canEdit($member = null)
    {
        return Director::isDev();
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
                ReadonlyField::create('Created', 'When did this error occur?'),
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
    }

    protected function escapedClassNameForAdmin(): string
    {
        return str_replace('\\', '-', $this->ClassName);
    }
}
