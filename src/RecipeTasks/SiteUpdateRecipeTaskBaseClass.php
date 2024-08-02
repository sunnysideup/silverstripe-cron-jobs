<?php

namespace Sunnysideup\CronJobs\RecipeTasks;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Traits\BaseClassTrait;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DB;
use Sunnysideup\Flush\FlushNow;

abstract class SiteUpdateRecipeTaskBaseClass
{
    use BaseClassTrait;

    use LogSuccessAndErrorsTrait;

    use Configurable;

    protected $debug = false;

    abstract public function run(): int;

    public function SubLinks(): ?ArrayList
    {
        return null;
    }

    /**
     * we assume that runners run successfull,
     * but some can return false.
     */
    public function allowNextTaskToRun(): bool
    {
        return true;
    }

    public function getLogClassName(): string
    {
        return SiteUpdateStep::class;
    }

    public function getGroup(): string
    {
        return 'Step';
    }

    public function canRun(): bool
    {
        return true;
    }

    protected function getAction(): string
    {
        return 'runstep';
    }

    protected function tableHasData($tableName): bool
    {
        $query = DB::query('SELECT * FROM ' . $tableName . ' LIMIT 1;');

        return (bool) $query->numRecords();
    }
}
