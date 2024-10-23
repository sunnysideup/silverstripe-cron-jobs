<?php

namespace Sunnysideup\CronJobs\Traits;

use Sunnysideup\CronJobs\Model\Logs\SiteUpdate;
use Sunnysideup\CronJobs\Model\Logs\SiteUpdateStep;
use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;
use SilverStripe\Control\Director;
use SilverStripe\ORM\DataObjectInterface;
use Sunnysideup\CronJobs\Model\SiteUpdateConfig;
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;
use Sunnysideup\Flush\FlushNowImplementor;

/**
 * @method SiteUpdateRecipeBaseClass getTye()
 */
trait LogSuccessAndErrorsTrait
{
    public function logAnything(string $message, ?string $type = 'changed', ?bool $important = false)
    {
        // needs to be here so that ping does not run it...
        self::$seconds_since_last_log_entry = time();
        $this->logAnythingInner($message, $type, $important);
    }

    public function logSuccess(string $message, ?bool $important = false)
    {
        $this->logAnything($message, 'success', $important);
    }

    public function logError(string $message, ?bool $important = false)
    {
        $this->logAnything($message, 'error', $important);
    }

    public function logChanged(string $message, ?bool $important = false)
    {
        $this->logAnything($message, 'changed', $important);
    }

    protected function logHeader(string $message)
    {
        $this->logAnything('---');
        $this->logAnything($message);
        $this->logAnything('---');
    }

    protected static int $seconds_since_last_log_entry = 0;
    protected static int $seconds_since_log_update = 0;

    private const SECONDS_BETWEEN_PINGS = 60;

    /**
     *
     * put out a message one a minute, or a . for every time a message is called.
     * @param string $message
     * @param mixed $type
     * @param mixed $important
     * @return void
     */
    protected function logSignOfLife(string $message, ?string $type = 'changed', ?bool $important = false)
    {
        if ($this->needsToPing()) {
            $this->logAnything($message, $type, $important);
        } else {
            // specifically goes directly to the inner to not update the ping time.
            $this->logAnythingInner('. ', $type, $important);
        }
    }

    protected function getLogFilePath(): ?string
    {
        $log = $this->getSiteUpdateLogObject();
        if ($log) {
            return $log->logFilePath();
        }
        return null;
    }

    protected $cachedLogForLogSuccessAndErrorsTrait = null;

    protected function getSiteUpdateLogObject(): SiteUpdate|SiteUpdateStep|null
    {
        if (! $this->cachedLogForLogSuccessAndErrorsTrait) {
            $this->cachedLogForLogSuccessAndErrorsTrait = null;
            if ($this instanceof SiteUpdateRecipeBaseClass || $this instanceof SiteUpdateRecipeStepBaseClass) {
                $this->cachedLogForLogSuccessAndErrorsTrait = $this->log;
            } elseif ($this instanceof SiteUpdate || $this instanceof SiteUpdateStep) {
                $this->cachedLogForLogSuccessAndErrorsTrait = $this;
            }
        }
        return $this->cachedLogForLogSuccessAndErrorsTrait;
    }

    protected function createNote(string $message, string $messageTypeForNote, ?bool $important = false): void
    {
        $log = $this->getSiteUpdateLogObject();
        if ($log) {
            $noteClass = $log->getRelationClass('ImportantLogs');
            $obj = $noteClass::create();
            $obj->Message = $message;
            $obj->Important = $important;
            $obj->Type = $messageTypeForNote;
            $log->ImportantLogs()->add($obj->write());
        }
    }

    protected function logAnythingInner(string $message, ?string $type = 'changed', ?bool $important = false, ?string $logFilePath = '')
    {
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

        $message = date('H:i:s') . ' | ' . $message;
        $logAllMessages = SiteUpdateConfig::inst()->LogAllMessagesInDatabase;
        if (Director::isDev() || $type !== 'changed' || $important || $logAllMessages) {
            FlushNowImplementor::do_flush(substr((string) $message, 0, 200), $flushType);
            $messageTypeForNote = [
                'created' => 'Success',
                'changed' => 'Warning',
                'deleted' => 'ERROR',
            ];
            $messageTypeForNote = $messageTypeForNote[$flushType] ?? 'Warning';
            if ($important || $logAllMessages) {
                $this->createNote($message, $messageTypeForNote, $important);
            }
        }
        $logFilePath =  $this->getLogFilePath();
        if ($logFilePath) {
            $message .= PHP_EOL;
            file_put_contents($logFilePath, $message, FILE_APPEND);
        }
        // update last edited...
        if ($this instanceof SiteUpdateRecipeBaseClass || $this instanceof SiteUpdateRecipeStepBaseClass) {
            if ($this->needsToUpdateLog()) {
                $this->recordTimeAndMemory();
                self::$seconds_since_log_update = time();
            }
        }
    }

    protected function needsToPing(): bool
    {
        return (time() - self::$seconds_since_last_log_entry) > self::SECONDS_BETWEEN_PINGS;
    }

    protected function needsToUpdateLog(): bool
    {
        return (time() - self::$seconds_since_log_update) > self::SECONDS_BETWEEN_PINGS;
    }
}
