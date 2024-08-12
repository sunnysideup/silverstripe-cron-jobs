<?php

namespace Sunnysideup\CronJobs\Model\Logs\Notes;

use Sunnysideup\CronJobs\Cms\SiteUpdatesAdmin;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Traits\NoteTrait;

/**
 * Class \Sunnysideup\CronJobs\Model\Logs\SiteUpdateStepNote
 *
 * @property string $Type
 * @property string $Title
 * @property string $Message
 * @property int $SiteUpdateID
 * @method \Sunnysideup\CronJobs\Model\Logs\SiteUpdate SiteUpdate()
 */
class SiteUpdateNote extends DataObject
{
    use NoteTrait;

    private static $table_name = 'SiteUpdateNote';

    private static $singular_name = 'Recipe Error';

    private static $plural_name = 'Recipe Errors';

    private static $db = [
        'Type' => 'Enum("Success,Warning,ERROR","ERROR")',
        'Title' => 'Varchar(50)',
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
    protected function onAfterWrite()
    {
        parent::onAfterWrite();
        if($this->Type === 'ERROR') {
            $this->SiteUpdate()->Status = 'ERROR';
            $this->SiteUpdate()->write();
        }
    }


    public function ParentRel(): string
    {
        return 'SiteUpdate';
    }

}
