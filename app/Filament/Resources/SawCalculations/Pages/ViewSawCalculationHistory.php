<?php

namespace App\Filament\Resources\SawCalculations\Pages;

use App\Filament\Resources\SawCalculations\SawCalculationResource;
use App\Models\SawCalculationResult;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ViewSawCalculationHistory extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = SawCalculationResource::class;

    protected string $view = 'filament.resources.saw-calculations.pages.view';

    public $record;

    public function mount(int|string $record): void
    {
        $this->record = \App\Models\SawCalculation::with('user')->findOrFail($record);
    }

    public function getTitle(): string
    {
        return 'Snapshot #' . $this->record->id . ' — ' . $this->record->calculated_at->format('d M Y H:i');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => SawCalculationResult::query()
                ->where('saw_calculation_id', $this->record->id)
                ->with('medicine:id,code,name'))
            ->defaultSort('rank')
            ->columns([
                TextColumn::make('rank')
                    ->label('#')
                    ->sortable(),
                TextColumn::make('medicine.code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('medicine.name')
                    ->label('Nama Obat')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('c1_raw')
                    ->label('Stok')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('c2_raw')
                    ->label('Permintaan/Bln')
                    ->numeric()
                    ->alignEnd(),
                TextColumn::make('c3_raw')
                    ->label('Sisa ED (hari)')
                    ->numeric()
                    ->placeholder('—')
                    ->alignEnd(),
                TextColumn::make('c4_raw')
                    ->label('Harga Beli')
                    ->money('IDR')
                    ->alignEnd(),
                TextColumn::make('preference_value')
                    ->label('Nilai Prioritas')
                    ->tooltip('V_i = Σ Wj × Rij')
                    ->numeric(decimalPlaces: 4)
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Detail')
                    ->icon(Heroicon::OutlinedCalculator)
                    ->modalHeading(fn (SawCalculationResult $r) => 'Detail SAW: ' . ($r->medicine->name ?? '-'))
                    ->modalDescription('Breakdown V_i sesuai Bab 3.4.4 proposal.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('5xl')
                    ->modalContent(fn (SawCalculationResult $r) => view(
                        'filament.pages.partials.saw-result-detail',
                        ['result' => $r->load('medicine', 'calculation')]
                    )),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }
}
