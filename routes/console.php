<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('tasks:prune-results')->everyMinute()->withoutOverlapping();

Schedule::command('tasks:reconcile')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->appendOutputTo('/proc/1/fd/1');
