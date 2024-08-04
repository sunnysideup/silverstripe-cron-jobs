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


    private static $segment = 'site-update-run';

    protected string $recipe = '';

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
        $stepOrRecipeClassName = null;
        // recipe already set ...
        if(! $this->recipe) {
            // get variable
            $this->recipe = $request->getVar('recipe');
        }
        if($this->recipe) {
            $recipesAvailable = WorkOutWhatToRunNext::get_recipes();
            $stepOrRecipeClassName = $recipesAvailable[$this->recipe] ?? '';
        } else {
            // check if a run next is listed...
            $runNowObj = SiteUpdateRunNext::get()
                ->filter(['RecipeOrStep' => 'Recipe'])
                ->sort(['ID' => 'DESC'])->first();
            if ($runNowObj) {
                $stepOrRecipeClassName = $runNowObj->RunnerClassName;
                $runNowObj->delete();
            } elseif(! $stepOrRecipeClassName) {
                // check out what should run next
                $stepOrRecipeClassName = WorkOutWhatToRunNext::get_next_recipe_to_run();
            }
        }

        if($stepOrRecipeClassName) {
            if (!class_exists($stepOrRecipeClassName)) {
                DB::alteration_message('Could not find Recipe, using CustomRecipe!', 'deleted');
                $stepOrRecipeClassName = CustomRecipe::class;
            }
            $obj = $stepOrRecipeClassName::inst();
            if ($obj) {
                $obj->run($request);
            } else {
                user_error('Could not inst() class ' . $stepOrRecipeClassName);
            }
        }
    }
}
