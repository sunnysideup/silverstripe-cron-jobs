<?php

namespace Sunnysideup\CronJobs\View;

use Respect\Validation\Rules\Unique;
use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\View\ArrayData;
use SilverStripe\View\ViewableData;

class Graph extends ViewableData
{
    private static $height_per_set_in_pixels = 40;
    private static $top_bottom_margin_in_pixels = 3;
    private static $left_margin_percentage_for_titles = 3;
    private static $left_padding_percentage_for_titles = 20;
    private static $default_start_date = '-24 hours';
    private static $default_end_date = 'now';
    protected array $sets;

    protected int $startDate;
    protected int $endDate;
    protected string $title;

    public function __construct()
    {
        parent::__construct();
        $this->sets = [];
        $this->startDate = strtotime($this->Config()->get('default_start_date'));
        $this->endDate = strtotime($this->Config()->get('default_end_date'));
    }

    public function render(): string
    {
        return $this->renderWith(static::class);
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * A set needs to be provided like this:
     * ```php
     *     [
     *         'Title' => 'MyTitle A',
     *         'Times' => [
     *             ['StartDateTime' => '2020-01-01 00:00:00', 'DurationInMinutes' => 3600, 'Colour => '#ff0000', 'Title' => 'Hello Mouse Over',],
     *             ['StartDateTime' => '2020-01-01 10:00:00', 'DurationInMinutes' => 3600, 'Colour => '#ff0000', 'Title' => 'Hello Mouse Over',],
     *             ['StartDateTime' => '2020-01-01 20:00:00', 'DurationInMinutes' => 3600, 'Colour => '#ff0000', 'Title' => 'Hello Mouse Over',],
     *         ],
     *     ],
     *     [
     *         'Title' => 'MyTitle B',
     *         'Times' => [
     *             ['StartDateTime' => '2020-01-01 00:00:00', 'DurationInMinutes' => 3600, 'Colour => '#ff0000', 'Title' => 'Hello Mouse Over',],
     *             ['StartDateTime' => '2020-01-01 10:00:00', 'DurationInMinutes' => 3600, 'Colour => '#ff0000', 'Title' => 'Hello Mouse Over',],
     *             ['StartDateTime' => '2020-01-01 20:00:00', 'DurationInMinutes' => 3600, 'Colour => '#ff0000', 'Title' => 'Hello Mouse Over',],
     *         ],
     *     ],
     * ```
     * @param string $title
     * @param array $set
     * @return \Sunnysideup\CronJobs\View\Graph
     */
    public function setSets(array $sets): self
    {
        $this->sets = $sets;

        return $this;
    }

    /**
     * A set needs to be provided like this:
     * ```php
     *             ['StartDateTime' => '2020-01-01 00:00:00', 'DurationInMinutes' => 3600, 'Colour => '#ff0000', 'Title' => 'Hello Mouse Over',],
     *             ['StartDateTime' => '2020-01-01 10:00:00', 'DurationInMinutes' => 3600, 'Colour => '#ff0000', 'Title' => 'Hello Mouse Over',],
     *             ['StartDateTime' => '2020-01-01 20:00:00', 'DurationInMinutes' => 3600, 'Colour => '#ff0000', 'Title' => 'Hello Mouse Over',],
     * ```
     * @param string $title
     * @param array $set
     * @return \Sunnysideup\CronJobs\View\Graph
     */
    public function addSet(string $title, array $set): self
    {
        $this->sets[] = [
            'Title' => $title,
            'Times' => $set,
        ];

        return $this;
    }

    public function setStartDate($startDate): self
    {
        if (!is_numeric($startDate)) {
            $startDate = strtotime($startDate);
        }
        $this->startDate = $startDate;

        return $this;
    }

    public function setEndDate($endDate): self
    {
        if (!is_numeric($endDate)) {
            $endDate = strtotime($endDate);
        }
        $this->endDate = $endDate;

        return $this;
    }

    public function getInstructions(): ArrayList
    {
        $topBottomMargin = $this->Config()->get('top_bottom_margin_in_percent');
        $al = ArrayList::create();
        $leftMargin = $this->Config()->get('left_margin_percentage_for_titles');
        $leftPadding = $this->Config()->get('left_padding_percentage_for_titles');
        $heighPerSet = $this->Config()->get('height_per_set_in_pixels');
        $span = $this->endDate - $this->startDate;
        $height = $topBottomMargin + ($heighPerSet / 2);
        $totalHeight = $this->getHeight();
        foreach ($this->sets as $title => $set) {
            $title = $set['Title'];
            $times = $set['Times'];
            $top = ($height / $totalHeight) * 100;
            $heighPerSetInPercent = ($heighPerSet / $totalHeight) * 100;
            foreach ($times as $time) {
                $start = $time['StartDateTime'];
                $duration = $time['DurationInMinutes'] * 60;
                $class = $time['Class'];
                $attributeTitle = $time['Title'];
                $absoluteStart = strtotime($start);
                $absoluteEnd = $absoluteStart + $duration;
                if ($absoluteStart < $this->startDate) {
                    $absoluteStart = $this->startDate;
                }
                if ($absoluteEnd > $this->endDate) {
                    $absoluteEnd = $this->endDate;
                }
                $relativeStart = $absoluteStart - $this->startDate;
                $relativeEnd = $absoluteEnd - $this->startDate;
                $left = ($relativeStart / $span) * 100;
                $width = (($relativeEnd - $relativeStart) / $span) * 100;
                $al->push(
                    ArrayData::create([
                        'Top' => round($top, 5),
                        'Left' => round($left, 5),
                        'Width' => round($width, 5),
                        'Height' => round($heighPerSetInPercent * 0.6, 5),
                        'Class' => $class,
                        'Content' => null,
                        'Title' => Convert::raw2att($attributeTitle),
                    ])
                );
            }
            $al->push(
                ArrayData::create([
                    'Top' => $top,
                    'Left' => 0,
                    'Width' => 100.1,
                    'Height' => $heighPerSetInPercent,
                    'Class' => 'cron-job-graph-title',
                    'Content' => $title instanceof DBHTMLText ? $title : DBHTMLText::create_field('HTMLText', $title),
                    'Title' => null,
                ])
            );
            $height += $heighPerSet + $topBottomMargin;
        }
        return $al;
    }

    public function getHeight(): int
    {
        $count = count($this->sets) + 1;
        $countTimesSets = ($count * $this->Config()->get('height_per_set_in_pixels')) + ($count * $this->Config()->get('top_bottom_margin_in_pixels'));
        return floor($countTimesSets) + 1;
    }

    public function ID(): string
    {
        return 'cron-graph-' . $this->startDate . '-' . $this->endDate;
    }

    public function Title(): string
    {
        if (isset($this->title)) {
            return $this->title;
        }
        return 'Activity between ' . date('d-m-Y, H:i', $this->startDate) . ' AND ' . date('d-m-Y, H:i', $this->endDate);
    }
}
