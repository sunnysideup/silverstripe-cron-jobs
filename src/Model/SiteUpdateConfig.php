<?php

namespace Sunnysideup\CronJobs\Model;

use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use Sunnysideup\CronJobs\Traits\LogTrait;
use SilverStripe\Control\Director;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CMSNiceties\Traits\CMSNicetiesTraitForReadOnly;

class SiteUpdateConfig extends DataObject
{
    private static string $table_name = 'SiteUpdateConfig';

    private static string $singular_name = 'Configuration';

    private static string $plural_name = 'Configurations';

    private static string $log_file_folder = 'site-update-logs';

    protected static $me = [];
    public static function inst()
    {
        if(! self::$me) {
            self::$me = SiteUpdateConfig::get()->first();
            if(! self::$me) {
                self::$me = SiteUpdateConfig::create();
                self::$me->write();
            }
        }
        return self::$me;
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function canCreate($member = null, $context = [])
    {
        return SiteUpdateConfig::get()->count() ? false : parent::canCreate($member, $context);
    }

}
