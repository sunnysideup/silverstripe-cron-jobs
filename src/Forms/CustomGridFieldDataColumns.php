<?php

namespace Sunnysideup\CronJobs\Forms;

use Override;
use SilverStripe\Forms\GridField\GridFieldDataColumns;

class CustomGridFieldDataColumns extends GridFieldDataColumns
{
    #[Override]
    public function getColumnContent($gridField, $record, $columnName)
    {
        if ($columnName == 'TimeTaken') {
            return $record->getTimeNice();
        }

        return parent::getColumnContent($gridField, $record, $columnName);
    }

}
