<?php

namespace Sunnysideup\CronJobs\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;

class SiteUpdateReset extends BuildTask
{
    protected static string $commandName = 'site-update-reset';

    protected string $title = 'Reset all Site Updates';

    protected static string $description = 'Set all the Site Updates steps to STOPPED';

    protected $verbose = true;

    public function setVerbose(?bool $b = true): self
    {
        $this->verbose = $b;

        return $this;
    }

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        DB::query('Update SiteUpdate SET Stopped = 1;');
        $output->writeln('Updated SiteUpdate table');
        DB::query('Update SiteUpdateStep SET Stopped = 1;');
        $output->writeln('Updated SiteUpdateStep table');
        DB::query('TRUNCATE SiteUpdateRunNext');
        $output->writeln('Truncated SiteUpdateRunNext table');
        if ($this->verbose) {
            $output->writeln('DONE');
        }

        return Command::SUCCESS;
    }
}
