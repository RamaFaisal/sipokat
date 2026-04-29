<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $app_name;
    public string $contact_email;
    public string $contact_phone;
    public string $website;

    public static function group(): string
    {
        return 'general';
    }
}
