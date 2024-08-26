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
        $log = $this->getSiteUpdateLogObject();
        if($log) {
            return $log->logFilePath();
        }
        return null;
    }

    protected function getSiteUpdateLogObject(): SiteUpdate|SiteUpdateStep|null
    {
        $log = null;
        if($this instanceof SiteUpdateRecipeBaseClass || $this instanceof SiteUpdateRecipeStepBaseClass) {
            $log = $this->log;
        } elseif($this instanceof SiteUpdate || $this instanceof SiteUpdateStep) {
            $log = $this;
        }
        return $log;
    }

    protected function createNote(string $message, string $messageTypeForNote): void
    {
        $log = $this->getSiteUpdateLogObject();
        if($log) {
            $noteClass = $log->getRelationClass('ImportantLogs');
            $obj = $noteClass::create();
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
        $logAllMessages = SiteUpdateConfig::inst()->LogAllMessagesInDatabase;
        if (Director::isDev() || $type !== 'changed' || $important || $logAllMessages) {
            FlushNowImplementor::do_flush(substr((string) $message, 0, 200), $flushType);
            $messageTypeForNote = [
                'created' => 'Success',
                'changed' => 'Warning',
                'deleted' => 'ERROR',
            ];
            $messageTypeForNote = $messageTypeForNote[$flushType] ?? 'Warning';
            if($important || $logAllMessages) {
                $this->createNote($message, $messageTypeForNote, $important);
            }
        }
        $logFilePath =  $this->getLogFilePath();
        if($logFilePath) {
            $message .= PHP_EOL;
            file_put_contents($logFilePath, $message, FILE_APPEND);
        }
    }
}
