<?php

use Illuminate\Support\Facades\Schedule;

// Where the host allows a per-minute cron (`php artisan schedule:run`), it
// drives the same runner as the pinger and web requests. Periodic tasks
// belong in App\Support\BackgroundRunner::tasks(), not here.
Schedule::command('app:tick')->everyMinute()->withoutOverlapping(5);
