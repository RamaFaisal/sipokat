<?php

namespace App\Filament\Pages;

use App\Models\SawCalculation as SawCalculationModel;
use App\Models\SawCalculationResult;
use App\Models\SawCriteria;
use App\Services\SawCalculationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Illuminate\Support\Carbon;

class SawCalculation extends Page implements HasTable, HasSchemas
{
    use InteractsWithSchemas;
    use InteractsWithTable;
    use HasPageShield;

    protected string $view = 'filament.pages.saw-calculation';

    protected static ?string $title = 'Perhitungan SAW';

    protected static ?string $navigationLabel = 'Hitung Prioritas Restock';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|\UnitEnum|null $navigationGroup = 'SPK Restock';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public ?SawCalculationModel $latest = null;

    public function mount(): void
    {
        $this->data = [
            'period_start' => now()->subDays(30)->toDateString(),
            'period_end' => now()->toDateString(),
        ];

        $this->refreshLatest();
    }

    protected function refreshLatest(): void
    {
        $this->latest = SawCalculationModel::orderByDesc('calculated_at')->first();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Parameter Periode')
                    ->description('Periode permintaan/penjualan (C2) yang akan diagregasi dari Orders. Default 30 hari ke belakang.')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('period_start')
                            ->label('Mulai')
                            ->required()
                            ->native(false)
                            ->maxDate(now()),
                        DatePicker::make('period_end')
                            ->label('Sampai')
                            ->required()
                            ->native(false)
                            ->maxDate(now())
                            ->afterOrEqual('period_start'),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        $calculationId = $this->latest?->id ?? 0;

        return $table
            ->query(fn (): Builder => SawCalculationResult::query()
                ->where('saw_calculation_id', $calculationId)
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
                    ->tooltip('Nilai preferensi SAW (V_i = Σ Wj × Rij). Semakin tinggi = semakin prioritas restock.')
                    ->numeric(decimalPlaces: 4)
                    ->alignEnd()
                    ->weight('bold'),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Detail Hitungan')
                    ->icon(Heroicon::OutlinedCalculator)
                    ->modalHeading(fn (SawCalculationResult $record) => 'Detail SAW: ' . ($record->medicine->name ?? '-'))
                    ->modalDescription('Breakdown perhitungan V_i step-by-step sesuai Bab 3.4.4 proposal.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('5xl')
                    ->modalContent(fn (SawCalculationResult $record) => view(
                        'filament.pages.partials.saw-result-detail',
                        ['result' => $record->load('medicine', 'calculation')]
                    )),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('calculate')
                ->label('Hitung Sekarang')
                ->icon(Heroicon::OutlinedPlay)
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('Hitung ulang SAW untuk seluruh obat aktif dan simpan sebagai snapshot baru?')
                ->action('runCalculation'),
        ];
    }

    public function runCalculation(): void
    {
        $totalWeight = (float) SawCriteria::where('is_active', true)->sum('weight');
        if (abs($totalWeight - 1.0) > 0.001) {
            Notification::make()
                ->danger()
                ->title('Bobot tidak valid')
                ->body('Total bobot kriteria aktif = ' . number_format($totalWeight, 3) . '. Harus = 1.000 sebelum SAW dijalankan.')
                ->send();
            return;
        }

        $data = $this->form->getState();

        try {
            $service = new SawCalculationService();
            $calc = $service->execute(
                Carbon::parse($data['period_start']),
                Carbon::parse($data['period_end']),
                'manual',
                auth()->id(),
            );

            $this->refreshLatest();
            $this->resetTable();

            Notification::make()
                ->success()
                ->title('Perhitungan SAW selesai')
                ->body("{$calc->total_alternatives} alternatif diranking. Snapshot ID #{$calc->id}.")
                ->send();
        } catch (\Throwable $e) {
            Notification::make()
                ->danger()
                ->title('Gagal menghitung SAW')
                ->body($e->getMessage())
                ->send();
        }
    }
}
