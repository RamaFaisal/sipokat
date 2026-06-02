<?php

namespace App\Filament\Resources\SawCriterias\Pages;

use App\Filament\Resources\SawCriterias\SawCriteriaResource;
use Filament\Resources\Pages\EditRecord;

class EditSawCriteria extends EditRecord
{
    protected static string $resource = SawCriteriaResource::class;

    protected static ?string $title = 'Edit Kriteria SAW';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
