<?php

namespace App\Filament\Resources\MedicineStockOpnames\Schemas;

use App\Filament\Resources\ReceiveOrders\Schemas\ReceiveOrderForm;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\MedicineStockOpname;
use App\Services\StockCardService;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

/**
 * Stok opname per lapisan, berbasis hitung fisik (rencana-revisi-2026-09 §5.3, Q3):
 * memilih obat menampilkan semua batch-nya dengan sisa sistem; petugas mengisi jumlah fisik
 * per batch, selisihnya menjadi baris kartu stok pada batch itu. Batch yang belum tercatat
 * ditambahkan dengan batch + ED (hanya untuk obat yang sudah punya HPP, §7.3).
 */
class MedicineStockOpnameForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('opname_number')
                            ->label('Nomor Opname')
                            ->required()
                            ->readOnly()
                            ->dehydrated()
                            ->default(fn () => self::nextNumber()),
                        DatePicker::make('opname_date')
                            ->label('Tanggal Opname')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),
                        Hidden::make('status')->default('in_stock'),
                        Hidden::make('created_by')->default(fn () => auth()->id()),
                        Textarea::make('description')
                            ->label('Keterangan')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Hitung Fisik per Batch')
                    ->description('Pilih obat, lalu isi jumlah fisik tiap batch. Selisih dari sisa sistem otomatis menjadi penyesuaian pada batch itu. Batch kedaluwarsa yang dimusnahkan/diretur: isi fisik 0.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            ->dehydrated(false)
                            ->schema([
                                Select::make('medicine_id')
                                    ->label('Obat')
                                    ->options(fn () => Medicine::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->distinct()
                                    ->afterStateUpdated(fn (Set $set, $state) => $set('layers', self::layerRows($state ? (int) $state : null)))
                                    ->columnSpanFull(),

                                Repeater::make('layers')
                                    ->label('Batch tercatat')
                                    ->addable(false)
                                    ->deletable(false)
                                    ->reorderable(false)
                                    ->schema([
                                        Hidden::make('layer_stock_id'),
                                        Hidden::make('system_qty'),
                                        Hidden::make('batch_label'),
                                        Hidden::make('expired_label'),
                                        Placeholder::make('batch_info')
                                            ->label('Batch · ED')
                                            ->content(fn (Get $get) => new HtmlString(
                                                '<b>'.e($get('batch_label')).'</b>'.($get('expired_label') ? ' · ED '.e($get('expired_label')) : '')
                                            ))
                                            ->columnSpan(3),
                                        Placeholder::make('system_info')
                                            ->label('Sisa sistem')
                                            ->content(fn (Get $get) => (string) (int) $get('system_qty'))
                                            ->columnSpan(1),
                                        TextInput::make('physical_qty')
                                            ->label('Fisik')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(0)
                                            ->required()
                                            ->live(onBlur: true)
                                            ->columnSpan(2),
                                        Placeholder::make('diff_info')
                                            ->label('Selisih')
                                            ->content(function (Get $get) {
                                                $diff = (int) $get('physical_qty') - (int) $get('system_qty');

                                                return new HtmlString(match (true) {
                                                    $diff > 0 => '<span class="text-success-600">+'.$diff.' (masuk)</span>',
                                                    $diff < 0 => '<span class="text-danger-600">'.$diff.' (keluar)</span>',
                                                    default => '<span class="text-gray-400">0</span>',
                                                });
                                            })
                                            ->columnSpan(2),
                                        self::noteInput()
                                            ->required(fn (Get $get) => (int) $get('physical_qty') !== (int) $get('system_qty'))
                                            ->columnSpan(4),
                                    ])
                                    ->columns(12)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get) => filled($get('medicine_id'))),

                                Repeater::make('new_batches')
                                    ->label('Batch belum tercatat')
                                    ->addActionLabel('+ Batch baru')
                                    ->defaultItems(0)
                                    ->schema([
                                        TextInput::make('batch_number')
                                            ->label('No. batch')
                                            ->required()
                                            ->maxLength(100)
                                            ->columnSpan(4),
                                        TextInput::make('expired_month')
                                            ->label('ED (bulan-tahun)')
                                            ->placeholder('10-2026')
                                            ->required()
                                            ->regex('/^(0[1-9]|1[0-2])-\d{4}$/')
                                            ->validationMessages(['regex' => 'Tulis bulan-tahun, mis. 10-2026.'])
                                            ->columnSpan(4),
                                        TextInput::make('qty')
                                            ->label('Jumlah fisik')
                                            ->numeric()
                                            ->integer()
                                            ->minValue(1)
                                            ->required()
                                            ->columnSpan(4)
                                            ->rules([
                                                fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                                    $medicineId = $get('../../medicine_id');
                                                    if ($medicineId && app(StockCardService::class)->currentHpp((int) $medicineId) === null) {
                                                        $fail('Obat ini belum punya riwayat harga — masukkan stok awal lewat Penerimaan, bukan opname.');
                                                    }
                                                },
                                            ]),
                                        self::noteInput()->required()->columnSpan(12),
                                    ])
                                    ->columns(12)
                                    ->columnSpanFull()
                                    ->visible(fn (Get $get) => filled($get('medicine_id'))),
                            ])
                            ->columnSpanFull()
                            ->addActionLabel('Tambah obat')
                            ->minItems(1)
                            ->collapsible()
                            ->itemLabel(fn (array $state) => filled($state['medicine_id'] ?? null)
                                ? Medicine::query()->whereKey($state['medicine_id'])->value('name')
                                : null),
                    ]),
            ]);
    }

    public static function nextNumber(): string
    {
        $last = MedicineStockOpname::withTrashed()
            ->where('opname_number', 'like', 'OPM%')
            ->orderByDesc('opname_number')
            ->value('opname_number');

        $next = $last ? ((int) preg_replace('/\D/', '', $last)) + 1 : 1;

        return 'OPM'.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** Baris lapisan untuk satu obat: semua batch (termasuk kedaluwarsa) dengan sisa sistem > 0. */
    /** Keterangan per batch: alasan selisih, ikut ke item opname dan deskripsi kartu stok. */
    public static function noteInput(): TextInput
    {
        return TextInput::make('note')
            ->label('Keterangan')
            ->placeholder('mis. rusak / kedaluwarsa dimusnahkan / retur PBF / salah hitung')
            ->maxLength(200)
            ->datalist(['Rusak', 'Kedaluwarsa dimusnahkan', 'Retur ke PBF', 'Salah hitung sebelumnya', 'Ditemukan saat hitung fisik']);
    }

    public static function layerRows(?int $medicineId): array
    {
        if (! $medicineId) {
            return [];
        }

        return app(StockCardService::class)->layers($medicineId)
            ->filter(fn (MedicineStock $l) => $l->remaining > 0)
            ->map(fn (MedicineStock $l) => [
                'layer_stock_id' => $l->id,
                'batch_label' => $l->batch_number ?? 'tanpa batch',
                'expired_label' => $l->expired_date?->format('m-Y').($l->isExpired() ? ' (kedaluwarsa)' : ''),
                'system_qty' => $l->remaining,
                'physical_qty' => $l->remaining,
            ])
            ->values()
            ->all();
    }

    public static function parseExpiredMonth(?string $value): ?string
    {
        return ReceiveOrderForm::parseExpiredMonth($value)?->toDateString();
    }
}
