<?php

use App\Models\BackupSetting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('minitor:backup')
    ->dailyAt('00:00')
    ->when(fn () => BackupSetting::current()->enabled)
    ->onOneServer();
