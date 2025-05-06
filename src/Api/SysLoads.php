<?php

namespace Sunnysideup\CronJobs\Api;

use InvalidArgumentException;
use RuntimeException;

class SysLoads
{

    public static function get_sys_load(?bool $asPercentages = false): array
    {
        try {
            if (function_exists('sys_getloadavg')) {
                $load = sys_getloadavg();
                $cores = (int) shell_exec('nproc');
                try {
                    $cores = (int) shell_exec('nproc');
                } catch (RuntimeException | InvalidArgumentException $e) {
                    $cores = 1;
                }
                if ($asPercentages) {
                    return [
                        self::float_2_percentage((float)(($load[0] ?? 0) / $cores)),
                        self::float_2_percentage((float)(($load[1] ?? 0) / $cores)),
                        self::float_2_percentage((float)(($load[2] ?? 0) / $cores)),
                    ];
                }
                return [
                    ($load[0] ?? 0) / $cores,
                    ($load[1] ?? 0) / $cores,
                    ($load[2] ?? 0) / $cores,
                ];
            }
        } catch (RuntimeException | InvalidArgumentException $e) {
            // do nothing
        }
        if ($asPercentages) {
            return [
                self::float_2_percentage(0),
                self::float_2_percentage(0),
                self::float_2_percentage(0),
            ];
        }
        return [
            0,
            0,
            0,
        ];
    }

    public static function get_max_ram_use_in_megabytes(): int
    {
        return round(memory_get_usage(true) / 1024 / 1024);
    }

    public static function get_ram_usage_as_percent_of_total_available(?bool $asPercentage = false): float
    {
        try {
            $output = [];
            exec('free -m', $output);

            if (empty($output)) {
                return $asPercentage ? self::float_2_percentage(0) : 0;
            }

            foreach ($output as $line) {
                if (strpos($line, 'Mem:') === 0) {
                    $parts = preg_split('/\s+/', $line);
                    $total = (int) $parts[1]; // Total memory in MB
                    $available = (int) $parts[6]; // Available memory in MB

                    if ($total === 0) {
                        return $asPercentage ? self::float_2_percentage((float) 0) : 0;
                    }

                    return $asPercentage ? self::float_2_percentage((float) ($available / $total)) : 0;
                }
            }
        } catch (RuntimeException | InvalidArgumentException $e) {
            // do nothing
        }


        return $asPercentage ? self::float_2_percentage((float) 0) : 0;
    }

    public static function float_2_percentage(float|int $float): string
    {
        return round($float * 100) . '%';
    }
}
