<?php

namespace Sunnysideup\CronJobs\Api;

use SilverStripe\Core\Injector\Injectable;

class Converters
{
    use Injectable;
    public function SecondsToTime(int $seconds): string
    {
        $timeUnits = [
            'month' => 86400 * 30.5,
            'week' => 86400 * 7,
            'day' => 86400,
            'hour' => 3600,
            'minute' => 60,
            'second' => 1
        ];

        $result = [];

        foreach ($timeUnits as $unit => $value) {
            if ($seconds >= $value) {
                $amount = floor($seconds / $value);
                $seconds %= $value;
                $result[] = $this->formatTimeUnit($amount, $unit);
            }
        }

        return $result ? implode(', ', $result) : 'n/a';
    }

    private function formatTimeUnit(int $value, string $unit): string
    {
        return $value . ' ' . $unit . ($value > 1 ? 's' : '');
    }


    public function MinutesToTime(int $minutes): string
    {
        return $this->SecondsToTime($minutes * 60);
    }
}
