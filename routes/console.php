<?php

use Illuminate\Support\Facades\Schedule;

// Make sure `php artisan schedule:work` (dev) or a `* * * * * php artisan schedule:run` cron (prod) is running.
Schedule::command('loans:tick')->everyMinute()->withoutOverlapping();
Schedule::command('stats:aggregate')->dailyAt('01:10');
Schedule::command('analytics:run')->weeklyOn(1, '02:00'); // Monday 02:00
