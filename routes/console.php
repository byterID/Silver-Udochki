<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('tasks:prune-results')->everyMinute()->withoutOverlapping();

Schedule::command('notifications:prune')->hourly()->withoutOverlapping();

Schedule::command('tasks:reconcile')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->appendOutputTo('/proc/1/fd/1');
