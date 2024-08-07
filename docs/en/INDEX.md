# tl;dr

Here is the quick intro to this module.

## why use this module?

This module is designed to help you run regular updates on your website when you want to run certain tasks with certain intervals as certain times.

For example, a nightly update of your website, or a weekly update of your website.

But you may also have an update that runs every 10 minutes, or every 60 minutes.

This module ensures they do not run at the same time.

## How to set this up?

## set up your recipe(s)

```php

use Sunnysideup\CronJobs\Recipes\SiteUpdateRecipeBaseClass;

class MySiteUpdateRecipe extends SiteUpdateRecipeBaseClass
{

    public const STEPS = [
        MyFirstSUpdateStep::class,
        MySecondUpdateStep::class,
    ];

    public function getType(): string
    {
        return 'MySiteUpdateRecipe';
    }

    public function getDescription(): string
    {
        return 'Does something really cool';
    }

    public function canRun(): bool
    {
        return true;
    }

    public function canRunHoursOfTheDay(): array 
    {
        return [0, 1, 2, 3];
        // OR
        // return []; // to always run
    }
    
    public function minIntervalInMinutesBetweenRuns(): int
    {
        return 10;
    }

    public function maxIntervalInMinutesBetweenRuns(): int 
    {
        return 60;
    }

    protected function runEvenIfUpdatesAreStopped(): bool
    {
        return false;
    }
}


```

you can set up as many recipes as you like.

### set up your steps

```php
use Sunnysideup\CronJobs\RecipeSteps\SiteUpdateRecipeStepBaseClass;

class MyFirstUpdateStep extends SiteUpdateRecipeStepBaseClass
{
    public function getType(): string
    {
        return 'First Step'
    }

    public function getDescription(): string
    {
        return 'Does a first step for something really cool.';
    }

    /**
     * when can this step run?
     */
    public function canRun(): bool
    {
        return Director::isLive();
    }

    /**
     * return the number of errors encountered. 
     */
    public function run(): int
    {
        $errors = 0;
        // do something
        return $errors;
    }

    /**
     * allows you to stop the process altogether if something went wrong.
     */
    public function allowNextStepToRun(): int
    {
        return true;
    }
}

```

### set up the cron job to run it

```shell
* * * * * vendor/bin/sake dev/tasks/site-update-run
```
