<?php

namespace Sunnysideup\CronJobs\Traits;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ReadonlyField;
use Sunnysideup\CronJobs\Cms\SiteUpdatesAdmin;

trait NoteTrait
{
    private static $default_sort = [
        'ID' => 'DESC',
    ];

    private static $casting = [
        'Title' => 'Varchar',
    ];

    public function getTitle()
    {
        return substr((string) $this->Message, 0, 49) . '...';
    }

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
                ReadonlyField::create('Created', 'When did this occur?'),
            ]
        );
        $fields->removeByName('Title');
        $fields->dataFieldByName('Important')->setTitle('Is this an important note?');

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


}
