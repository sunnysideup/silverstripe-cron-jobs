<?php

namespace Sunnysideup\CronJobs;

use PageController;
use Sunnysideup\CronJobs\Analysis\AnalysisBaseClass;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Core\Environment;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use Sunnysideup\CronJobs\Model\SiteUpdateConfig;

class SiteUpdatePageController extends PageController
{
    protected $content = '';

    private static $allowed_actions = [
        'index' => 'ADMIN',
        'runanalysis' => 'ADMIN',
        'runstep' => 'ADMIN',
        'runrecipe' => 'ADMIN',
        'startsiteupdates' => 'ADMIN',
        'stopsiteupdates' => 'ADMIN',
    ];

    private static $emergency_array = [
        [
            'Title' => 'Reset Updates (start again)',
            'Link' => 'dev/tasks/site-update-reset',
        ],
        [
            'Title' => 'Clear Cache: flush cache and check database',
            'Link' => 'dev/build/?flush=1',
        ],
        [
            'Title' => 'Do now allow site updates',
            'Link' => 'admin/siteupdates/stopsiteupdates',
        ],
        [
            'Title' => 'Allow site updates',
            'Link' => 'admin/siteupdates/startsiteupdates',
        ],

    ];

    public function getContent()
    {
        return $this->content;
    }

    public function HasContent(): bool
    {
        return (bool) trim($this->content);
    }

    public function runanalysis($request)
    {
        $this->content = $this->runClassFromRequest($request);

        return [];
    }

    public function runstep($request)
    {
        $this->content = $this->runClassFromRequest($request);

        return [];
    }

    public function runrecipe($request)
    {
        $this->content = $this->runClassFromRequest($request);

        return [];
    }

    public function stopsiteupdates($request)
    {
        $this->setUpdatesOnOrOff(true);

        return [];
    }

    public function startsiteupdates($request)
    {
        $this->setUpdatesOnOrOff(false);

        return [];
    }


    public function EmergencyLinks()
    {
        $array = $this->config()->get('emergency_array');

        return $this->createList($array);
    }

    public function AnalysisLinks()
    {
        return AnalysisBaseClass::my_child_links()->sort('Title');
    }

    public function RecipeLinks()
    {
        return SiteUpdateRecipeBaseClass::my_child_links();
    }

    public function StepLinks()
    {
        return SiteUpdateRecipeStepBaseClass::my_child_links();
    }


    public function CurrentWebsite()
    {
        return Director::protocolAndHost();
    }

    public function CurrentlyRunning(): ArrayList
    {
        $al = ArrayList::create();
        foreach ([SiteUpdate::class, SiteUpdateStep::class] as $className) {
            $items = $className::get()->filter(['Stopped' => false]);
            foreach ($items as $item) {
                $al->push($item);
            }
        }

        return $al;
    }


    protected function getClassFromRequest(HTTPRequest $request): string
    {
        $escapedClass = $request->param('ID');
        $className = str_replace('-', '\\', $escapedClass);

        return class_exists($className) ? $className : '';
    }

    protected function runClassFromRequest($request)
    {
        $className = $this->getClassFromRequest($request);
        if ($className) {
            return $className::run_me($request);
        }

        return $this->httpError(404, 'Could not find class ' . $request->param('ID'));
    }

    public function AllowSiteUpdatesRightNow(): bool
    {
        return ! (bool) SiteUpdateConfig::inst()->StopSiteUpdates;
    }

    protected function setUpdatesOnOrOff(?bool $on = true)
    {
        SiteUpdateConfig::inst()->StopSiteUpdates = $on;
        SiteUpdateConfig::inst()->write();
    }

    protected function createList($array): ArrayList
    {
        $doSet = new ArrayList();
        foreach ($array as $item) {
            $doSet->push(
                new ArrayData(
                    [
                        'Title' => $item['Title'],
                        'Link' => $item['Link'],
                        'Description' => $item['Description'] ?? '',
                        'LastCompleted' => $item['LastCompleted'] ?? '',
                        'HasErrors' => $item['HasErrors'] ?? '',
                        'SubLinks' => $item['SubLinks'] ?? null,
                    ]
                )
            );
        }

        return $doSet;
    }


    protected function init()
    {
        if (! Permission::check('ADMIN')) {
            Security::permissionFailure($this);
        }
        Environment::increaseTimeLimitTo(1200);
        Environment::increaseMemoryLimitTo();
        parent::init();

        Requirements::clear();
    }
}
