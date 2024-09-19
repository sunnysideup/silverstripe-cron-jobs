<?php

namespace Sunnysideup\CronJobs\Model;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CronJobs\Api\WorkOutWhatToRunNext;

class SiteUpdateConfig extends DataObject
{
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
            if (! self::$me) {
                self::$me = SiteUpdateConfig::create();
                self::$me->write();
            }
        }
        $folderPath = static::folder_path();
        if (! file_exists($folderPath)) {
            try {
                mkdir($folderPath);
            } catch (\Exception $e) {
                //do nothing
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
        $stopped = $fields->dataFieldByName('xx');
        if ($stopped) {
            $alwaysRun = [];
            foreach (WorkOutWhatToRunNext::get_recipes() as $recipe) {
                if ($recipe->runEvenIfUpdatesAreStopped()) {
                    $alwaysRun[] = $recipe->getTitle();
                }
            }
            $stopped->setDescription('The following update recipes always run: ' . implode(', ', $alwaysRun) .'.');
        }
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

}
