<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Cadangan data setiap malam, tujuh yang terbaru disimpan. Tanpa media supaya
// tidak menghabiskan disk — media jarang berubah dan ikut di cadangan manual.
// Hanya berjalan bila cron server menjalankan `php artisan schedule:run`
// setiap menit (petunjuknya ada di menu Cadangan & Pemulihan).
Schedule::command('cadangan:buat --otomatis --tanpa-berkas --simpan=7')
    ->dailyAt('01:30')
    ->withoutOverlapping();
