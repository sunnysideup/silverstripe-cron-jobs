<?php

namespace Sunnysideup\CronJobs\Tasks;

use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class SiteUpdateLogsDelete extends BuildTask
{
    use LogSuccessAndErrorsTrait;

    protected $title = 'Delete All Site Update Logs';

    protected $description = 'Delete all the Site Update Logs to start afresh';

    public function run($request)
    {
        $this->truncateTable('SiteUpdate');
        $this->truncateTable('SiteUpdateStep');
        $this->truncateTable('SiteUpdateStepError');
        $this->truncateTable('SiteUpdateStepNote');
        $this->truncateTable('SiteUpdateRunNext');
        self::log_anything('DONE, make sure to run a dev/build');
    }

    public function truncateTable(string $tableName)
    {
        DB::get_conn()->clearTable($tableName);
    }
}
