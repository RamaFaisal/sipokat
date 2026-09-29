<?php

namespace App\Support;

/**
 * Satu ambang kedaluwarsa untuk seluruh aplikasi (K6).
 *
 * Sebelum 2026-09-29 ada dua definisi "mendesak" yang berbeda: badge widget memakai 30 dan 60 hari,
 * sedangkan notifikasi harian memindai 90 hari. Sekarang ketiganya memakai kelas ini, jadi obat yang
 * memerahkan baris di dashboard adalah obat yang sama dengan yang memicu notifikasi.
 *
 * Batch yang sudah kedaluwarsa (sisa hari negatif) ikut tingkat paling mendesak.
 */
class AmbangEd
{
    /** Merah: kedaluwarsa dalam 30 hari atau kurang, termasuk yang sudah lewat. */
    public const MENDESAK = 30;

    /** Kuning: 31 sampai 60 hari. */
    public const WASPADA = 60;

    /** Hijau: 61 sampai 90 hari. Di atas ini tidak lagi dipantau. */
    public const PANTAU = 90;

    /** Warna Filament untuk sisa hari menuju kedaluwarsa. */
    public static function warna(?int $sisaHari): string
    {
        return match (true) {
            $sisaHari === null => 'gray',
            $sisaHari <= self::MENDESAK => 'danger',
            $sisaHari <= self::WASPADA => 'warning',
            $sisaHari <= self::PANTAU => 'success',
            default => 'gray',
        };
    }

    /** Kelas CSS baris tabel, dipakai `recordClasses()` pada widget kedaluwarsa. */
    public static function kelasBaris(?int $sisaHari): ?string
    {
        return match (self::warna($sisaHari)) {
            'danger' => 'bg-danger-50 dark:bg-danger-500/10',
            'warning' => 'bg-warning-50 dark:bg-warning-500/10',
            'success' => 'bg-success-50 dark:bg-success-500/10',
            default => null,
        };
    }

    /** Apakah sisa hari ini masuk pantauan (termasuk yang sudah kedaluwarsa). */
    public static function dipantau(?int $sisaHari): bool
    {
        return $sisaHari !== null && $sisaHari <= self::PANTAU;
    }
}
