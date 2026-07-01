<?php

namespace Sunnysideup\CronJobs\Tasks;

use Override;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\CronJobs\Api\WorkOutWhatToRunNext;
use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Recipes\Entries\CleanUpSiteUpdatesRecipe;
use Sunnysideup\CronJobs\Recipes\Entries\CustomRecipe;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use SilverStripe\PolyExecution\PolyOutput;

class SiteUpdateRun extends BuildTask
{
    protected string $title = 'Run Site Updates';

    protected static string $description = '
        Build Task to communicate with the SiteUpdateRecipeBaseClass classes.
        Runs any SiteUpdateRunNext objects (to be deleted afterwards).
        If none, then runs the item set through the recipe "GET" variable. ';

    protected ?string $recipe = '';

    protected static string $commandName = 'site-update-run';


    public function setRecipe(string $recipe): self
    {
        $this->recipe = $recipe;

        return $this;
    }

    protected $cleanupAttempt = 0;

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        error_reporting(E_ERROR | E_PARSE);
        // allow the database to be around for longer
        DB::query('SET SESSION wait_timeout=1200;');
        $forceRun = false;
        // recipe already set ...
        if (!$this->recipe && $input->getOption('recipe')) {
            // get variable
            $forceRun = true;
            $this->recipe = (string) $input->getOption('recipe');
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

                $outcome = $this->doTheActualRun($input, $output, true);
                if ($outcome && $runNowObj) {
                    $runNowObj->delete();
                }
            } elseif (! $this->recipe) {
                // check out what should run next
                $this->recipe = WorkOutWhatToRunNext::get_next_recipe_to_run(true);
            }
        }

        if ($this->recipe) {
            $outcome = $this->doTheActualRun($input, $output, $forceRun);
        }

        if ($outcome) {
            $output->writeln(PHP_EOL . 'RAN: ' . $this->recipe . PHP_EOL);
        } elseif ($this->cleanupAttempt < 3 && $this->recipe !== CleanUpSiteUpdatesRecipe::class) {
            $this->cleanupAttempt++;
            $this->recipe = CleanUpSiteUpdatesRecipe::class;
            $output->writeln(PHP_EOL . 'RETRYING WITH: ' . $this->recipe . PHP_EOL);
            $this->doTheActualRun($input, $output, $forceRun);
        } else {
            $output->writeln(PHP_EOL . 'NOTHING HAS BEEN RUN' .  PHP_EOL);
        }

        return Command::SUCCESS;
    }

    protected function doTheActualRun(InputInterface $input, PolyOutput $output, bool $forceRun = false): bool
    {
        if (!class_exists($this->recipe)) {
            $output->writeln('Could not find Recipe, using CustomRecipe!');
            $this->recipe = CustomRecipe::class;
        }

        $className = $this->recipe;
        $obj = $className::inst();
        if ($obj) {
            if ($forceRun) {
                $obj->setIgnoreAll(true);
            }

            return $obj->run($input);
        } else {
            user_error('Could not inst() class ' . $this->recipe);
        }

        return false;
    }

    #[Override]
    public function getOptions(): array
    {
        return [new InputOption('recipe', 'r', InputOption::VALUE_OPTIONAL, 'do something specific')];
    }
}
