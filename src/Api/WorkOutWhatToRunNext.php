<?php

namespace Sunnysideup\CronJobs\Api;

use Sunnysideup\CronJobs\Recipes\UpdateRecipe;

class WorkOutWhatToRunNext
{
    public static function get_next_recipe_to_run(): ?string
    {
        $classes = UpdateRecipe::get_recipes();
        $candidates = [];
        foreach($classes as $class) {
            $obj = $class::inst();
            if($obj->canRunCalculated()) {
                if($obj->IsThereEnoughTimeSinceLastRun()) {
                    array_unshift($candidates, $class);
                } else {
                    $candidates[] = $class;
                }
            }
        }
        if(! empty($candidates)) {
            return array_shift($candidates);
        }
        return null;
    }
}
