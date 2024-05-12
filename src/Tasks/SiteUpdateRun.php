<?php

namespace Sunnysideup\CronJobs\Tasks;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Recipes\Entries\CustomRecipe;
use Sunnysideup\CronJobs\Recipes\UpdateRecipe;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class SiteUpdateRun extends BuildTask
{
    protected $title = 'Run Site Updates';

    protected $description = '
        Build Task to communicate with the UpdateRecipe classes.
        Runs any SiteUpdateRunNext objects (to be deleted afterwards).
        If none, then runs the item set through the recipe "GET" variable. ';

    protected $recipe = '';

    private static $segment = 'SiteUpdateRun';

    public function setRecipe(string $recipe): self
    {
        $this->recipe = $recipe;

        return $this;
    }

    public function run($request)
    {
        $runNowObjects = SiteUpdateRunNext::get()->filter(['RecipeOrStep' => 'Step']);
        if ($runNowObjects->exists()) {
            $apiRecipeClassName = CustomRecipe::class;
        } else {
            $runNowObj = SiteUpdateRunNext::get()->filter(['RecipeOrStep' => 'Recipe'])->sort(['ID' => 'DESC'])->first();
            $apiRecipeClassName = null;
            if ($runNowObj) {
                $apiRecipeClassName = $runNowObj->RunnerClassName;
                $runNowObj->delete();
            } else {
                $this->recipe = $request->getVar('recipe');
                if (! $this->recipe) {
                    DB::alteration_message('You have not specified which recipe you want to run, please add "?recipe=recipename" to your url.', 'deleted');
                }

                $recipesAvailable = UpdateRecipe::get_recipes();
                $apiRecipeClassName = $recipesAvailable[$this->recipe] ?? '';
            }
        }

        if (! ($apiRecipeClassName && class_exists($apiRecipeClassName))) {
            DB::alteration_message('Could not find Recipe, using CustomRecipe!', 'deleted');
            $apiRecipeClassName = CustomRecipe::class;
        }

        $obj = $apiRecipeClassName::inst();
        if ($obj) {
            $obj->run($request);
        } else {
            user_error('Could not inst() class ' . $apiRecipeClassName);
        }
    }
}
