<?php

namespace Sunnysideup\CronJobs\Model\Logs\Custom;

use Sunnysideup\CronJobs\Traits\LogTrait;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\DataObject;
use Sunnysideup\CronJobs\Cms\SiteUpdatesAdmin;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;

/**
 * Class \Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext
 *
 * @property string $RecipeOrStep
 * @property string $RunnerClassName
 */
class SiteUpdateRunNext extends DataObject
{
    private static $table_name = 'SiteUpdateRunNext';

    private static $singular_name = 'Manually Run Next';

    private static $plural_name = 'Manually Run Next';

    private static $db = [
        'RecipeOrStep' => 'Enum("Recipe,Step", "Recipe")',
        'RunnerClassName' => 'Varchar(255)',
    ];

    private static $summary_fields = [
        'RecipeOrStep' => 'Type',
        'Title' => 'Title',
        'Created.Ago' => 'Lodged',
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
        $fields->replaceField(
            'RunnerClassName',
            ReadonlyField::create('Title', 'Title')
        );
        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create('Description', 'Description'),
            ]
        );
        $fields->addFieldsToTab(
            'Root.Main',
            [
                ReadonlyField::create('CreatedNice', 'Lodged', $this->dbObject('Created')->Ago()),
            ]
        );
        return $fields;
    }

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canEdit($member = null)
    {
        return false;
    }

    public function getTitle()
    {
        $object = $this->getRunnerObject();
        if (! $object) {
            return 'ERROR: RunnerClassName not found';
        }
        return $object->getTitle();
    }

    public function getDescription()
    {
        $object = $this->getRunnerObject();
        if (! $object) {
            return 'ERROR: RunnerClassName not found';
        }
        return $object->getDescription();
    }

    public function getRunnerObject()
    {
        $className = $this->RunnerClassName;
        if ($className && class_exists((string) $className)) {
            return $className::inst();
        }
        return null;
    }

    public function CMSEditLink(): string
    {
        return Injector::inst()->get(SiteUpdatesAdmin::class)->getCMSEditLinkForManagedDataObject($this);
    }
}
