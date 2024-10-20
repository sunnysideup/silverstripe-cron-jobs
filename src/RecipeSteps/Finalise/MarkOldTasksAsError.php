<?php

namespace Sunnysideup\CronJobs\RecipeSteps\Finalise;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateNote;
use Sunnysideup\CronJobs\Model\Logs\Custom\SiteUpdateRunNext;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Model\Logs\Notes\SiteUpdateStepNote;
use Sunnysideup\CronJobs\Model\SiteUpdateConfig;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;

class MarkOldTasksAsError extends SiteUpdateRecipeStepBaseClass
{
    /**
     * @var int
     */
    private static $max_keep_days = 10;
    private static $max_keep_days_files = 3;

    public function getDescription(): string
    {
        return 'Tasks that ran more than ' . $this->Config()->max_keep_days . ' days ago are deleted.';
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
        $this->deleteFilesOderThan($this->Config()->max_keep_days_files);
        $this->markBadSiteUpdatesAsStopped();
        $this->markStoppedUpdatesAsNotCompleted();
    }

    protected function oldLogsDeleterInner($className)
    {
        $logs = $className::get()->filter(
            [
                'Created:LessThan' => date(
                    'Y-m-d H:i:s',
                    strtotime('-' . $this->Config()->max_keep_days . ' days')
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

    protected function markStoppedUpdatesAsNotCompleted()
    {
        foreach ([SiteUpdate::class, SiteUpdateStep::class] as $className) {
            $siteUpdates = $className::get()->filter(
                [
                    'Status' => 'Started',
                    'Stopped' => true,
                ]
            );
            if ($siteUpdates->exists()) {
                foreach ($siteUpdates as $siteUpdate) {
                    $siteUpdate->Status = 'NotCompleted';
                    $siteUpdate->write();
                }
            }
        }
    }

    protected function deleteFilesOderThan(?int $days = 3)
    {
        $files = glob(SiteUpdateConfig::folder_path() . '/*.log');
        $now = time();
        foreach ($files as $file) {
            if (is_file($file)) {
                if ($now - filemtime($file) >= 60 * 60 * 24 * $days) {
                    unlink($file);
                }
            }
        }
    }
}
