<?php

namespace Sunnysideup\CronJobs\Control;

use PageController;
use SilverStripe\Control\Controller;
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

class SiteUpdateController extends Controller
{
    protected $content = '';

    private static $url_segment = 'admin/site-update-review';

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
            'Description' => 'Mark as completed any updates that are currently running so that new ones can run.',
        ],
        [
            'Title' => 'Clear Cache: flush cache and check database',
            'Link' => 'dev/build/?flush=1',
            'Description' => 'Flush any caches to update what the website shows (e.g. images).',
        ],
        [
            'Title' => 'Do now allow site updates',
            'Link' => 'stopsiteupdates',
            'Description' => 'Stop any updates from starting to run.',
        ],
        [
            'Title' => 'Allow site updates',
            'Link' => 'startsiteupdates',
            'Description' => 'Allow updates to run.',
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
        $doSet = new ArrayList();
        foreach ($array as $key => $item) {
            if(isset($item['Link'])) {
                $item['Link'] = self::my_link($item['Link']);
            }
            $doSet->push(
                new ArrayData(
                    [
                        'Title' => $item['Title'],
                        'Link' => $item['Link'],
                        'Description' => $item['Description'] ?? '',
                    ]
                )
            );
        }
        return $doSet;
    }

    public function AnalysisLinks()
    {
        return AnalysisBaseClass::my_child_links()->sort('Title');
    }

    public function RecipeLinks()
    {
        return SiteUpdateRecipeBaseClass::my_child_links()->sort('Title');
    }

    public function StepLinks()
    {
        return SiteUpdateRecipeStepBaseClass::my_child_links();
    }


    public function CurrentWebsite()
    {
        return Director::protocolAndHost();
    }

    public static function currently_running()
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

    public function CurrentlyRunning(): ArrayList
    {
        return self::currently_running();
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

    public function Link($action = null)
    {
        return Controller::join_links(
            Director::baseURL(),
            $this->config()->get('url_segment'),
            $action
        );
    }

    public static function my_link($action = null)
    {
        return self::singleton()->Link($action);
    }
}
