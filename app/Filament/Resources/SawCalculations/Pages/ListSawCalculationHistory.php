<?php

namespace App\Filament\Resources\SawCalculations\Pages;

use App\Filament\Resources\SawCalculations\SawCalculationResource;
use Filament\Resources\Pages\ListRecords;

class ListSawCalculationHistory extends ListRecords
{
    protected static string $resource = SawCalculationResource::class;

    protected static ?string $title = 'Riwayat Perhitungan SAW';
}
