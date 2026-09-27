<?php

namespace App\Filament\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    // Seperti halaman kustom lain: akses tunduk pada `View:Dashboard`. Ketiga peran
    // Tabel 3.2 memilikinya; user tanpa peran memang tidak seharusnya melihat apa pun.
    use HasPageShield;

    public function getColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 4,
        ];
    }
}
