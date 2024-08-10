<?php

namespace Sunnysideup\CronJobs\Tasks;

use Sunnysideup\CronJobs\Api\WorkOutWhatToRunNext;
use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Recipes\Entries\CustomRecipe;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class SiteUpdateRun extends BuildTask
{
    protected $title = 'Run Site Updates';

    protected $description = '
        Build Task to communicate with the SiteUpdateRecipeBaseClass classes.
        Runs any SiteUpdateRunNext objects (to be deleted afterwards).
        If none, then runs the item set through the recipe "GET" variable. ';

    protected string $recipe = '';

    private static $segment = 'site-update-run';


    public function setRecipe(string $recipe): self
    {
        $this->recipe = $recipe;

        return $this;
    }

    /**
     * @param mixed $request
     * @return void
     */
    public function run($request)
    {
        $forceRun = true;
        // recipe already set ...
        if(! $this->recipe) {
            // get variable
            $this->recipe = (string) $request->getVar('recipe');
        }
        if(!$this->recipe) {
            // check if a run next is listed...
            $runNowObj = SiteUpdateRunNext::get()
                ->filter(['RecipeOrStep' => 'Recipe'])
                ->sort(['ID' => 'DESC'])->first();
            if ($runNowObj) {
                $this->recipe = $runNowObj->RunnerClassName;
                $runNowObj->delete();
            } elseif(! $this->recipe) {
                // check out what should run next
                $forceRun = false;
                $this->recipe = WorkOutWhatToRunNext::get_next_recipe_to_run();
            }
        }
        if($this->recipe) {
            if (!class_exists($this->recipe)) {
                DB::alteration_message('Could not find Recipe, using CustomRecipe!', 'deleted');
                $this->recipe = CustomRecipe::class;
            }
            $obj = $this->recipe::inst();
            if ($obj) {
                if($forceRun) {
                    $obj->setIgnoreLastRanAndTimeOfDay(true);
                }
                $obj->run($request);
            } else {
                user_error('Could not inst() class ' . $this->recipe);
            }
        }
    }
}
