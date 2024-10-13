<?php

namespace Sunnysideup\CronJobs\Forms;

use SilverStripe\Forms\DropdownField;
use Sunnysideup\CronJobs\Api\WorkOutWhatToRunNext;

class SiteUpdateDropdownField extends DropdownField
{
    public function getSource()
    {
        $list = [];
        $recipes = WorkOutWhatToRunNext::get_recipes(true);
        foreach ($recipes as $className => $recipe) {
            $list[$className] = $recipe->getTitle();
        }
        asort($list);
        $list = array_merge(['' => '(Any)'], $list);
        return $list;
    }

}
