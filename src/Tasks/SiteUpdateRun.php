<?php

namespace Sunnysideup\CronJobs\Tasks;

use Sunnysideup\CronJobs\Api\WorkOutWhatToRunNext;
use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Recipes\Entries\CustomRecipe;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\CronJobs\Recipes\Entries\CleanUpSiteUpdatesRecipe;

class SiteUpdateRun extends BuildTask
{
    protected $title = 'Run Site Updates';

    protected $description = '
        Build Task to communicate with the SiteUpdateRecipeBaseClass classes.
        Runs any SiteUpdateRunNext objects (to be deleted afterwards).
        If none, then runs the item set through the recipe "GET" variable. ';

    protected ?string $recipe = '';

    private static $segment = 'site-update-run';


    public function setRecipe(string $recipe): self
    {
        $this->recipe = $recipe;

        return $this;
    }

    protected $cleanupAttempt = 0;

    /**
     * @param mixed $request
     * @return void
     */
    public function run($request)
    {
        error_reporting(E_ERROR | E_PARSE);
        $forceRun = false;
        // recipe already set ...
        if (! $this->recipe) {
            if ($request->getVar('recipe')) {
                // get variable
                $forceRun = true;
                $this->recipe = (string) $request->getVar('recipe');
            }
        }
        if (!$this->recipe) {
            // check if a run next is listed...
            $runNowObj = SiteUpdateRunNext::get()->first();
            if ($runNowObj) {
                if ($runNowObj->RecipeOrStep === 'Step') {
                    $this->recipe = CustomRecipe::class;
                    $runNowObj = null;
                } else {
                    $this->recipe = $runNowObj->RunnerClassName;
                }
                $outcome = $this->doTheActualRun($request, true);
                if ($outcome && $runNowObj) {
                    $runNowObj->delete();
                }
            } elseif (! $this->recipe) {
                // check out what should run next
                $this->recipe = WorkOutWhatToRunNext::get_next_recipe_to_run(true);
            }
        }
        if ($this->recipe) {
            $outcome = $this->doTheActualRun($request, $forceRun);
        }
        if ($outcome) {
            echo PHP_EOL . 'RAN: '. $this->recipe . PHP_EOL;
        } else {
            if ($this->cleanupAttempt < 3 && $this->recipe !== CleanUpSiteUpdatesRecipe::class) {
                $this->cleanupAttempt++;
                $this->recipe = CleanUpSiteUpdatesRecipe::class;
                echo PHP_EOL . 'RETRYING WITH: '. $this->recipe . PHP_EOL;
                $this->doTheActualRun($request, $forceRun);
            } else {
                echo PHP_EOL . 'NOTHING HAS BEEN RUN'.  PHP_EOL;
            }
        }
    }

    protected function doTheActualRun($request, bool $forceRun = false): bool
    {
        if (!class_exists($this->recipe)) {
            DB::alteration_message('Could not find Recipe, using CustomRecipe!', 'deleted');
            $this->recipe = CustomRecipe::class;
        }
        $className = $this->recipe;
        $obj = $className::inst();
        if ($obj) {
            if ($forceRun) {
                $obj->setIgnoreAll(true);
            }
            return $obj->run($request);
        } else {
            user_error('Could not inst() class ' . $this->recipe);
        }
        return false;
    }

}
