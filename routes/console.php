<?php

use App\Jobs\CloseBillsJob;
use App\Jobs\ProcessRecurrencesJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CloseBillsJob)->dailyAt('00:00');
Schedule::job(new ProcessRecurrencesJob)->dailyAt('00:00');
