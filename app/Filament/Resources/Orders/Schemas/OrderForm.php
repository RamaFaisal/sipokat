<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Models\Medicine;
use App\Models\Order;
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
                    ->columns(3)
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
                            ->helperText('Boleh mundur untuk menyusulkan penjualan kemarin. Alokasi batch selalu dari stok saat ini.'),
                        Hidden::make('created_by')->default(fn () => auth()->id()),
                    ]),

                Section::make('Item')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('medicine_name'),
                                Select::make('medicine_id')
                                    ->label('Obat')
                                    ->columnSpan(5)
                                    ->options(fn () => Medicine::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) {
                                        $set('medicine_name', $state ? Medicine::query()->whereKey($state)->value('name') : null);
                                    }),
                                TextInput::make('qty')
                                    ->label(fn (Get $get) => 'Jumlah'.self::unitSuffix($get))
                                    ->columnSpan(2)
                                    ->numeric()
                                    ->integer()
                                    ->minValue(1)
                                    ->default(1)
                                    ->required()
                                    ->live(onBlur: true)
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
                                TextInput::make('price')
                                    ->label('Harga jual')
                                    ->columnSpan(3)
                                    ->numeric()
                                    ->minValue(0)
                                    ->prefix('Rp')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->helperText(fn (Get $get) => self::hppHint($get))
                                    ->rules([
                                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                            $medicineId = $get('medicine_id');
                                            if (! $medicineId) {
                                                return;
                                            }
                                            $hpp = app(StockCardService::class)->currentHpp((int) $medicineId);
                                            if ($hpp !== null && (float) $value < $hpp) {
                                                $fail('Harga jual tidak boleh di bawah HPP (Rp '.number_format($hpp, 0, ',', '.').').');
                                            }
                                        },
                                    ]),
                                Placeholder::make('subtotal_preview')
                                    ->label('Subtotal')
                                    ->columnSpan(2)
                                    ->content(fn (Get $get) => 'Rp '.number_format(((int) $get('qty')) * ((float) $get('price')), 0, ',', '.')),
                                Placeholder::make('allocation_preview')
                                    ->label('Diambil dari batch (FEFO)')
                                    ->columnSpanFull()
                                    ->content(fn (Get $get) => self::allocationPreview($get)),
                            ])
                            ->columns(12)
                            ->columnSpanFull()
                            ->addActionLabel('Tambah obat')
                            ->minItems(1)
                            ->live(),
                    ]),

                Section::make('Ringkasan')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('note')
                            ->label('Catatan')
                            ->rows(2),
                        Placeholder::make('grand_total_preview')
                            ->label('Total')
                            ->content(function (Get $get) {
                                $total = 0;
                                foreach ($get('items') ?? [] as $row) {
                                    $total += ((int) ($row['qty'] ?? 0)) * ((float) ($row['price'] ?? 0));
                                }

                                return new HtmlString('<span class="text-lg font-bold">Rp '.number_format($total, 0, ',', '.').'</span>');
                            }),
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

    protected static function unitSuffix(Get $get): string
    {
        $medicineId = $get('medicine_id');
        if (! $medicineId) {
            return '';
        }
        $unit = Medicine::query()->whereKey($medicineId)->with('unit')->first()?->unit?->name;

        return $unit ? " ({$unit})" : '';
    }

    protected static function hppHint(Get $get): ?string
    {
        $medicineId = $get('medicine_id');
        if (! $medicineId) {
            return null;
        }
        $hpp = app(StockCardService::class)->currentHpp((int) $medicineId);

        return $hpp === null ? 'Obat ini belum punya HPP.' : 'HPP saat ini Rp '.number_format($hpp, 0, ',', '.').' — harga jual minimal sebesar itu.';
    }

    /** Pratinjau alokasi FEFO: kasir melihat batch, ED, dan sisa hari — tidak memilih. */
    protected static function allocationPreview(Get $get): HtmlString
    {
        $medicineId = $get('medicine_id');
        $qty = (int) $get('qty');
        if (! $medicineId || $qty <= 0) {
            return new HtmlString('<span class="text-gray-400">—</span>');
        }

        $layers = app(StockCardService::class)->sellableLayers((int) $medicineId);
        $available = $layers->sum('remaining');

        if ($available <= 0) {
            return new HtmlString('<span class="text-danger-600">Tidak ada stok yang belum kedaluwarsa.</span>');
        }

        $lines = [];
        $left = $qty;
        foreach ($layers as $layer) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, (int) $layer->remaining);
            $days = $layer->expired_date ? (int) today()->diffInDays($layer->expired_date->copy()->startOfDay(), false) : null;
            $lines[] = sprintf(
                '→ Ambil <b>%d</b> dari batch <b>%s</b>%s',
                $take,
                e($layer->batch_number ?? 'tanpa batch'),
                $layer->expired_date ? ' ED '.$layer->expired_date->format('m-Y').' ('.$days.' hari)' : '',
            );
            $left -= $take;
        }

        if ($left > 0) {
            $lines[] = '<span class="text-danger-600">Kurang '.$left.' — stok tersedia '.$available.'.</span>';
        }

        return new HtmlString('<span class="text-sm">Sisa tersedia: '.$available.' dalam '.$layers->count().' batch</span><br>'.implode('<br>', $lines));
    }
}
