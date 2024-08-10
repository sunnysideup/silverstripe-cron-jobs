<?php

namespace Sunnysideup\CronJobs\Tasks;

use SilverStripe\Core\Injector\Injector;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;

class SiteUpdateLogsDelete extends BuildTask
{
    use LogSuccessAndErrorsTrait;

    protected $title = 'Delete All Site Update Logs';

    protected $description = 'Delete all the Site Update Logs to start afresh';

    public function run($request)
    {
        $this->truncateTable('SiteUpdate');
        $this->truncateTable('SiteUpdateNote');
        $this->truncateTable('SiteUpdateStep');
        $this->truncateTable('SiteUpdateStepNote');
        $this->truncateTable('SiteUpdateRunNext');

        // delete all log files
        Injector::inst()->get(SiteUpdate::class)->deleteAllFilesInFolder();

        DB::alteration_message('DONE, make sure to run a dev/build');
    }

    public function truncateTable(string $tableName)
    {
        DB::get_conn()->clearTable($tableName);
    }
}
