<?php

namespace Sunnysideup\CronJobs\Forms;

use Override;
use SilverStripe\Forms\DropdownField;
use Sunnysideup\CronJobs\Api\WorkOutWhatToRunNext;

class SiteUpdateStepDropdownField extends DropdownField
{
    #[Override]
    public function getSource()
    {
        $list = [];
        $steps = WorkOutWhatToRunNext::get_recipe_steps();
        foreach ($steps as $className => $step) {
            $list[$className] = $step->getTitle();
        }

        asort($list);
        $list = array_merge(['' => '(Any)'], $list);
        return $list;
    }

}
