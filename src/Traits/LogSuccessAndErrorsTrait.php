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
    public function logAnything(string $message, ?string $type = 'changed', ?bool $important = false)
    {
        $this->logAnythingInner($message, $type, $important, $this->getLogFilePath());
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

    protected function logHeader(string $message)
    {
        $this->logAnything('---');
        $this->logAnything($message);
        $this->logAnything('---');
    }

    protected static $time_since_last_message = 0;

    /**
     *
     * put out a message one a minute, or a . for every time a message is called.
     * @param string $message
     * @param mixed $type
     * @param mixed $important
     * @return void
     */
    protected function signOfLife(string $message, ?string $type = 'changed', ?bool $important = false)
    {
        if((time() - self::$time_since_last_message) > 60) {
            $this->logAnything($message, $type, $important);
        } else {
            $this->logAnything('. ', $type, $important);
        }
    }

    protected function getLogFilePath(): ?string
    {
        if($this instanceof SiteUpdate || $this instanceof SiteUpdateStep) {
            if($this->ID) {
                return $this->logFilePath();
            }
        }
        return null;
    }

    protected function createNote(string $message, string $messageTypeForNote): void
    {
        if($this instanceof SiteUpdate || $this instanceof SiteUpdateStep) {
            $logClassName = $this->getRelationClass('ImportantLogs');
            $obj = $logClassName::create();
            $obj->Message = $message;
            $obj->Type = $messageTypeForNote;
            $this->ImportantLogs()->add($obj->write());
        }
    }

    protected function logAnythingInner(string $message, ?string $type = 'changed', ?bool $important = false, ?string $logFilePath = '')
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

        if (Director::isDev() || $type !== 'changed' || $important) {
            FlushNowImplementor::do_flush(substr((string) $message, 0, 200), $flushType);
            $messageTypeForNote = [
                'created' => 'Success',
                'changed' => 'Warning',
                'deleted' => 'Error',
            ];
            $messageTypeForNote = $messageTypeForNote[$flushType] ?? 'Warning';
            $this->createNote($message, $messageTypeForNote);
        }
        if($logFilePath) {
            $message .= PHP_EOL;
            file_put_contents($logFilePath, $message, FILE_APPEND);
        }
    }
}
