<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public string $app_name;
    public ?string $contact_email = null;
    public ?string $contact_phone = null;
    public ?string $website = null;

    public static function group(): string
    {
        return 'general';
    }
}
