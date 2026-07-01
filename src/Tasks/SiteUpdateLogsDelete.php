<?php

namespace Sunnysideup\CronJobs\Tasks;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use SilverStripe\Core\Injector\Injector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use SilverStripe\PolyExecution\PolyOutput;

class SiteUpdateLogsDelete extends BuildTask
{
    protected string $title = 'Delete All Site Update Logs';

    protected static string $description = 'Delete all the Site Update Logs to start afresh';

    protected static string $commandName = 'site-update-logs-delete';

    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        $this->truncateTable('SiteUpdate', $output);
        $this->truncateTable('SiteUpdateNote', $output);
        $this->truncateTable('SiteUpdateStep', $output);
        $this->truncateTable('SiteUpdateStepNote', $output);
        $this->truncateTable('SiteUpdateRunNext', $output);
        // delete all log files
        Injector::inst()->get(SiteUpdate::class)->deleteAllFilesInFolder();
        $output->writeln('DONE, make sure to run a dev/build');
        return Command::SUCCESS;
    }

    public function truncateTable(string $tableName, PolyOutput $output)
    {
        $output->writeln("Truncating {$tableName}");
        DB::get_conn()->clearTable($tableName);
    }
}
