<?php

namespace Sunnysideup\CronJobs\Api;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;

class WorkOutWhatToRunNext
{
    public static function get_recipes(): array
    {
        $classes = ClassInfo::subClassesFor(SiteUpdateRecipeBaseClass::class, false);
        $array = [];
        foreach ($classes as $class) {
            $obj = $class::inst();
            $array[$obj->getShortClassCode()] = $class;
        }

        return $array;
    }
    public static function get_next_recipe_to_run(): ?string
    {
        $classes = self::get_recipes();
        $candidates = [];
        foreach($classes as $class) {
            $obj = $class::inst();
            if($obj->canRunCalculated()) {
                $candidates[$class] = $obj->overTimeSinceLastRun();
            }
        }
        // if any of them are over then return task that is over by the most
        // else return the last candidate.
        if(! empty($candidates)) {
            asort($candidates);
            return array_pop(array_keys($candidates));
        }
        return null;
    }


    public static function stop_recipes_and_tasks_running_too_long(?bool $clearAll = false)
    {
        $array = [
            SiteUpdate::class => Config::inst()->get(SiteUpdateRecipeBaseClass::class, 'max_execution_minutes_recipes'),
            SiteUpdateStep::class => Config::inst()->get(SiteUpdateRecipeBaseClass::class, 'max_execution_minutes_steps'),
        ];
        foreach ($array as $className => $minutes) {
            if ($clearAll) {
                $filter = [
                    'Stopped' => false,
                ];
            } else {
                $singleton = Injector::inst()->get($className);
                LogSuccessAndErrorsTrait::log_anything('Checking for ' . $singleton->i18n_singular_name() . ' not STOPPED and marking them as NotCompleted.');
                $mustBeCreatedBeforeDate = date(
                    'Y-m-d H:i:s',
                    strtotime('-' . $minutes . ' minutes')
                );
                $filter = [
                    'Stopped' => false,
                    'Created:LessThan' => $mustBeCreatedBeforeDate,
                ];
                LogSuccessAndErrorsTrait::log_anything(
                    'Checking for ' . Injector::inst()->get($className)->i18n_plural_name() .
                    ' started before ('.'-' . $minutes . ' minutes'.')' . $mustBeCreatedBeforeDate . ' and marking them as NotCompleted.'
                );
            }

            $logs = $className::get()->filter($filter);
            foreach ($logs as $log) {
                LogSuccessAndErrorsTrait::log_anything('Found: -- ' . $log->getTitle() . ' with ID ' . $log->ID . '  -- ... marking as NotCompleted.');
                $log->Status = 'NotCompleted';
                $log->Stopped = true;
                $log->write();
            }
        }
    }
}
