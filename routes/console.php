<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SAW recalculate — pagi sebelum jam buka apotek, hasil dipakai widget dashboard.
Schedule::command('sipokat:recalculate-saw')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->runInBackground();

// Notifikasi stok menipis & obat mendekati kedaluwarsa — pagi jam buka.
Schedule::command('sipokat:check-stock-and-expiry')
    ->dailyAt('08:00')
    ->withoutOverlapping();
