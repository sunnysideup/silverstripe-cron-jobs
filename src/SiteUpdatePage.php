<?php

namespace Sunnysideup\Crob;

use Page;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Security\Permission;

/**
 * class that controls all migration and updates
 *
 */
class SiteUpdateUpdatePgae extends Page
{
    private static $can_be_root = true;

    private static $icon = '';

    private static $description = 'Review data - ADMINS only';

    private static $table_name = 'SiteUpdateUpdatePgae';

    /**
     * Standard SS variable.
     */
    private static $singular_name = 'Data Update Page';

    /**
     * Standard SS variable.
     */
    private static $plural_name = 'Data Update Pages';

    private static $defaults = [
        'URLSegment' => 'admin-update',
        'ShowInMenus' => '0',
        'ShowInSearch' => '0',
    ];

    public function i18n_singular_name()
    {
        return _t('DataUpdatePage.SINGULARNAME', 'Data Update Page');
    }

    public function i18n_plural_name()
    {
        return _t('DataUpdatePage.PLURALNAME', 'Data Update Pages');
    }

    public function canView($member = null)
    {
        return $this->canUse($member);
    }

    public function canEdit($member = null, $context = [])
    {
        return $this->canUse($member);
    }

    public function canDelete($member = null)
    {
        return $this->canUse($member);
    }

    public function canCreate($member = null, $context = [])
    {
        return SiteTree::get()->filter(['ClassName' => SiteUpdateUpdatePgae::class])->exists() ? false : parent::canCreate($member, $context);
    }

    public function canUse($member = null)
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
