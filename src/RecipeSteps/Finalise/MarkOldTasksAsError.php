<?php

namespace Sunnysideup\CronJobs\RecipeSteps\Finalise;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateNote;
use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateStepNote;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;

class MarkOldTasksAsError extends SiteUpdateRecipeStepBaseClass
{
    /**
     * @var int
     */
    private const MAX_KEEP_DAYS = 10;

    public function getDescription(): string
    {
        return 'Tasks that ran more than ' . self::MAX_KEEP_DAYS . ' days ago are deleted.';
    }

    public function run(): int
    {
        $this->oldLogsDeleter();
        return 0;

    }

    protected function oldLogsDeleter()
    {
        $this->oldLogsDeleterInner(SiteUpdate::class);
        $this->oldLogsDeleterInner(SiteUpdateStep::class);
        $this->oldLogsDeleterInner(SiteUpdateNote::class);
        $this->oldLogsDeleterInner(SiteUpdateRunNext::class);
        $this->oldLogsDeleterInner(SiteUpdateStepNote::class);
        $this->markBadSiteUpdatesAsStopped(SiteUpdateStepNote::class);
    }

    protected function oldLogsDeleterInner($className)
    {
        $logs = $className::get()->filter(
            [
                'Created:LessThan' => date(
                    'Y-m-d H:i:s',
                    strtotime('-' . self::MAX_KEEP_DAYS . ' days')
                ),
            ]
        );
        if ($logs->exists()) {
            foreach ($logs as $log) {
                $log->delete();
            }
        }
    }

    protected function markBadSiteUpdatesAsStopped()
    {
        $siteUpdates = SiteUpdate::get()->filterAny(
            [
                'Status' => [null, ''],
                'Type' => [null, ''],
                'RunnerClassName' => [null, ''],
            ]
        );
        if ($siteUpdates->exists()) {
            foreach ($siteUpdates as $siteUpdate) {
                $siteUpdate->Stopped = true;
                $siteUpdate->Status = 'NotCompleted';
                $siteUpdate->write();
            }
        }
    }
}
