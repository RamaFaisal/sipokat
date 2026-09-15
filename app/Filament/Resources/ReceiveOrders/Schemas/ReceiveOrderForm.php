<?php

namespace App\Filament\Resources\ReceiveOrders\Schemas;

use App\Filament\Forms\PackLine;
use App\Models\Medicine;
use App\Models\PurchaseOrder;
use App\Models\ReceiveOrder;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\Rule;

/**
 * Penerimaan = satu faktur PBF (rencana-revisi-2026-09 Bagian 2).
 *
 * Header: nomor RO, nomor faktur (unik per PBF), PBF, tanggal terima, PO opsional.
 * Dari PO petugas mencentang item yang ada di faktur (R9); baris di luar PO boleh ditambah (R11).
 * Baris: kemasan → dikonversi ke satuan jual (R1); batch dan ED bulan-tahun wajib (R3, R4);
 * ED disimpan tanggal 1 dan harus lewat dari tanggal terima (Q4).
 */
class ReceiveOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Faktur')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('receive_order_number')
                            ->label('Nomor RO')
                            ->default(fn () => ReceiveOrder::nextNumber())
                            ->readOnly()
                            ->dehydrated()
                            ->required(),
                        Select::make('purchase_order_id')
                            ->label('Dari PO')
                            ->options(fn () => PurchaseOrder::query()
                                ->whereIn('status_receive_order', [PurchaseOrder::STATUS_PENDING, PurchaseOrder::STATUS_PARTIAL])
                                ->with('supplier')
                                ->orderByDesc('po_date')
                                ->get()
                                ->mapWithKeys(fn (PurchaseOrder $po) => [$po->id => $po->po_number.' — '.($po->supplier?->name ?? '')]))
                            ->searchable()
                            ->live()
                            ->disabled(fn (?ReceiveOrder $record) => $record !== null)
                            ->dehydrated()
                            ->helperText('Opsional. Satu PO bisa dipenuhi banyak faktur.')
                            ->afterStateUpdated(function (Get $get, Set $set, $state) {
                                $po = $state ? PurchaseOrder::find($state) : null;
                                if ($po) {
                                    $set('supplier_id', $po->supplier_id);
                                }
                                $set('po_pick', []);
                                $set('items', array_values(array_filter($get('items') ?? [], fn ($row) => empty($row['from_po']))));
                            }),
                        Select::make('supplier_id')
                            ->label('PBF')
                            ->relationship('supplier', 'name', fn ($query) => $query->where('status', 'active')->orderBy('name'))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->live()
                            ->disabled(fn (Get $get) => filled($get('purchase_order_id')))
                            ->dehydrated(),
                        TextInput::make('invoice_number')
                            ->label('Nomor faktur PBF')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('02028/NPM/5/24')
                            ->rules([
                                fn (Get $get, ?ReceiveOrder $record) => Rule::unique('receive_orders', 'invoice_number')
                                    ->where('supplier_id', $get('supplier_id'))
                                    ->whereNull('deleted_at')
                                    ->ignore($record?->id),
                            ])
                            ->validationMessages([
                                'unique' => 'Nomor faktur ini sudah pernah dicatat untuk PBF yang sama.',
                            ]),
                        DatePicker::make('receive_date')
                            ->label('Tanggal terima')
                            ->default(now())
                            ->required()
                            ->live(onBlur: true),
                        Hidden::make('received_by')
                            ->default(fn () => auth()->id()),
                    ]),

                Section::make('Pilih item dari PO')
                    ->description('Centang item yang tercetak di faktur ini. Sisa PO berkurang bertahap sampai lengkap.')
                    ->columnSpanFull()
                    ->visible(fn (Get $get, ?ReceiveOrder $record) => $record === null && filled($get('purchase_order_id')))
                    ->schema([
                        CheckboxList::make('po_pick')
                            ->hiddenLabel()
                            ->dehydrated(false)
                            ->live()
                            ->columns(2)
                            ->options(fn (Get $get) => self::poRemainingOptions($get('purchase_order_id')))
                            ->afterStateUpdated(fn (Get $get, Set $set, $state) => self::syncItemsFromPo($get, $set, (array) $state)),
                    ]),

                Section::make('Item faktur')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('items')
                            ->relationship()
                            ->hiddenLabel()
                            ->schema([
                                Hidden::make('from_po')->dehydrated(false),
                                Hidden::make('po_max_qty')->dehydrated(false),
                                Hidden::make('medicine_name'),
                                Select::make('medicine_id')
                                    ->label('Obat')
                                    ->columnSpan(4)
                                    ->options(fn () => Medicine::query()->where('status', 'active')->orderBy('name')->pluck('name', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->live()
                                    ->disabled(fn (Get $get) => (bool) $get('from_po'))
                                    ->dehydrated()
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        PackLine::applyMedicineDefaults($set, $state ? (int) $state : null, defaultPackQty: 1);
                                        $set('medicine_name', $state ? Medicine::query()->whereKey($state)->value('name') : null);
                                    }),
                                PackLine::packUnitSelect()->columnSpan(2),
                                PackLine::packQtyInput('Jumlah')
                                    ->columnSpan(2)
                                    ->rules([
                                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                            // Q6: baris dari PO tidak boleh melebihi sisa PO (dalam satuan jual).
                                            if (! $get('from_po')) {
                                                return;
                                            }
                                            [$qty] = PackLine::convert($value, $get('pack_size'), null);
                                            $max = (int) $get('po_max_qty');
                                            if ($qty !== null && $qty > $max) {
                                                $fail("Melebihi sisa PO ({$max} satuan jual). Kelebihan kiriman dicatat sebagai baris di luar PO.");
                                            }
                                        },
                                    ]),
                                PackLine::packSizeInput()->columnSpan(2),
                                PackLine::packPriceInput('Harga per kemasan')->columnSpan(2),
                                TextInput::make('batch_number')
                                    ->label('No. batch')
                                    ->required()
                                    ->maxLength(100)
                                    ->columnSpan(3),
                                TextInput::make('expired_month')
                                    ->label('ED (bulan-tahun)')
                                    ->placeholder('10-2026')
                                    ->required()
                                    ->regex('/^(0[1-9]|1[0-2])-\d{4}$/')
                                    ->validationMessages(['regex' => 'Tulis bulan-tahun, mis. 10-2026.'])
                                    ->helperText('Seperti tercetak di faktur. Obat dianggap kedaluwarsa sejak tanggal 1 bulan itu.')
                                    ->rules([
                                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                                            $ed = self::parseExpiredMonth($value);
                                            $received = $get('../../receive_date');
                                            if ($ed && $received && $ed->lte(Carbon::parse($received)->startOfDay())) {
                                                $fail('ED harus lewat dari tanggal terima.');
                                            }
                                        },
                                    ])
                                    ->columnSpan(3),
                                PackLine::subtotalPreview()->columnSpan(6),
                            ])
                            ->columns(12)
                            ->columnSpanFull()
                            ->addActionLabel('Tambah item di luar PO')
                            ->minItems(1)
                            ->mutateRelationshipDataBeforeCreateUsing(fn (array $data) => self::dehydrateItem($data))
                            ->mutateRelationshipDataBeforeSaveUsing(fn (array $data) => self::dehydrateItem($data))
                            ->mutateRelationshipDataBeforeFillUsing(fn (array $data) => self::hydrateItem($data)),

                        Placeholder::make('total_preview')
                            ->label('Total RO')
                            ->content(function (Get $get) {
                                $total = 0;
                                foreach ($get('items') ?? [] as $row) {
                                    [$qty, $price] = PackLine::convert($row['pack_qty'] ?? 0, $row['pack_size'] ?? 1, $row['pack_price'] ?? null);
                                    $total += ($qty ?? 0) * ($price ?? 0);
                                }

                                return new HtmlString('<span class="text-lg font-bold">Rp '.number_format($total, 0, ',', '.').'</span> <span class="text-sm text-gray-500">— cocokkan dengan Total pada faktur (sudah termasuk PPN)</span>');
                            }),
                    ]),
            ]);
    }

    /** Opsi centang: item PO yang masih bersisa. */
    public static function poRemainingOptions($poId): array
    {
        $po = $poId ? PurchaseOrder::with(['items.medicine.unit'])->find($poId) : null;
        if (! $po) {
            return [];
        }

        $remaining = $po->remainingByMedicine();
        $options = [];

        foreach ($po->items as $item) {
            $sisa = $remaining[$item->medicine_id] ?? 0;
            if ($sisa <= 0) {
                continue;
            }
            $unit = $item->medicine?->unit?->name ?? '';
            $options[$item->medicine_id] = sprintf(
                '%s — dipesan %d, sisa %d %s',
                $item->medicine?->name,
                $item->qty,
                $sisa,
                $unit,
            );
        }

        return $options;
    }

    /** Bangun/buang baris dari centang PO; baris di luar PO tidak disentuh. */
    public static function syncItemsFromPo(Get $get, Set $set, array $checked): void
    {
        $poId = $get('purchase_order_id');
        $po = $poId ? PurchaseOrder::with(['items.medicine'])->find($poId) : null;
        if (! $po) {
            return;
        }

        $checked = array_map('intval', $checked);
        $remaining = $po->remainingByMedicine();
        $items = $get('items') ?? [];

        // Buang baris PO yang tidak lagi dicentang, dan baris kosong bawaan repeater.
        $items = array_filter($items, fn ($row) => ! empty($row['from_po'])
            ? in_array((int) ($row['medicine_id'] ?? 0), $checked, true)
            : filled($row['medicine_id'] ?? null));
        $present = array_map(fn ($row) => (int) ($row['medicine_id'] ?? 0), array_filter($items, fn ($row) => ! empty($row['from_po'])));

        foreach ($po->items as $poItem) {
            $mid = (int) $poItem->medicine_id;
            if (! in_array($mid, $checked, true) || in_array($mid, $present, true)) {
                continue;
            }

            $sisa = (int) ($remaining[$mid] ?? 0);
            $packSize = max(1, (int) $poItem->pack_size);
            $medicineUnitId = (int) $poItem->medicine?->unit_id;

            // Sisa habis dibagi isi kemasan PO → tampil dalam kemasan; kalau tidak → eceran (satuan jual).
            if ($sisa % $packSize === 0) {
                $packUnitId = $poItem->pack_unit_id;
                $packQty = intdiv($sisa, $packSize);
            } else {
                $packUnitId = $medicineUnitId;
                $packSize = 1;
                $packQty = $sisa;
            }

            $items[] = [
                'from_po' => true,
                'po_max_qty' => $sisa,
                'medicine_id' => $mid,
                'medicine_name' => $poItem->medicine?->name,
                'pack_unit_id' => $packUnitId,
                'pack_size' => $packSize,
                'pack_qty' => $packQty,
                'pack_price' => round((float) $poItem->price * $packSize, 2),
                'batch_number' => null,
                'expired_month' => null,
            ];
        }

        $set('items', array_values($items));
    }

    public static function parseExpiredMonth(?string $value): ?Carbon
    {
        if (! $value || ! preg_match('/^(0[1-9]|1[0-2])-(\d{4})$/', $value, $m)) {
            return null;
        }

        return Carbon::create((int) $m[2], (int) $m[1], 1)->startOfDay();
    }

    public static function dehydrateItem(array $data): array
    {
        $data = PackLine::dehydrate($data);
        $data['expired_date'] = self::parseExpiredMonth($data['expired_month'] ?? null)?->toDateString();
        $data['medicine_name'] = $data['medicine_name']
            ?? Medicine::query()->whereKey($data['medicine_id'] ?? null)->value('name');
        unset($data['expired_month'], $data['from_po'], $data['po_max_qty']);

        return $data;
    }

    public static function hydrateItem(array $data): array
    {
        $data = PackLine::hydrate($data);
        $data['expired_month'] = isset($data['expired_date']) ? Carbon::parse($data['expired_date'])->format('m-Y') : null;

        return $data;
    }
}
