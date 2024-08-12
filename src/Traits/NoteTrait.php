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
        $this->Title = substr((string) $this->Message, 0, 49);
    }

    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        if($this->Type === 'ERROR') {
            $parentRel = $this->ParentRel();
            $this->$parentRel()->Status = 'Errors';
            $this->$parentRel()->write();
        }
    }


}
