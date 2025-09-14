<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('app:clear-expired-tokens', function () {
    $this->info('Clearing expired JWT tokens...');
    // Logic to clear expired tokens from blacklist
    $this->info('Expired tokens cleared successfully.');
})->purpose('Clear expired JWT tokens from blacklist');

Artisan::command('app:cleanup-temp-files', function () {
    $this->info('Cleaning up temporary files...');
    // Logic to clean temporary files
    $deleted = 0;
    $path = storage_path('app/temp');
    if (File::exists($path)) {
        $files = File::files($path);
        foreach ($files as $file) {
            if ($file->getMTime() < now()->subHours(24)->timestamp) {
                File::delete($file);
                $deleted++;
            }
        }
    }
    $this->info("Deleted {$deleted} temporary files.");
})->purpose('Clean up temporary files older than 24 hours');