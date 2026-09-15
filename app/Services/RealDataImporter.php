<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\MedicineCategories;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Support\RealDataTemplate;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Importer data riil apotek dari template RealDataTemplate (rencana-revisi-2026-09 D5, C1–C3).
 *
 * Urutan muat menjaga urutan waktu (C1): saldo awal ditulis sebagai RO "SALDO AWAL" bertanggal
 * awal periode (C2, supaya obat langsung punya HPP), lalu faktur dalam periode, lalu penjualan.
 * Semua lewat StockMovementService — lapisan batch, HPP, FEFO sama dengan jalur UI.
 * Obat didedup berdasarkan nama ternormalisasi (M6). Seluruh muatan satu transaksi:
 * satu baris salah → tidak ada yang tersimpan; daftar masalah dikembalikan.
 */
class RealDataImporter
{
    public const OPENING_SUPPLIER_CODE = 'SALDO-AWAL';

    /** @var array<int, string> */
    protected array $errors = [];

    /** @var array<string, int> */
    protected array $summary = [];

    public function __construct(
        private StockMovementService $movement,
        private StockCardService $stockCard,
    ) {}

    /**
     * @return array{errors: array<int,string>, summary: array<string,int>}
     */
    public function import(string $path, Carbon $periodStart, bool $dryRun = false): array
    {
        $this->errors = [];
        $this->summary = ['obat' => 0, 'saldo_awal' => 0, 'faktur' => 0, 'faktur_baris' => 0, 'penjualan' => 0, 'penjualan_baris' => 0];

        $sheets = $this->readSheets($path);
        if ($this->errors) {
            return $this->result();
        }

        DB::beginTransaction();
        try {
            $medicines = $this->importMedicines($sheets['Obat']);
            $this->importOpeningBalance($sheets['SaldoAwal'], $medicines, $periodStart);
            $this->importInvoices($sheets['Faktur'], $medicines);
            $this->importSales($sheets['Penjualan'], $medicines);

            if ($this->errors || $dryRun) {
                DB::rollBack();
            } else {
                $this->movement->refreshStockStatus(array_values($medicines));
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->errors[] = 'Gagal: '.$e->getMessage();
        }

        return $this->result();
    }

    /** @return array{errors: array<int,string>, summary: array<string,int>} */
    protected function result(): array
    {
        return ['errors' => $this->errors, 'summary' => $this->summary];
    }

    // ---------------------------------------------------------------------
    // Pembacaan
    // ---------------------------------------------------------------------

    /**
     * @return array<string, array<int, array<string, mixed>>> sheet => baris (keyed by header, baris petunjuk & contoh dilewati)
     */
    protected function readSheets(string $path): array
    {
        if (! is_file($path)) {
            $this->errors[] = "Berkas tidak ditemukan: {$path}";

            return [];
        }

        $spreadsheet = IOFactory::load($path);
        $result = [];

        foreach (RealDataTemplate::SHEETS as $title => $columns) {
            $sheet = $spreadsheet->getSheetByName($title);
            if (! $sheet) {
                $this->errors[] = "Sheet '{$title}' tidak ada.";
                $result[$title] = [];

                continue;
            }

            $rows = $sheet->toArray(null, true, false, false);
            $header = array_map(fn ($h) => strtolower(trim((string) $h)), $rows[0] ?? []);
            $expected = array_keys($columns);
            if (array_slice($header, 0, count($expected)) !== $expected) {
                $this->errors[] = "Sheet '{$title}': kolom harus ".implode(', ', $expected).'.';
            }

            $data = [];
            foreach (array_slice($rows, 1) as $i => $row) {
                $excelRow = $i + 2;
                // Baris 2 = petunjuk (teks di semua kolom); baris kosong dilewati.
                if ($excelRow === 2 || count(array_filter($row, fn ($v) => $v !== null && $v !== '')) === 0) {
                    continue;
                }
                $assoc = [];
                foreach ($expected as $k => $name) {
                    $assoc[$name] = $row[$k] ?? null;
                }
                $assoc['_row'] = $excelRow;
                $data[] = $assoc;
            }
            $result[$title] = $data;
        }

        return $result;
    }

    // ---------------------------------------------------------------------
    // Obat
    // ---------------------------------------------------------------------

    /** @return array<string, int> nama ternormalisasi => id */
    protected function importMedicines(array $rows): array
    {
        // Satuan dikenali lewat nama maupun alias (faktur PBF menulis FLS/KLG/AMP/TUBE).
        $units = [];
        foreach (Unit::all() as $u) {
            $units[strtolower($u->name)] = $u;
            $units[strtolower($u->alias)] = $u;
        }
        $categories = MedicineCategories::all()->keyBy(fn ($c) => strtolower($c->name));
        // Golongan lama di berkas apotek: "Obat Bebas Terbatas" dilebur ke Obat Bebas.
        if (isset($categories['obat bebas'])) {
            $categories['obat bebas terbatas'] = $categories['obat bebas'];
        }
        $map = [];

        foreach ($rows as $row) {
            $name = Medicine::normalizeName((string) $row['nama']);
            if ($name === '') {
                $this->errors[] = "Obat baris {$row['_row']}: nama kosong.";

                continue;
            }
            $category = $categories[strtolower(trim((string) $row['kategori']))] ?? null;
            $unit = $units[strtolower(trim((string) $row['satuan_jual']))] ?? null;
            $pack = $units[strtolower(trim((string) $row['kemasan']))] ?? null;
            if (! $category || ! $unit || ! $pack) {
                $this->errors[] = "Obat baris {$row['_row']} ({$name}): kategori/satuan jual/kemasan tidak dikenal.";

                continue;
            }
            $packSize = max(1, (int) $row['isi_kemasan']);

            // min_stock boleh ditulis "200 Tablet"; satuannya (bila ada) harus = satuan jual.
            $minStock = (int) $row['min_stock'];
            if (preg_match('/^\s*(\d+)\s*([A-Za-z]+)\s*$/', (string) $row['min_stock'], $m)) {
                $minStock = (int) $m[1];
                $suffix = $units[strtolower($m[2])] ?? null;
                if (! $suffix || $suffix->id !== $unit->id) {
                    $this->errors[] = "Obat baris {$row['_row']} ({$name}): satuan stok minimum '{$m[2]}' tidak sama dengan satuan jual '{$unit->name}'.";

                    continue;
                }
            }

            $medicine = Medicine::firstOrNew(['name' => $name]);
            $medicine->fill([
                'name' => $name,
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'pack_unit_id' => $pack->id,
                'pack_size' => $packSize,
                'min_stock' => $minStock > 0 ? $minStock : ($medicine->exists ? $medicine->min_stock : 0),
                'status' => 'active',
            ]);
            if (! $medicine->exists) {
                $medicine->stock_status = 'empty';
            }
            $medicine->save();

            $map[$name] = $medicine->id;
            $this->summary['obat']++;
        }

        return $map;
    }

    protected function medicineId(array $map, $name, string $sheet, int $row): ?int
    {
        $key = Medicine::normalizeName((string) $name);
        if (! isset($map[$key])) {
            $this->errors[] = "{$sheet} baris {$row}: obat '{$key}' tidak ada di sheet Obat.";

            return null;
        }

        return $map[$key];
    }

    // ---------------------------------------------------------------------
    // Saldo awal → RO "SALDO AWAL"
    // ---------------------------------------------------------------------

    protected function importOpeningBalance(array $rows, array $map, Carbon $periodStart): void
    {
        if (! $rows) {
            return;
        }

        $supplier = Supplier::firstOrCreate(
            ['code' => self::OPENING_SUPPLIER_CODE],
            ['name' => 'SALDO AWAL (bukan PBF)', 'status' => 'active'],
        );

        $ro = ReceiveOrder::create([
            'receive_order_number' => ReceiveOrder::nextNumber($periodStart),
            'invoice_number' => 'SALDO-AWAL-'.$periodStart->format('Ymd'),
            'supplier_id' => $supplier->id,
            'receive_date' => $periodStart->toDateString(),
            'received_by' => auth()->id(),
        ]);

        $lines = 0;
        foreach ($rows as $row) {
            $mid = $this->medicineId($map, $row['nama_obat'], 'SaldoAwal', $row['_row']);
            $ed = $this->parseMonth($row['ed'], 'SaldoAwal', $row['_row']);
            $qty = (int) $row['jumlah'];
            $price = (float) $row['harga_per_satuan_jual'];
            if (! $mid || ! $ed) {
                continue;
            }
            if ($qty <= 0 || $price <= 0) {
                $this->errors[] = "SaldoAwal baris {$row['_row']}: jumlah dan harga harus > 0.";

                continue;
            }
            $medicine = Medicine::find($mid);
            ReceiveOrderItem::create([
                'receive_order_id' => $ro->id,
                'medicine_id' => $mid,
                'medicine_name' => $medicine->name,
                'pack_unit_id' => $medicine->unit_id,
                'pack_size' => 1,
                'pack_qty' => $qty,
                'qty' => $qty,
                'price' => $price,
                'batch_number' => trim((string) $row['batch']) ?: 'SALDO-AWAL',
                'expired_date' => $ed,
            ]);
            $lines++;
        }

        if ($lines > 0) {
            $this->movement->recordReceipt($ro);
            $this->summary['saldo_awal'] = $lines;
        } else {
            $ro->forceDelete();
        }
    }

    // ---------------------------------------------------------------------
    // Faktur → RO per (PBF, nomor faktur)
    // ---------------------------------------------------------------------

    protected function importInvoices(array $rows, array $map): void
    {
        $groups = [];
        foreach ($rows as $row) {
            $key = trim((string) $row['pbf']).'|'.trim((string) $row['no_faktur']);
            $groups[$key][] = $row;
        }

        foreach ($groups as $key => $lines) {
            [$pbfName, $invoice] = explode('|', $key, 2);
            if ($pbfName === '' || $invoice === '') {
                $this->errors[] = "Faktur baris {$lines[0]['_row']}: PBF dan nomor faktur wajib.";

                continue;
            }
            $date = $this->parseDate($lines[0]['tanggal'], 'Faktur', $lines[0]['_row']);
            if (! $date) {
                continue;
            }

            // Kode PBF dibuat model dari inisial nama (Supplier::nextCode).
            $supplier = Supplier::firstOrCreate(['name' => $pbfName], ['status' => 'active']);

            if (ReceiveOrder::where('supplier_id', $supplier->id)->where('invoice_number', $invoice)->exists()) {
                $this->errors[] = "Faktur {$invoice} ({$pbfName}) sudah pernah diimpor.";

                continue;
            }

            $ro = ReceiveOrder::create([
                'receive_order_number' => ReceiveOrder::nextNumber($date),
                'invoice_number' => $invoice,
                'supplier_id' => $supplier->id,
                'receive_date' => $date->toDateString(),
                'received_by' => auth()->id(),
            ]);

            $units = Unit::all()->keyBy(fn (Unit $u) => strtolower($u->name));
            $written = 0;
            foreach ($lines as $row) {
                $mid = $this->medicineId($map, $row['nama_obat'], 'Faktur', $row['_row']);
                $ed = $this->parseMonth($row['ed'], 'Faktur', $row['_row']);
                if (! $mid || ! $ed) {
                    continue;
                }
                $medicine = Medicine::find($mid);
                $pack = $units[strtolower(trim((string) $row['kemasan']))] ?? null;
                $packSize = max(1, (int) $row['isi']);
                $packQty = (int) $row['jumlah_kemasan'];
                $packPrice = (float) $row['harga_per_kemasan'];
                if (! $pack || $packQty <= 0) {
                    $this->errors[] = "Faktur baris {$row['_row']}: kemasan tidak dikenal atau jumlah ≤ 0.";

                    continue;
                }
                if ($ed->lte($date)) {
                    $this->errors[] = "Faktur baris {$row['_row']}: ED harus lewat dari tanggal terima.";

                    continue;
                }

                ReceiveOrderItem::create([
                    'receive_order_id' => $ro->id,
                    'medicine_id' => $mid,
                    'medicine_name' => $medicine->name,
                    'pack_unit_id' => $pack->id,
                    'pack_size' => $packSize,
                    'pack_qty' => $packQty,
                    'qty' => $packQty * $packSize,
                    'price' => round($packPrice / $packSize, 2),
                    'batch_number' => trim((string) $row['batch']) ?: '-',
                    'expired_date' => $ed,
                ]);
                $written++;
            }

            if ($written > 0) {
                $this->movement->recordReceipt($ro);
                $this->summary['faktur']++;
                $this->summary['faktur_baris'] += $written;
            } else {
                $ro->forceDelete();
            }
        }
    }

    // ---------------------------------------------------------------------
    // Penjualan → satu order per tanggal per ≤ 5 baris
    // ---------------------------------------------------------------------

    protected function importSales(array $rows, array $map): void
    {
        $byDate = [];
        foreach ($rows as $row) {
            $date = $this->parseDate($row['tanggal'], 'Penjualan', $row['_row']);
            $mid = $this->medicineId($map, $row['nama_obat'], 'Penjualan', $row['_row']);
            if (! $date || ! $mid) {
                continue;
            }
            $qty = (int) $row['jumlah'];
            if ($qty <= 0) {
                $this->errors[] = "Penjualan baris {$row['_row']}: jumlah harus > 0.";

                continue;
            }
            $byDate[$date->toDateString()][] = [$mid, $qty, $row['harga_jual'] !== null && $row['harga_jual'] !== '' ? (float) $row['harga_jual'] : null, $row['_row']];
        }
        ksort($byDate);

        foreach ($byDate as $date => $lines) {
            foreach (array_chunk($lines, 5) as $chunk) {
                $order = Order::create([
                    'order_code' => $this->nextImportedOrderCode($date),
                    'order_date' => $date,
                    'grand_total' => 0,
                    'note' => 'Impor data riil',
                    'created_by' => auth()->id(),
                ]);

                $total = 0;
                foreach ($chunk as [$mid, $qty, $price, $excelRow]) {
                    $hpp = $this->stockCard->currentHpp($mid);
                    $salePrice = $price ?? (float) ($hpp ?? 0);
                    OrderItem::create([
                        'order_id' => $order->id,
                        'medicine_id' => $mid,
                        'medicine_name' => Medicine::find($mid)->name,
                        'qty' => $qty,
                        'price' => $salePrice,
                    ]);
                    $total += $qty * $salePrice;
                }
                $order->update(['grand_total' => $total]);

                try {
                    $this->movement->recordSale($order);
                } catch (\RuntimeException $e) {
                    $this->errors[] = "Penjualan {$date} (baris ".implode(',', array_column($chunk, 3)).'): '.$e->getMessage();

                    continue;
                }

                $this->summary['penjualan']++;
                $this->summary['penjualan_baris'] += count($chunk);
            }
        }
    }

    protected function nextImportedOrderCode(string $date): string
    {
        $prefix = 'ORD-'.Carbon::parse($date)->format('Ymd');
        $last = Order::withTrashed()->where('order_code', 'like', $prefix.'%')->orderByDesc('order_code')->value('order_code');
        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    // ---------------------------------------------------------------------
    // Parser
    // ---------------------------------------------------------------------

    /** MM-YYYY (atau tanggal Excel) → Carbon tanggal 1 bulan itu (R3). */
    protected function parseMonth($value, string $sheet, int $row): ?Carbon
    {
        if (is_numeric($value) && (float) $value > 1000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfMonth()->startOfDay();
        }
        $value = trim((string) $value);
        if (preg_match('/^(0?[1-9]|1[0-2])[-\/](\d{2}|\d{4})$/', $value, $m)) {
            $year = strlen($m[2]) === 2 ? 2000 + (int) $m[2] : (int) $m[2];

            return Carbon::create($year, (int) $m[1], 1)->startOfDay();
        }
        $this->errors[] = "{$sheet} baris {$row}: ED '{$value}' harus bulan-tahun (mis. 10-2026).";

        return null;
    }

    /** DD-MM-YYYY (atau tanggal Excel) → Carbon. */
    protected function parseDate($value, string $sheet, int $row): ?Carbon
    {
        if (is_numeric($value) && (float) $value > 1000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->startOfDay();
        }
        $value = trim((string) $value);
        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                $d = Carbon::createFromFormat($format, $value);
                if ($d && $d->format($format) === $value) {
                    return $d->startOfDay();
                }
            } catch (\Throwable) {
            }
        }
        $this->errors[] = "{$sheet} baris {$row}: tanggal '{$value}' harus DD-MM-YYYY.";

        return null;
    }
}
