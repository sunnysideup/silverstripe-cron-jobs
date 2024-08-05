<?php

namespace Sunnysideup\CronJobs;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Security\Permission;

/**
 * class that controls all migration and updates
 *
 */
class SiteUpdatePage extends Page
{
    private static $can_be_root = true;

    private static $icon = '';

    private static $description = 'Allows you review the Site Updates';

    private static $table_name = 'SiteUpdatePage';

    /**
     * Standard SS variable.
     */
    private static $singular_name = 'Site Update Page';

    /**
     * Standard SS variable.
     */
    private static $plural_name = 'Site Update Pages';

    private static $defaults = [
        'URLSegment' => 'site-update-page',
        'ShowInMenus' => '0',
        'ShowInSearch' => '0',
    ];

    public function canView($member = null)
    {
        return $this->hasEditRights($member);
    }

    public function canEdit($member = null, $context = [])
    {
        return $this->hasEditRights($member);
    }

    public function canDelete($member = null)
    {
        return $this->hasEditRights($member);
    }

    public function canCreate($member = null, $context = [])
    {
        return SiteTree::get()->filter(['ClassName' => SiteUpdatePage::class])->exists() ? false : parent::canCreate($member, $context);
    }

    protected function hasEditRights($member = null)
    {
        return Permission::check('ADMIN');
    }

    protected function onBeforeWrite()
    {
        $defaults = $this->config()->get('defaults');
        $this->URLSegment = $defaults['URLSegment'] ?? 'admin-update';
        $this->ParentID = 0;
        parent::onBeforeWrite();
    }
}
