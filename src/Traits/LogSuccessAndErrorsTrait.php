<?php

namespace Sunnysideup\CronJobs\Traits;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use SilverStripe\Control\Director;
use SilverStripe\ORM\DataObjectInterface;
use Sunnysideup\Flush\FlushNowImplementor;

/**
 * @method SiteUpdateRecipeBaseClass getTye()
 */
trait LogSuccessAndErrorsTrait
{
    protected static DataObjectInterface|null $current_log_file_object = null;
    protected static string $current_log_file_path = '';

    public static function set_current_log_file_object(null|SiteUpdate|SiteUpdateStep $logObject)
    {
        self::$current_log_file_object = $logObject;
        if(self::$current_log_file_object) {
            self::$current_log_file_path = $logObject->logFilePath();
        } else {
            self::$current_log_file_path = '';
        }
    }



    public static function log_anything(string $message, ?string $type = 'changed', ?bool $important = false)
    {
        self::log_anything_inner($message, $type, $important);
    }


    protected function logHeader(string $message)
    {
        FlushNowImplementor::do_flush('---');
        FlushNowImplementor::do_flush($message);
        FlushNowImplementor::do_flush('---');
    }

    protected function logAnything(string $message, ?string $type = 'changed', ?bool $important = false)
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


    protected static $time_since_last_message = 0;

    protected function signOfLife(string $message, ?string $type = 'changed', ?bool $important = false)
    {
        if((time() - self::$time_since_last_message) > 60) {
            self::log_anything_inner('... ', $type, $important);
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

        $message = date('h:i:s') . ' | ' . $message;

        if (Director::isDev() || $type) {
            FlushNowImplementor::do_flush(substr((string) $message, 0, 200), $flushType);
        }
        if(self::$current_log_file_path) {
            file_put_contents(self::$current_log_file_path, $message . "\r\n", FILE_APPEND);
        }
    }
}
