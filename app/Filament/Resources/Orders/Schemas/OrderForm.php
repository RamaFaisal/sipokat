<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Filament\Forms\PackLine;
use App\Models\Medicine;
use App\Models\Order;
use App\Services\StockCardService;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\RawJs;

/**
 * Penjualan (rencana-revisi-2026-09 §4.2, §5.2): kasir mengetik obat, jumlah (satuan jual),
 * dan harga (≥ HPP). Sistem mengalokasikan FEFO dan menampilkan batch mana yang diambil;
 * kasir tidak memilih batch (F0). Tidak ada edit — salah input dihapus lalu dibuat ulang (S6).
 */
class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Penjualan')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('order_code')
                            ->label('Nomor')
                            ->required()
                            ->readOnly()
                            ->dehydrated()
                            ->default(fn () => self::generateOrderCode()),
                        DatePicker::make('order_date')
                            ->label('Tanggal')
                            ->required()
                            ->default(now())
                            ->maxDate(now())
                            ->helperText('Alokasi batch selalu dari stok saat ini.'),
                        Hidden::make('created_by')->default(fn () => auth()->id()),
                    ]),

                Section::make('Item')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                // Satu baris: Obat · Jumlah · Harga jual · Subtotal (baca-saja) · Batch (FEFO, baca-saja).
                                Hidden::make('medicine_name'),
                                Select::make('medicine_id')
                                    ->label('Obat')
                                    ->columnSpan(3)
                                    ->options(fn () => Medicine::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Get $get, Set $set) {
                                        $set('medicine_name', $state ? Medicine::query()->whereKey($state)->value('name') : null);
                                        // Harga jual bawaan = HPP saat ini (batas minimum); kasir menaikkannya bila perlu.
                                        $hpp = $state ? app(StockCardService::class)->currentHpp((int) $state) : null;
                                        $set('price', $hpp);
                                        self::syncRow($get, $set);
                                    }),
                                TextInput::make('qty')
                                    ->label('Jumlah')
                                    ->suffix(fn (Get $get) => self::unitName($get) ?: 'satuan jual')
                                    ->columnSpan(2)
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncRow($get, $set))
                                    ->rules([
                                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                            $medicineId = $get('medicine_id');
                                            if (! $medicineId) {
                                                return;
                                            }
                                            $available = app(StockCardService::class)->availableStock((int) $medicineId);
                                            if ((int) $value > $available) {
                                                $fail("Stok tersedia hanya {$available}.");
                                            }
                                        },
                                    ]),
                                // Masking rupiah seperti PO/RO (tanpa numeric(): cast-nya membaca "5.900" sebagai 5,9).
                                TextInput::make('price')
                                    ->label('Harga jual')
                                    ->columnSpan(2)
                                    ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
                                    ->stripCharacters('.')
                                    ->inputMode('numeric')
                                    ->rules(['numeric', 'min:0'])
                                    ->prefix('Rp')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Get $get, Set $set) => self::syncRow($get, $set))
                                    ->helperText(fn (Get $get) => self::hppHint($get))
                                    ->rules([
                                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                            $medicineId = $get('medicine_id');
                                            if (! $medicineId) {
                                                return;
                                            }
                                            $hpp = app(StockCardService::class)->currentHpp((int) $medicineId);
                                            if ($hpp !== null && (PackLine::toNumber($value) ?? 0) < $hpp) {
                                                $fail('Harga jual tidak boleh di bawah HPP (Rp '.number_format($hpp, 0, ',', '.').').');
                                            }
                                        },
                                    ]),
                                TextInput::make('subtotal')
                                    ->label('Subtotal')
                                    ->columnSpan(2)
                                    ->prefix('Rp')
                                    ->readOnly()
                                    ->dehydrated(false)
                                    ->extraInputAttributes(['class' => 'text-right font-semibold']),
                                // Batch yang akan dipakai, ditentukan sistem secara FEFO (F0/F1) — ditampilkan sebagai
                                // pilihan ganda yang dinonaktifkan supaya batch + ED + jumlah per batch terlihat, tanpa bisa diubah.
                                // Tidak disimpan: alokasi sesungguhnya dihitung ulang StockMovementService saat simpan.
                                Select::make('fefo_batches')
                                    ->label('Batch (otomatis FEFO)')
                                    ->columnSpan(3)
                                    ->multiple()
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->options(fn (Get $get) => self::fefoAllocation($get)['options'])
                                    ->helperText(fn (Get $get) => self::fefoAllocation($get)['hint']),
                            ])
                            ->columns(12)
                            ->columnSpanFull()
                            ->addActionLabel('Tambah obat')
                            ->minItems(1)
                            ->live()
                            // Tambah/hapus baris → total ikut; perubahan di dalam baris ditangani syncRow().
                            ->afterStateUpdated(fn (Get $get, Set $set) => $set('grand_total_display', self::grandTotal($get('items') ?? []))),
                    ]),

                Section::make('Ringkasan')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('note')
                            ->label('Catatan')
                            ->rows(2),
                        TextInput::make('grand_total_display')
                            ->label('Total')
                            ->prefix('Rp')
                            ->readOnly()
                            ->dehydrated(false)
                            ->default('0')
                            ->extraInputAttributes(['class' => 'text-right font-semibold']),
                        Hidden::make('grand_total')->default(0),
                    ]),
            ]);
    }

    /** ORD-{YYYYMMDD}XXXX berdasarkan nomor terakhir dengan prefiks itu (bukan tanggal order, yang bisa mundur). */
    public static function generateOrderCode(): string
    {
        $prefix = 'ORD-'.now()->format('Ymd');

        $last = Order::withTrashed()
            ->where('order_code', 'like', $prefix.'%')
            ->orderByDesc('order_code')
            ->value('order_code');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    protected static function hppHint(Get $get): ?string
    {
        $medicineId = $get('medicine_id');
        if (! $medicineId) {
            return null;
        }
        $hpp = app(StockCardService::class)->currentHpp((int) $medicineId);

        return $hpp === null ? 'Belum punya HPP.' : 'Min. Rp '.number_format($hpp, 0, ',', '.').' (HPP)';
    }

    /** Setiap obat/jumlah/harga berubah: isi ulang subtotal, chip batch FEFO, dan total penjualan. */
    public static function syncRow(Get $get, Set $set): void
    {
        $qty = (int) $get('qty');
        $price = PackLine::toNumber($get('price'));
        $set('subtotal', ($qty > 0 && $price !== null) ? number_format($qty * $price, 0, ',', '.') : null);
        $set('fefo_batches', self::fefoAllocation($get)['selected']);
        $set('../../grand_total_display', self::grandTotal($get('../../items') ?? []));
    }

    /** Σ jumlah × harga semua baris, berformat rupiah bulat. */
    public static function grandTotal(array $rows): string
    {
        $total = 0.0;
        foreach ($rows as $row) {
            $total += ((int) ($row['qty'] ?? 0)) * (PackLine::toNumber($row['price'] ?? null) ?? 0);
        }

        return number_format($total, 0, ',', '.');
    }

    protected static function unitName(Get $get): string
    {
        $medicineId = $get('medicine_id');

        return $medicineId ? (string) (Medicine::query()->whereKey($medicineId)->with('unit')->first()?->unit?->name ?? '') : '';
    }

    /**
     * Alokasi FEFO untuk pratinjau (kasir melihat, tidak memilih): opsi = batch yang akan dipakai
     * dengan jumlah yang diambil dari masing-masing, selected = id lapisannya, hint = ringkasan/peringatan.
     *
     * @return array{options: array<int,string>, selected: array<int,int>, hint: ?string}
     */
    public static function fefoAllocation(Get $get): array
    {
        $medicineId = $get('medicine_id');
        $qty = (int) $get('qty');
        if (! $medicineId || $qty <= 0) {
            return ['options' => [], 'selected' => [], 'hint' => null];
        }

        $layers = app(StockCardService::class)->sellableLayers((int) $medicineId);
        $available = (int) $layers->sum('remaining');

        if ($available <= 0) {
            return ['options' => [], 'selected' => [], 'hint' => 'Tidak ada stok yang belum kedaluwarsa.'];
        }

        $options = [];
        $left = $qty;
        foreach ($layers as $layer) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, (int) $layer->remaining);
            // Kunci menyertakan jumlah yang diambil: nilai chip berubah setiap alokasi berubah, sehingga
            // label di browser ikut dirender ulang (nilai yang sama tidak memicu gambar ulang chip).
            $options[$layer->id.':'.$take] = sprintf(
                '%s%s · %d dari %d',
                $layer->batch_number ?? 'tanpa batch',
                $layer->expired_date ? ' · ED '.$layer->expired_date->format('m-Y') : '',
                $take,
                (int) $layer->remaining,
            );
            $left -= $take;
        }

        $hint = 'Tersedia '.$available.' dalam '.$layers->count().' batch, ED terdekat dipakai lebih dulu.';
        if ($left > 0) {
            $hint = 'Kurang '.$left.' — stok tersedia hanya '.$available.'.';
        }

        return ['options' => $options, 'selected' => array_keys($options), 'hint' => $hint];
    }
}
