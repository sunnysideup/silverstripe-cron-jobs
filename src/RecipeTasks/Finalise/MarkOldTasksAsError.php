<?php

namespace Sunnysideup\CronJobs\RecipeTasks\Finalise;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStepError;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStepNote;
use Sunnysideup\CronJobs\RecipeTasks\SiteUpdateRecipeTaskBaseClass;

class MarkOldTasksAsError extends SiteUpdateRecipeTaskBaseClass
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
        $this->oldLogsDeleterInner(SiteUpdateStepError::class);
        $this->oldLogsDeleterInner(SiteUpdateRunNext::class);
        $this->oldLogsDeleterInner(SiteUpdateStepNote::class);
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
        echo "\r\n";
        if ($logs->exists()) {
            foreach ($logs as $log) {
                echo '. ';
                $log->delete();
            }
        }

        echo "\r\n";
    }
}
