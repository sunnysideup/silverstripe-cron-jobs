<?php

namespace Sunnysideup\CronJobs\Traits;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStepError;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStepNote;
use Sunnysideup\CronJobs\Recipes\UpdateRecipe;
use SilverStripe\Control\Director;
use Sunnysideup\Flush\FlushNow;
use Sunnysideup\Flush\FlushNowImplementor;

/**
 * @method UpdateRecipe getTye()
 */
trait LogSuccessAndErrorsTrait
{
    public static function log_anything(string $message, ?string $type = 'changed', ?bool $important = false)
    {
        self::log_anything_inner($message, $type, $important);
    }
    protected function logSuccess(string $message, ?bool $important = false)
    {
        $this->logAnything($message, 'success', $important);
    }

    protected function logError(string $message, ?bool $important = false)
    {
        $this->logAnything($message, 'error', $important);
    }

    protected function logChanged(string $message, ?bool $important = false)
    {
        $this->logAnything($message, 'changed', $important);
    }

    protected function logAnything(string $message, ?string $type = 'changed', ?bool $important = false)
    {
        self::log_anything_inner($message, $type, $important);
    }

    protected static $time_since_last_message = 0;

    protected function signOfLife(string $message, ?string $type = 'changed', ?bool $important = false)
    {
        if((time() - self::$time_since_last_message) > 60) {
            self::log_anything_inner('...', $type, $important);
            self::log_anything_inner($message, $type, $important);
        }
    }

    private static function log_anything_inner(string $message, ?string $type = 'changed', ?bool $important = false)
    {
        self::$time_since_last_message = time();
        $type = strtolower($type);
        $flushTypes = [
            'success' => 'created',
            'created' => 'created',
            'good' => 'created',
            'error' => 'deleted',
            'deleted' => 'deleted',
            'bad' => 'deleted',
            'warning' => 'changed',
        ];

        $flushType = $flushTypes[$type] ?? 'changed';

        if (Director::isDev() || $type) {
            $message = date('h:i:s') . ' | ' . $message;
            FlushNowImplementor::do_flush(substr((string) $message, 0, 200), $flushType);
        }
    }
}
