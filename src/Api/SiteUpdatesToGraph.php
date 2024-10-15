<?php

namespace Sunnysideup\CronJobs\Api;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

class SiteUpdatesToGraph
{
    use Injectable;
    use Configurable;

    public function SiteUpdatesToGraphData(?string $dateFilter = null): array
    {
        $data = [];
        foreach (WorkOutWhatToRunNext::get_recipes() as $recipe) {
            $lastRunHadErrorsSymbol = $recipe->LastRunHadErrorsSymbol();
            if ($recipe->CMSEditLink()) {
                $title = '
                <a href="'.$recipe->CMSEditLink().'" target="_blank">'.$recipe->getTitle().'</a>: ' .
                $recipe->getDescription().'. ' .
                $lastRunHadErrorsSymbol . ''.$recipe->LastCompletedNice().'. '.
                'It is '.($recipe->IsMeetingTarget() ? '' : ' NOT ').' meeting its schedule targets. ';
            } else {
                $title = $recipe->getTitle().': '.$recipe->getDescription().'.  No current records. ';
            }
            $title .= '<a href="'.$recipe->Link().'" target="_blank">Schedule now.</a>';
            $data[] = [
                'Title' => $title,
                'Times' => $this->SiteUpdateToGraphData($recipe, $dateFilter),
            ];
        }

        return $data;
    }

    public function SiteUpdateToGraphData($recipe, ?string $dateFilter = null): array
    {
        $data = [];
        $logs = $recipe->listOfLogsForThisRecipeOrStep();
        $logs = $logs->filter(['Stopped' => true]);
        if ($dateFilter) {
            $logs = $logs->filter(['Created:GreaterThanOrEqual' => date('Y-m-d H:i:s', strtotime($dateFilter))]);
        }
        foreach ($logs as $log) {
            $data[] = [
                'StartDateTime' => $log->Created,
                'DurationInMinutes' => $log->TimeTaken / 60,
                'Class' => $log->HasErrors ? 'cron-job-graph-bad' : 'cron-job-graph-good',
                'Title' => $log->Created . ' - ' . $log->getTimeNice()
            ];
        }

        return $data;
    }

}
