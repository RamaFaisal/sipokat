<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $app_name;

    public ?string $contact_email = null;

    public ?string $contact_phone = null;

    public ?string $website = null;

    /** Tarif PPN (%) — hanya untuk pecahan DPP/PPN di cetakan RO; harga faktur sudah termasuk PPN. */
    public int $ppn_rate = 11;

    public static function group(): string
    {
        return 'general';
    }
}
