<?php

namespace Sunnysideup\CronJobs\Traits;

use InvalidArgumentException;
use RuntimeException;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Injector\Injector;
use Sunnysideup\CronJobs\Control\SiteUpdateController;

trait BaseMethodsForAllRunners
{
    protected $request = null;

    public function Link(?string $action = null): string
    {
        $action = $this->getAction();
        return SiteUpdateController::my_link($action . '/' . $this->getEscapedClassName() . '/');
    }

    public static function inst()
    {
        return Injector::inst()->get(static::class);
    }

    public function setRequest($request)
    {
        $this->request = $request;

        return $this;
    }

    protected function getRequest()
    {
        return $this->request;
    }


    protected function getEscapedClassName(): string
    {
        return str_replace('\\', '-', static::class);
    }

    public function Title(): string
    {
        return $this->getTitle();
    }

    public function getTitle(): string
    {
        $string = ClassInfo::shortName(static::class);

        return trim(preg_replace('#(?<!\ )[A-Z]#', ' $0', $string));
    }
}
