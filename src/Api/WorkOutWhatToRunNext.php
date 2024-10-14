<?php

namespace Sunnysideup\CronJobs\Api;

use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Recipes\Entries\CustomRecipe;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;

class WorkOutWhatToRunNext
{
    public static function get_recipes(?bool $includeCustom = false): array
    {
        $classes = ClassInfo::subClassesFor(SiteUpdateRecipeBaseClass::class, false);
        $array = [];
        foreach ($classes as $class) {
            if ($class !== CustomRecipe::class || $includeCustom) {
                $obj = $class::inst();
                $array[$class] = $obj;
            }
        }

        return $array;
    }
    public static function get_recipe_steps(): array
    {
        $classes = ClassInfo::subClassesFor(SiteUpdateRecipeStepBaseClass::class, false);
        $array = [];
        foreach ($classes as $class) {
            $obj = $class::inst();
            $array[$class] = $obj;
        }

        return $array;
    }

    public static function get_next_recipe_to_run(?bool $verbose = false): ?string
    {
        $classes = self::get_recipes();
        $candidates = [];

        foreach ($classes as $obj) {
            if ($obj->canRunCalculated($verbose)) {
                $candidates[$obj->ClassName] = $obj->overTimeSinceLastRun();
            }
        }
        // if any of them are over then return task that is over by the most
        // else return the last candidate.
        if (! empty($candidates)) {
            asort($candidates);
            $candidateKeys = array_keys($candidates);
            return array_pop($candidateKeys);
        }
        if ($verbose) {
            echo 'No recipes to run';
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
                $singleton->logAnything('Checking for ' . $singleton->i18n_singular_name() . ' not STOPPED and marking them as NotCompleted.');
                $mustBeCreatedBeforeDate = date(
                    'Y-m-d H:i:s',
                    strtotime('-' . $minutes . ' minutes')
                );
                $filter = [
                    'Stopped' => false,
                    'Created:LessThan' => $mustBeCreatedBeforeDate,
                ];
                $singleton->logAnything(
                    'Checking for ' . Injector::inst()->get($className)->i18n_plural_name() .
                    ' started before ' . $mustBeCreatedBeforeDate . ' (' . $minutes . ' minutes ago) and marking them as NotCompleted.'
                );
            }

            $logs = $className::get()->filter($filter);
            foreach ($logs as $log) {
                $log->logAnything('Found: -- ' . $log->getTitle() . ' with ID ' . $log->ID . '  -- ... marking as NotCompleted.');
                $log->Status = 'NotCompleted';
                $log->Stopped = true;
                $log->write();
            }
        }
    }
}
