<?php

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * Tarif PPN hanya untuk pecahan DPP/PPN pada cetakan RO (rencana R13, Q9).
 * Harga faktur PBF sudah termasuk PPN; tidak ada perhitungan pajak di mana pun.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.ppn_rate', 11);
    }
};
