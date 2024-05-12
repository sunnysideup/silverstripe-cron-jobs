<?php

namespace Sunnysideup\CronJobs\Cms;

use Sunnysideup\CronJobs\Model\BackupCheck;
use Sunnysideup\CronJobs\Model\Logs\ProductsToIgnore;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateConfig;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStepError;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CMSNiceties\Forms\CMSNicetiesLinkButton;

/**
 * Class \Sunnysideup\CronJobs\Cms\SiteUpdatesAdmin
 *
 */
class SiteUpdatesAdmin extends ModelAdmin
{
    private static $managed_models = [
        SiteUpdateConfig::class,
        SiteUpdate::class,
        SiteUpdateStep::class,
        SiteUpdateStepError::class,
        SiteUpdateRunNext::class,
    ];

    private static $url_segment = 'site-updates';

    private static $menu_title = 'Site Updates';

}
