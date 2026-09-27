<?php

namespace App\Filament\Pages;

use App\Filament\Resources\PurchaseOrders\Schemas\PurchaseOrderForm;
use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\Supplier;
use App\Services\SawCalculationService;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Fluent;

/**
 * Halaman ranking SAW (rencana-revisi-2026-09 Bagian 7): periode "sampai" dikunci hari ini (K2),
 * kolom "Tingkat" = peringkat padat (K9), penanda "sudah dipesan" (P10), dan bulk "Buat PO" (P9)
 * jembatan dari rekomendasi ke tindakan; apoteker yang mencentang, sistem tidak memutuskan.
 *
 * Sejak 2026-09-27 perhitungan dilakukan **saat halaman dibuka**, tidak lagi membaca snapshot
 * angka yang tampil selalu mencerminkan stok dan penjualan terkini.
 */
class SawCalculation extends Page implements HasSchemas, HasTable
{
    use HasPageShield;
    use InteractsWithSchemas;
    use InteractsWithTable;

    protected string $view = 'filament.pages.saw-calculation';

    protected static ?string $title = 'Perhitungan SAW';

    protected static ?string $navigationLabel = 'Hitung Prioritas Restock';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|\UnitEnum|null $navigationGroup = 'SPK Restock';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    /** Hasil perhitungan untuk render ini; dihitung sekali lalu dipakai ulang dalam satu permintaan. */
    protected ?array $result = null;

    public function mount(): void
    {
        $this->data = [
            'period_start' => today()->subDays(29)->toDateString(),
            'period_end' => today()->toDateString(),
        ];
    }

    /**
     * Hitung SAW atas kondisi saat ini. Kegagalan (mis. Σ bobot ≠ 1) tidak boleh
     * menjatuhkan halaman tabel ditampilkan kosong dengan peringatan.
     */
    protected function result(): array
    {
        if ($this->result !== null) {
            return $this->result;
        }

        $start = Carbon::parse($this->data['period_start'] ?? today()->subDays(29)->toDateString());

        try {
            return $this->result = app(SawCalculationService::class)->calculate($start, today());
        } catch (\Throwable $e) {
            $this->result = [
                'period_start' => $start,
                'period_end' => today(),
                'calculated_at' => now(),
                'criteria' => [],
                'column_stats' => [],
                'total_alternatives' => 0,
                'excluded_count' => 0,
                'rows' => [],
                'error' => $e->getMessage(),
            ];

            return $this->result;
        }
    }

    /** Ringkasan untuk ditampilkan di view halaman. */
    public function summary(): array
    {
        $result = $this->result();

        return [
            'calculated_at' => $result['calculated_at'],
            'period_start' => $result['period_start'],
            'period_end' => $result['period_end'],
            'total_alternatives' => $result['total_alternatives'],
            'excluded_count' => $result['excluded_count'],
            'error' => $result['error'] ?? null,
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Periode Permintaan (C2)')
                    ->description('Penjualan dalam periode ini diproyeksikan ke ekuivalen 30 hari. "Sampai" selalu hari ini; ubah "Dari" bila perlu (bawaan 30 hari).')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('period_start')
                            ->label('Dari')
                            ->required()
                            ->native(false)
                            ->maxDate(today()),
                        DatePicker::make('period_end')
                            ->label('Sampai')
                            ->required()
                            ->native(false)
                            ->disabled()
                            ->dehydrated(),
                    ]),
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        $result = $this->result();
        $ordered = $this->orderedMedicineIds();

        return $table
            ->records(fn (): Collection => collect($result['rows']))
            ->columns([
                TextColumn::make('rank')
                    ->label('Tingkat')
                    ->tooltip('Peringkat padat: obat dengan Nilai Prioritas sama berada di tingkat yang sama.')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        (int) $state <= 3 => 'danger',
                        (int) $state <= 7 => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Kode')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Nama Obat')
                    ->searchable()
                    ->wrap()
                    ->description(fn (array $record) => in_array($record['medicine_id'], $ordered, true) ? 'Sudah dipesan (PO terbuka)' : null)
                    ->icon(fn (array $record) => in_array($record['medicine_id'], $ordered, true) ? Heroicon::OutlinedShoppingCart : null)
                    ->iconColor('info'),
                TextColumn::make('c1_raw')
                    ->label('Stok / Min (C1)')
                    ->tooltip('Rasio stok tersedia terhadap batas minimum obat')
                    ->state(fn (array $record) => $record['c1_stock'] === null
                    ? number_format((float) $record['c1_raw'], 2, ',', '.')
                    : sprintf('%d / %d = %s', $record['c1_stock'], $record['c1_min_stock'], number_format((float) $record['c1_raw'], 2, ',', '.')))
                    ->alignEnd(),
                TextColumn::make('c2_raw')
                    ->label('Permintaan/Bln (C2)')
                    ->numeric(decimalPlaces: 0)
                    ->alignEnd(),
                TextColumn::make('c3_raw')
                    ->label('Sisa ED hari (C3)')
                    ->tooltip('Batch terjauh yang masih bersisa; 0 = stok tersedia habis')
                    ->numeric(decimalPlaces: 0)
                    ->alignEnd(),
                TextColumn::make('c4_raw')
                    ->label('HPP (C4)')
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
                    ->modalHeading(fn (array $record) => 'Detail SAW: '.($record['name'] ?? '-'))
                    ->modalDescription('Rincian perhitungan V_i langkah demi langkah sesuai Bab 3.4.3–3.4.4.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('5xl')
                    ->modalContent(fn (array $record) => view(
                        'filament.pages.partials.saw-result-detail',
                        [
                            'result' => new Fluent($record),
                            'criteria' => collect($result['criteria'])->keyBy('code'),
                            'columnStats' => $result['column_stats'],
                            'periodStart' => $result['period_start'],
                            'periodEnd' => $result['period_end'],
                        ]
                    )),
            ])
            ->toolbarActions([
                BulkAction::make('create_po')
                    ->label('Buat PO dari yang dicentang')
                    ->icon(Heroicon::OutlinedShoppingCart)
                    ->color('primary')
                // Pemilik/Manajer hanya memantau ranking (Tabel 3.2), yang memesan Petugas.
                    ->visible(fn (): bool => auth()->user()?->can('create', PurchaseOrder::class) ?? false)
                    ->modalHeading('Buat PO')
                    ->modalDescription('Setelah ketersediaan dikonfirmasi ke sales PBF. Jumlah bawaan = mengisi sampai batas minimum; ubah di halaman PO bila perlu.')
                    ->schema([
                        Select::make('supplier_id')
                            ->label('PBF')
                            ->options(fn () => Supplier::where('status', 'active')->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->required(),
                        DatePicker::make('po_date')
                            ->label('Tanggal pesan')
                            ->default(today())
                            ->required(),
                    ])
                    ->action(function (Collection $records, array $data) {
                        $medicineIds = $records
                            ->map(fn ($record) => (int) (is_array($record) ? $record['medicine_id'] : $record->medicine_id))
                            ->unique()
                            ->values()
                            ->all();

                        $po = $this->createPurchaseOrder($medicineIds, (int) $data['supplier_id'], Carbon::parse($data['po_date']));

                        Notification::make()
                            ->success()
                            ->title("PO {$po->po_number} dibuat")
                            ->body($po->items()->count().' obat. Buka menu Purchase Order untuk menyesuaikan jumlah.')
                            ->send();
                    })
                    ->deselectRecordsAfterCompletion(),
            ])
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25);
    }

    /** P9: PO dari ranking baris dalam kemasan bawaan obat, jumlah ⌈min_stock ÷ isi⌉ (P5), harga perkiraan (P6). */
    protected function createPurchaseOrder(array $medicineIds, int $supplierId, Carbon $poDate): PurchaseOrder
    {
        abort_unless(auth()->user()?->can('create', PurchaseOrder::class) ?? false, 403);

        return DB::transaction(function () use ($medicineIds, $supplierId, $poDate) {
            $po = PurchaseOrder::create([
                'po_number' => PurchaseOrderForm::generatePONumber($poDate),
                'supplier_id' => $supplierId,
                'po_date' => $poDate->toDateString(),
                'created_by' => auth()->id(),
            ]);

            foreach (Medicine::query()->whereKey($medicineIds)->get() as $medicine) {
                $packSize = max(1, (int) $medicine->pack_size);
                $packQty = (int) ceil(max(1, (int) $medicine->min_stock) / $packSize);
                $unitPrice = $medicine->latestPurchasePrice() ?? 0;

                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'medicine_id' => $medicine->id,
                    'pack_unit_id' => $medicine->pack_unit_id,
                    'pack_size' => $packSize,
                    'pack_qty' => $packQty,
                    'qty' => $packQty * $packSize,
                    'price' => $unitPrice,
                ]);
            }

            return $po;
        });
    }

    /** P10: obat yang ada di PO terbuka (belum lengkap, belum ditutup). */
    protected function orderedMedicineIds(): array
    {
        return PurchaseOrderItem::query()
            ->whereHas('purchaseOrder', fn ($q) => $q->whereIn('status_receive_order', [PurchaseOrder::STATUS_PENDING, PurchaseOrder::STATUS_PARTIAL]))
            ->pluck('medicine_id')
            ->unique()
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('recalculate')
                ->label('Refresh')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->action(fn () => $this->recalculate()),
        ];
    }

    /** Muat ulang perhitungan dengan periode yang sedang dipilih. */
    public function recalculate(): void
    {
        $this->form->getState();
        $this->result = null;
        $this->resetTable();

        $result = $this->result();

        if (isset($result['error'])) {
            Notification::make()->danger()->title('Gagal menghitung SAW')->body($result['error'])->persistent()->send();

            return;
        }

        $body = "{$result['total_alternatives']} alternatif diranking.";
        if ($result['excluded_count'] > 0) {
            $body .= " {$result['excluded_count']} obat tanpa riwayat kartu stok dikecualikan.";
        }

        Notification::make()->success()->title('Perhitungan SAW diperbarui')->body($body)->send();
    }
}
