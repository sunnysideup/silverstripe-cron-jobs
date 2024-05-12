<?php

namespace Sunnysideup\CronJobs\Tasks;

use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;

class SiteUpdateReset extends BuildTask
{
    use LogSuccessAndErrorsTrait;

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
        DB::query('Delete from SiteUpdateRunNext');
        if ($this->verbose) {
            self::log_anything('DONE');
        }
    }
}
