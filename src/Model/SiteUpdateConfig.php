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
class SiteUpdateConfig extends DataObject
{
    private static $table_name = 'SiteUpdateConfig';

    private static $singular_name = 'Update Recipe Configuration';

    private static $plural_name = 'Update Recipe Configurations';

    protected static $me = [];
    public static function inst()
    {
        if(! self::$me) {
            self::$me = SiteUpdateConfig::get()->first();
        }
        return self::$me;
    }
}
