<?php

namespace Sunnysideup\CronJobs\Cms;

use Sunnysideup\CronJobs\Model\SiteUpdateConfig;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Forms\LiteralField;
use Sunnysideup\CronJobs\Control\SiteUpdateController;
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
        if($this->modelClass === SiteUpdateConfig::class) {
            $fields = $form->Fields();
            $fields->removeByName('Sunnysideup-CronJobs-Model-SiteUpdateConfig');
            $fields->push(
                LiteralField::create(
                    'SiteUpdateConfigInfo',
                    '<p>Please review <a href="'.SiteUpdateController::my_link().'">update details</p>'
                ),
            );
        }
        return $form;
    }

}
