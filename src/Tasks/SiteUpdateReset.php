<?php

namespace Sunnysideup\CronJobs\Tasks;

use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class SiteUpdateReset extends BuildTask
{
    private static $segment = 'site-update-reset';

    protected $title = 'Reset all Site Updates';

    protected $description = 'Set all the Site Updates steps to STOPPED';

    protected $verbose = true;

    public function setVerbose(?bool $b = true): self
    {
        $this->verbose = $b;

        return $this;
    }

    public function run($request)
    {
        DB::query('Update SiteUpdate SET Stopped = 1;');
        DB::query('Update SiteUpdateStep SET Stopped = 1;');
        DB::query('TRUNCATE SiteUpdateRunNext');
        if ($this->verbose) {
            DB::alteration_message('DONE');
        }
    }
}
