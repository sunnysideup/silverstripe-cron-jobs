<?php

declare(strict_types=1);

namespace Sunnysideup\CronJobs\Api;

use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

class TimeGuard
{
    use Injectable;
    use Configurable;

    protected static float $start = 0.0;

    private static string $log_file = 'silverstripe-timeguard.log';
    private static int $max_bytes = 200000; // ~200 KB

    public static function checkIn(int $seconds = 300): void
    {
        if (self::$start === 0.0) {
            self::$start = microtime(true);
            return;
        }

        $elapsed = microtime(true) - self::$start;

        if ($elapsed <= $seconds) {
            return;
        }

        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1] ?? [];
        $label = trim(($caller['class'] ?? '') . '::' . ($caller['function'] ?? ''), ':') ?: 'unknown';

        self::writeLine(
            'Slow: ' . $label . ' after ' . number_format($elapsed, 3) . 's'
        );

        self::$start = microtime(true);
    }

    private static function writeLine(string $line): void
    {
        $path = self::getLogFilePath();
        $maxBytes = (int) Config::inst()->get(self::class, 'max_bytes');

        if (file_exists($path) && filesize($path) > $maxBytes) {
            self::removeOldestLine($path);
        }

        file_put_contents(
            $path,
            $line . "\n",
            FILE_APPEND | LOCK_EX
        );
    }

    private static function removeOldestLine(string $path): void
    {
        $content = file($path, FILE_IGNORE_NEW_LINES);
        if (! $content) {
            return;
        }

        array_shift($content);

        file_put_contents(
            $path,
            implode("\n", $content) . "\n",
            LOCK_EX
        );
    }

    private static function getLogFilePath(): string
    {
        $file = Config::inst()->get(self::class, 'log_file') ?? self::$log_file;
        return Director::baseFolder() . '/' . $file;
    }
}
