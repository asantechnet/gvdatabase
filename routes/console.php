<?php

use App\Support\CompanyProfile;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$companyTimezone = app(CompanyProfile::class)->timezone();
$yesterday = now($companyTimezone)->subDay()->toDateString();

Schedule::command("attendance:finalize --from={$yesterday} --to={$yesterday}")
    ->dailyAt('00:20')->timezone($companyTimezone)->withoutOverlapping()->onOneServer();
Schedule::command("attendance:calculate-overtime --from={$yesterday} --to={$yesterday}")
    ->weeklyOn(1, '00:40')->timezone($companyTimezone)->withoutOverlapping()->onOneServer();
Schedule::command('analytics:sync')
    ->dailyAt('01:10')->timezone($companyTimezone)->withoutOverlapping()->onOneServer();
Schedule::command('bonuses:calculate')
    ->monthlyOn(1, '02:00')->timezone($companyTimezone)->withoutOverlapping()->onOneServer();
Schedule::command('recurring-events:process')
    ->hourly()->timezone($companyTimezone)->withoutOverlapping()->onOneServer();
Schedule::command('applicants:send-reminders')
    ->dailyAt('08:00')->timezone($companyTimezone)->withoutOverlapping()->onOneServer();
Schedule::command('contracts:send-expiry-alerts')
    ->dailyAt('08:10')->timezone($companyTimezone)->withoutOverlapping()->onOneServer();
Schedule::command('sanctum:prune-expired --hours=24')
    ->dailyAt('02:30')->timezone($companyTimezone)->withoutOverlapping()->onOneServer();
