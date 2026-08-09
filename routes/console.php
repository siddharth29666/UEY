<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// UEY Scheduled Platform Maintenance & Audits
Schedule::command('app:expire-pending-rides')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('app:cleanup-otp')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('app:retry-notifications')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('app:expire-promo-codes')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('app:driver-offline')
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('app:wallet-settlement')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

Schedule::command('app:referral-bonus')
    ->everyMinute()
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground();

if (config('app.driver_subscription_enabled', false)) {
    Schedule::command('app:expire-subscriptions')
        ->dailyAt('00:00')
        ->withoutOverlapping()
        ->onOneServer()
        ->runInBackground();
}
