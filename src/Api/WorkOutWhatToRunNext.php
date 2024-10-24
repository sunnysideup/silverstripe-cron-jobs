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
                if ($obj->canRun()) {
                    $array[$class] = $obj;
                }
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

        foreach ($classes as $class => $obj) {
            if ($obj->canRunCalculated($verbose)) {
                $candidates[$class] = $obj->overTimeSinceLastRun();
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


}
