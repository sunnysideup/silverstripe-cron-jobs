<?php

namespace Sunnysideup\CronJobs\Cms;

use SilverStripe\Core\Injector\Injector;
use Sunnysideup\CronJobs\Model\SiteUpdateConfig;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\LiteralField;
use SilverStripe\ORM\ArrayList;
use Sunnysideup\CronJobs\Api\WorkOutWhatToRunNext;
use Sunnysideup\CronJobs\Control\SiteUpdateController;
use Sunnysideup\CronJobs\Forms\CustomGridFieldDataColumns;
use Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateNote;
use Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateStepNote;

/**
 * Class \Sunnysideup\CronJobs\Cms\SiteUpdatesAdmin
 *
 */
class SiteUpdatesAdmin extends ModelAdmin
{
    private static $managed_models = [
        SiteUpdateConfig::class,
        SiteUpdateRunNext::class,
        SiteUpdate::class,
        SiteUpdateNote::class,
        SiteUpdateStep::class,
        SiteUpdateStepNote::class,
    ];

    private static $url_segment = 'site-updates';

    private static $menu_title = 'Site Updates';

    public function init()
    {
        parent::init();
        $this->showImportForm = false;
    }

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);
        if($this->modelClass === SiteUpdate::class || $this->modelClass === SiteUpdateStep::class) {
            $gridField = $form->Fields()->fieldByName($this->sanitiseClassName($this->modelClass));

            $config = $gridField->getConfig();

            // Remove the default GridFieldDataColumns
            $config->removeComponentsByType(GridFieldDataColumns::class);

            // Add the custom GridFieldDataColumns
            $config->addComponent(new CustomGridFieldDataColumns());

        }
        if($this->modelClass === SiteUpdateConfig::class) {
            $fields = $form->Fields();

            $runners = WorkOutWhatToRunNext::get_recipes();
            $htmlLeft = $this->renderWith('Sunnysideup/CronJobs/Includes/CurrentlyRunning');
            $htmlLeft .= $this->renderWith('Sunnysideup/CronJobs/Includes/RunningNext');
            $htmlRight = '<h2>List of Site Update Recipes</h2>';
            foreach($runners as $shortClassName => $className) {
                $obj = $className::inst();
                if($obj) {
                    $lastRunHadErrorsSymbol = $obj->LastRunHadErrorsSymbol();
                    $htmlRight .= '
                        <h3>
                          <a href="'.$obj->CMSEditLink().'" target="_blank">'.$obj->getTitle().'</a>: '.$obj->getDescription().'.
                          <br />'. $lastRunHadErrorsSymbol . ' - Last completed: '.$obj->LastCompletedNice().'.
                          It is '.($obj->IsMeetingTarget() ? '' : ' NOT ').' meeting its schedule targets.
                          <a href="'.$obj->Link().'" target="_blank">Run next?</a>
                        </h3>
                        ';
                }
            }

            $htmlRight .= '
                <h3><br /><a href="'.SiteUpdateController::my_link().'" target="_blank">Open Full Review</a></h3>';
            $fields->push(
                LiteralField::create(
                    'CurrentlyRunning',
                    '<div style="display: flex;flex-direction: row;justify-content: space-between;"><div>' . $htmlLeft . '</div><div>'. $htmlRight . '</div>'
                )
            );
        }
        return $form;
    }

    public function CurrentlyRunning(): ArrayList
    {
        return SiteUpdateController::currently_running();
    }

    public function RunningNext()
    {
        return SiteUpdateController::running_next();
    }

    public function CustomRunNext()
    {
        return SiteUpdateController::custom_running_next();
    }

}
