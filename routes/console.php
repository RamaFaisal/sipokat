<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// SAW tidak lagi dijadwalkan: peringkat dihitung langsung saat halaman/dashboard dibuka
// (keputusan 2026-09-27), sehingga tidak ada snapshot yang perlu disegarkan tiap pagi.

// Notifikasi stok menipis & obat mendekati kedaluwarsa pagi jam buka.
Schedule::command('sipokat:check-stock-and-expiry')
    ->dailyAt('08:00')
    ->withoutOverlapping();
