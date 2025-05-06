<?php

namespace Sunnysideup\CronJobs\Model;

use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CronJobs\Api\WorkOutWhatToRunNext;
use Sunnysideup\CronJobs\Traits\InteractionWithLogFile;
use Exception;
use SilverStripe\Forms\ReadonlyField;
use Sunnysideup\CronJobs\Api\SysLoads;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;

class SiteUpdateConfig extends DataObject
{
    use InteractionWithLogFile;

    private static string $table_name = 'SiteUpdateConfig';

    private static string $singular_name = 'Configuration';

    private static string $plural_name = 'Configurations';

    private static $db = [
        'Title' => 'Varchar(255)',
        'StopSiteUpdates' => 'Boolean',
        'LogAllMessagesInDatabase' => 'Boolean',
    ];

    private static $defaults = [
        'Title' => 'Default Site Update Configuration',
    ];

    private static $summary_fields = [
        'Title' => 'Config Name',
        'StopSiteUpdates.NiceAndColourfullInvertedColours' => 'Updates stopped?',
    ];

    private static string $log_file_folder = 'site-update-logs';

    public static function folder_path(): string
    {
        return Director::baseFolder() . '/' . Config::inst()->get(SiteUpdateConfig::class, 'log_file_folder');
    }

    protected static ?SiteUpdateConfig $me = null;

    public static function inst(): SiteUpdateConfig
    {
        if (! self::$me) {
            self::$me = SiteUpdateConfig::get()->first();
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

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName('Title');
        if (! $this->folderPathIsWritable()) {
            $fields->addFieldToTab(
                'Root.Main',
                LiteralField::create(
                    'FolderNotWritable',
                    '<p class="message error">The folder ' . static::folder_path() . ' is not writable. Please make it writable.</p>'
                )
            );
        }
        $outcome = SiteUpdateRecipeBaseClass::can_run_now_based_on_sys_load();
        if ($outcome !== true) {
            $fields->addFieldToTab(
                'Root.Main',
                LiteralField::create(
                    'CanNotRunNow',
                    '<p class="message error">Updates can not run because: ' . $outcome . '.</p>'
                )
            );
        }
        $sysLoad = SysLoads::get_sys_load(true);
        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create(
                    'CurrentRamLoad',
                    'RAM Load',
                    SysLoads::get_ram_usage_as_percent_of_total_available(true)
                ),
                ReadonlyField::create(
                    'sysLoadA',
                    'CPU Usage 1 minute',
                    $sysLoad[0]
                ),
                ReadonlyField::create(
                    'sysLoadB',
                    'CPU Usage 5 minutes',
                    $sysLoad[1]
                ),
                ReadonlyField::create(
                    'sysLoadC',
                    'CPU Usage 15 minutes',
                    $sysLoad[2]
                ),
            ]
        );
        $stopped = $fields->dataFieldByName('StopSiteUpdates');
        if ($stopped) {
            $alwaysRun = [];
            foreach (WorkOutWhatToRunNext::get_recipes() as $obj) {
                if ($obj->runEvenIfUpdatesAreStopped()) {
                    $alwaysRun[] = $obj->getTitle();
                }
            }
            $stopped->setDescription('The following update recipes always run: ' . implode(', ', $alwaysRun) . '.');
        }

        $this->addLogField($fields, 'Root.RawLogs');
        return $fields;
    }

    protected function folderPathIsWritable()
    {
        return is_writable(static::folder_path());
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if (! $this->Title) {
            $defaults = $this->Config()->get('defaults');
            $this->Title = $defaults['Title'] ?? 'Default Site Update Configuration';
        }
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        if (! SiteUpdateConfig::get()->exists()) {
            $obj = SiteUpdateConfig::create();
            $obj->write();
        }
        $folderPath = static::folder_path();
        if (! file_exists($folderPath)) {
            try {
                mkdir($folderPath);
            } catch (Exception $e) {
                //do nothing
            }
        }
    }

    public function logFilePath(): string
    {
        return Controller::join_links(
            self::folder_path(),
            'SiteUpdateConfig_' . $this->ID . '-' . date('Y-m-d') . '-update.log'
        );
    }
}
