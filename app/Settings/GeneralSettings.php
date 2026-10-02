<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $app_name;

    public ?string $contact_email = null;

    public ?string $contact_phone = null;

    public ?string $website = null;

    /**
     * Tarif PPN (%): harga faktur PBF sudah termasuk PPN, angka ini hanya memecahnya jadi
     * DPP dan PPN di cetakan RO, tidak pernah menambah ke total.
     */
    public int $ppn_rate = 11;

    public static function group(): string
    {
        return 'general';
    }
}
