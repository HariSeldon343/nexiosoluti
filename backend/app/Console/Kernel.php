<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Schedule commands here
        // $schedule->command('inspire')->hourly();

        // Clean expired JWT tokens daily
        $schedule->command('jwt:prune')->daily();

        // Clean up old activity logs
        $schedule->command('activitylog:clean')->daily();

        // Process queued jobs
        $schedule->command('queue:work --stop-when-empty')->everyMinute();

        // Backup database (if enabled)
        if (env('BACKUP_ENABLED', false)) {
            $schedule->command('backup:run')->daily();
        }
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}