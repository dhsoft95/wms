<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('containers:check-dwell')->daily();
Schedule::command('wms:po:remind-approvers')->dailyAt('08:00');
Schedule::command('reports:weekly-email')->weeklyOn(1, '07:00')->timezone('Africa/Dar_es_Salaam');
