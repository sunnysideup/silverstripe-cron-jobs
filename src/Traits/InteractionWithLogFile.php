<?php

namespace Sunnysideup\CronJobs\Traits;

use SilverStripe\Forms\LiteralField;
use Sunnysideup\CronJobs\Api\BashColours;

trait InteractionWithLogFile
{
    protected function addLogField($fields, $tabName)
    {
        $data = $this->getLogContent();
        $source = basename($this->logFilePath());
        $logField = LiteralField::create(
            'Logs',
            '<h2>Raw Log - stored in (' . $source . ') </h2>
            <div style="background-color: #300a24; padding: 20px; height: 600px; overflow-y: auto; border-radius: 10px; color: #efefef; font-family: monospace; font-size: 10px;">' . $data . '</div>'
        );
        $fields->addFieldsToTab(
            $tabName,
            [
                $logField,
            ]
        );
    }


    protected function getLogContent(): string
    {
        $filePath = $this->logFilePath();
        if (file_exists($filePath)) {
            return BashColours::bash_to_html(file_get_contents($filePath));
        }

        return 'No file found here '.$filePath.'. Older logs maybe deleted.';
    }

    protected function deleteLogFile()
    {
        if (file_exists($this->logFilePath())) {
            unlink($this->logFilePath());
        }
    }


    protected function hasErrorInLog(string $contents): bool
    {
        $needles = ['[Emergency]', '[Error]', '[CRITICAL]', '[ALERT]', '[ERROR]'];
        foreach ($needles as $needle) {
            if (strpos($contents, $needle) !== false) {
                return true;
            }
        }
        return false;
    }
}
