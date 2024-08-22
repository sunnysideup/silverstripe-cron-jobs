<?php

namespace Sunnysideup\CronJobs\Forms;

use SilverStripe\Forms\GridField\GridFieldDataColumns;

class CustomGridFieldDataColumns extends GridFieldDataColumns
{
    public function getColumnContent($gridField, $record, $columnName)
    {
        if ($columnName == 'TimeTaken') {
            return $record->getTimeNice();
        }

        return parent::getColumnContent($gridField, $record, $columnName);
    }

}
