<?php

namespace App\Filament\Resources\SawCriterias\Pages;

use App\Filament\Resources\SawCriterias\SawCriteriaResource;
use App\Models\SawCriteria;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListSawCriteria extends ListRecords
{
    protected static string $resource = SawCriteriaResource::class;

    protected static ?string $title = 'Kriteria SAW';

    protected function getHeaderWidgets(): array
    {
        return [];
    }

    public function mount(): void
    {
        parent::mount();

        $totalWeight = (float) SawCriteria::where('is_active', true)->sum('weight');

        if (abs($totalWeight - 1.0) > 0.001) {
            Notification::make()
                ->warning()
                ->title('Total bobot tidak = 1.000')
                ->body('Total bobot kriteria aktif saat ini: ' . number_format($totalWeight, 3) . '. SAW butuh total bobot = 1 supaya nilai preferensi valid.')
                ->persistent()
                ->send();
        }
    }
}
