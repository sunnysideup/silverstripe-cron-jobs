<?php

namespace Sunnysideup\CronJobs\Analysis;

use Sunnysideup\CronJobs\Traits\BaseMethodsForRecipesAndSteps;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataList;
use SilverStripe\ORM\DataObject;
use SilverStripe\View\ArrayData;
use Sunnysideup\CronJobs\Traits\LogSuccessAndErrorsTrait;

abstract class AnalysisBaseClass
{
    use BaseMethodsForRecipesAndSteps;
    use LogSuccessAndErrorsTrait;

    /**
     * returns the HTML for the analysis.
     */
    abstract public function run(HTTPRequest $request): string;

    public function SubLinks(): ?ArrayList
    {
        return null;
    }

    /**
     * nothing to return here
     *
     * @return string
     */
    public function getLogClassName(): string
    {
        return '';
    }

    public function getGroup(): string
    {
        return 'Analysis';
    }

    protected function runHeader(): string
    {
        $html = '';
        $html .= '<h1>Analysis - ' . $this->getTitle() . '</h1>';
        $html .= '<p class="message success">' . $this->getDescription() . '</p>';
        if ($this->HasIdSelection()) {
            $html .= '<h2>' . $this->getFormTitle() . '</h2>';
            $html .= $this->SelectItemForm();
        }

        return $html;
    }

    protected function runFooter(): string
    {
        $html = '';
        $html .= '<h2>More Analysis</h2>';

        return $html . ArrayData::create(['MyList' => AnalysisBaseClass::my_child_links()])->renderWith('MyChildLinks');
    }

    protected function getAction(): string
    {
        return 'runanalysis';
    }

    abstract protected function HasIdSelection(): bool;

    protected function ListToChooseFrom(): ?DataList
    {
        return null;
    }

    protected function hasCurrentID(): bool
    {
        return (bool) $this->getCurrentID();
    }

    protected function getCurrentID(): int
    {
        return (int) $this->request->getVar('id');
    }

    /**
     * @return null|DataObject
     */
    protected function getCurrentObject()
    {
        return $this->ListToChooseFrom()->byID($this->getCurrentID());
    }

    protected function getTitleFieldForList(): string
    {
        return 'Title';
    }

    protected function useDropdownInForm(): bool
    {
        return true;
    }

    protected function getFormTitle(): string
    {
        return 'Select Item';
    }

    protected function SelectItemForm(): string
    {
        if ($this->HasIdSelection()) {
            if ($this->useDropdownInForm()) {
                $options = $this->ListToChooseFrom()->sort($this->getTitleFieldForList(), 'ASC')->map('ID', $this->getTitleFieldForList())->toArray();
                $optionsHTML = '';
                foreach ($options as $id => $title) {
                    $selected = '';
                    if ($id === $this->getCurrentID()) {
                        $selected = 'selected="selected"';
                    }

                    $optionsHTML .= '<option value="' . $id . '" ' . $selected . '>' . $title . '</option>';
                }

                $input = '
                <select name="id" onchange="this.form.submit();">
                    <option value="0">
                    --- please select ---
                    </option>
                    ' . $optionsHTML . '
                </select>
                ';
            } else {
                $input = '<input type="text" name="id" value="' . $this->getCurrentID() . '" onchange="this.form.submit();" />';
            }

            return '
                <form method="get">
                    ' . $input . '
                    <input type="submit" value="show">
                </form>
            ';
        }

        return '';
    }

    protected function array2ul(array $array): string
    {
        $out = '<ul>';
        foreach ($array as $key => $elem) {
            if (!is_array($elem)) {
                $out .= '<li><strong>' . $key . ':</strong> ' . $elem . '</li>';
            } else {
                $out .= '<li><strong>' . $key . ':</strong>' . $this->array2ul($elem) . '</li>';
            }
        }

        return $out . '</ul>';
    }
}
