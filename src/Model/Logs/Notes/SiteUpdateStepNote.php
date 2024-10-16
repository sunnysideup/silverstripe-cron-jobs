<?php

namespace Sunnysideup\CronJobs\Model\Logs\Notes;

use Sunnysideup\CronJobs\Cms\SiteUpdatesAdmin;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Traits\NoteTrait;

/**
 * Class \Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateStepNote
 *
 * @property string $Type
 * @property string $Title
 * @property string $Message
 * @property int $SiteUpdateStepID
 * @method \Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep SiteUpdateStep()
 */
class SiteUpdateStepNote extends DataObject
{
    use NoteTrait;

    private static $table_name = 'SiteUpdateStepNote';

    private static $singular_name = 'Step Note';

    private static $plural_name = 'Step Notes';

    private static $db = [
        'Type' => 'Enum("Success,Warning,ERROR","ERROR")',
        'Important' => 'Boolean',
        'Message' => 'Text',
    ];

    private static $has_one = [
        'SiteUpdateStep' => SiteUpdateStep::class,
    ];

    private static $summary_fields = [
        'Created.Ago' => 'When',
        // 'SiteUpdateStep.Title' => 'Update Recipe Step',
        'Type' => 'Type',
        'Title' => 'Subject',
    ];


    public function ParentRel(): string
    {
        return 'SiteUpdateStep';
    }

}
