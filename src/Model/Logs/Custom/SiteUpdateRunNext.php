<?php

namespace Sunnysideup\CronJobs\Model\Logs\Custom;

use Sunnysideup\CronJobs\Traits\LogTrait;
use SilverStripe\Control\Director;
use SilverStripe\ORM\DataObject;

/**
 * Class \Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext
 *
 * @property string $RecipeOrStep
 * @property string $RunnerClassName
 */
class SiteUpdateRunNext extends DataObject
{
    use LogTrait;


    private static $table_name = 'SiteUpdateRunNext';

    private static $singular_name = 'Manually Run Next';

    private static $plural_name = 'Manually Run Next';

    private static $db = [
        'RecipeOrStep' => 'Enum("Recipe,Step", "Recipe")',
        'RunnerClassName' => 'Varchar(255)',
    ];

    private static $summary_fields = [
        'Created.Ago' => 'Lodged',
        'RecipeOrStep' => 'Type',
        'Title' => 'Title',
    ];

    private static $searchable_fields = [
        'RecipeOrStep' => 'ExactMatchFilter',
    ];

    private static $indexes = [
        //no indexes needed, as we should only have one.
    ];

    private static $default_sort = [
        'ID' => 'DESC',
    ];

    private static $casting = [
        'Title' => 'Varchar',
        'Description' => 'Varchar',
    ];

    private static $field_labels = [

    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $this->addGenericFields($fields);

        return $fields;
    }

    public function canEdit($member = null)
    {
        return Director::isDev();
    }
}
