<?php

namespace Database\Seeders;

use App\Models\Medicine;
use App\Models\MedicineCategories;
use App\Models\MedicineStock;
use App\Models\MedicineStockOpname;
use App\Models\MedicineStockOpnameItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockCardService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class DemoApotekSeeder extends Seeder
{
    protected const MARKER = '[DEMO_DATA]';

    protected const TOTAL_MEDICINES = 120;

    protected const DAYS_BACK = 364;

    protected const CYCLE_DAYS = 14;      // procurement tiap 2 minggu

    protected const OPNAME_EVERY = 91;    // opname tiap ~3 bulan

    protected ?int $userId = null;

    protected int $poSeq = 1;

    protected int $roSeq = 1;

    protected int $opmSeq = 1;

    /** @var array<string,int> Ymd => sequence order pada hari itu */
    protected array $orderSeqByDate = [];

    /** @var array<int,int> medicine_id => stok berjalan */
    protected array $stock = [];

    /** @var array<int,int> harga beli per obat (dipakai PO/RO/opname) */
    protected array $purchasePrice = [];

    /** @var array<int,int> harga jual per obat (dipakai order) */
    protected array $salePrice = [];

    /** @var array<int,int> medicine_id => bobot popularitas (0 = dead stock) */
    protected array $pop = [];

    /** @var array<int,int> medicine_id => target stok (reorder-up-to) */
    protected array $target = [];

    /** @var array<int,int> medicine_id => titik reorder */
    protected array $reorder = [];

    /** @var array<int,Medicine> */
    protected array $medById = [];

    /** @var array<int,int> */
    protected array $medIds = [];

    /** @var array<string,array<int,array>> Ymd penerimaan => daftar spesifikasi RO */
    protected array $pendingReceipts = [];

    protected int $orderTotal = 0;

    protected int $stockEntryTotal = 0;

    public function run(): void
    {
        $this->userId = User::value('id');

        $this->command->info('Membersihkan data demo lama...');
        $this->cleanup();

        $this->command->info('Menyiapkan master data (kategori/unit/rak/supplier)...');
        $suppliers = $this->ensureMasterData();

        $this->command->info('Membuat ' . self::TOTAL_MEDICINES . ' data obat...');
        $this->seedMedicines();
        $this->writeSeededMedicineIds();

        $this->command->info('Menentukan popularitas & target stok tiap obat...');
        $this->planMedicines();

        $this->command->info('Menyiapkan penomoran (PO/RO/OPM)...');
        $this->initCounters();

        $this->command->info('Simulasi transaksi 1 tahun (PO → RO → penjualan → opname)...');
        $this->simulate($suppliers);

        $this->command->info('Membuat beberapa PO outstanding (belum diterima)...');
        $this->seedOutstandingPurchaseOrders($suppliers);

        $this->command->info('Memperbarui status stok tiap obat...');
        $stockService = app(StockCardService::class);
        foreach ($this->medIds as $id) {
            $stockService->updateMedicineStockStatus($id);
        }

        $this->command->info(sprintf(
            'Selesai. %d order, %d entri kartu stok. Jalankan: php artisan sipokat:recalculate-saw',
            $this->orderTotal,
            $this->stockEntryTotal,
        ));
    }

    protected function cleanup(): void
    {
        $orderIds = Order::withTrashed()->where('note', 'like', self::MARKER . '%')->pluck('id')->all();
        if ($orderIds) {
            MedicineStock::whereIn('order_id', $orderIds)->delete();
            OrderItem::withTrashed()->whereIn('order_id', $orderIds)->forceDelete();
            Order::withTrashed()->whereIn('id', $orderIds)->forceDelete();
        }

        $roIds = ReceiveOrder::withTrashed()->where('description', 'like', self::MARKER . '%')->pluck('id')->all();
        if ($roIds) {
            MedicineStock::whereIn('receive_order_id', $roIds)->delete();
            ReceiveOrderItem::withTrashed()->whereIn('receive_order_id', $roIds)->forceDelete();
            ReceiveOrder::withTrashed()->whereIn('id', $roIds)->forceDelete();
        }

        $opIds = MedicineStockOpname::withTrashed()->where('description', 'like', self::MARKER . '%')->pluck('id')->all();
        if ($opIds) {
            MedicineStock::whereIn('medicine_stock_opname_id', $opIds)->delete();
            MedicineStockOpnameItem::withTrashed()->whereIn('medicine_stock_opname_id', $opIds)->forceDelete();
            MedicineStockOpname::withTrashed()->whereIn('id', $opIds)->forceDelete();
        }

        $poIds = PurchaseOrder::withTrashed()->where('description', 'like', self::MARKER . '%')->pluck('id')->all();
        if ($poIds) {
            PurchaseOrderItem::withTrashed()->whereIn('purchase_order_id', $poIds)->forceDelete();
            PurchaseOrder::withTrashed()->whereIn('id', $poIds)->forceDelete();
        }

        $medIds = $this->readSeededMedicineIds();
        if ($medIds) {
            MedicineStock::whereIn('medicine_id', $medIds)->delete();
            Medicine::withTrashed()->whereIn('id', $medIds)->forceDelete();
        }

        Supplier::withTrashed()->where('code', 'like', 'DMO-%')->forceDelete();
    }

    /**
     * @return array<int,Supplier>
     */
    protected function ensureMasterData(): array
    {
        // Kategori & unit — buat bila belum ada (mengikuti MasterDataSeeder).
        if (Unit::count() === 0) {
            foreach ([['Pcs', 'PCS'], ['Strip', 'STR'], ['Flask', 'FLS'], ['Sachet', 'SCH'], ['Box', 'BOX'], ['Tube', 'TUB']] as [$n, $a]) {
                Unit::updateOrCreate(['name' => $n], ['name' => $n, 'alias' => $a]);
            }
        }
        if (MedicineCategories::count() === 0) {
            foreach ([
                ['Obat Bebas', 'OBB', 'Obat yang dapat dibeli bebas tanpa resep dokter'],
                ['Obat Keras', 'OBK', 'Obat yang penyerahannya harus dengan resep dokter'],
            ] as [$n, $a, $d]) {
                MedicineCategories::updateOrCreate(['name' => $n], ['name' => $n, 'alias' => $a, 'description' => $d]);
            }
        }

        // Supplier demo (distributor farmasi nyata) — dihapus & dibuat ulang tiap seed.
        $data = [
            ['DMO-01', 'PT Kimia Farma Trading & Distribution', 'Semarang', 'Andi Pratama'],
            ['DMO-02', 'PT Enseval Putera Megatrading', 'Semarang', 'Rina Wijaya'],
            ['DMO-03', 'PT Anugrah Pharmindo Lestari (APL)', 'Demak', 'Bagus Santoso'],
            ['DMO-04', 'PT Merapi Utama Pharma', 'Semarang', 'Dewi Lestari'],
        ];
        $suppliers = [];
        foreach ($data as $i => [$code, $name, $city, $pic]) {
            $suppliers[] = Supplier::create([
                'code' => $code,
                'name' => $name,
                'address' => 'Jl. Distributor No. ' . ($i + 1) . ', ' . $city,
                'phone' => '024-' . mt_rand(3000000, 8999999),
                'email' => 'sales' . ($i + 1) . '@distributor.co.id',
                'pic' => $pic,
                'status' => 'active',
            ]);
        }

        return $suppliers;
    }

    protected function seedMedicines(): void
    {
        $categories = MedicineCategories::all()->keyBy('id');
        $units = Unit::all()->keyBy('id');
        $categoryIds = $categories->keys()->all();
        $unitIds = $units->keys()->all();

        $names = $this->medicineNamePool();
        $seen = [];

        for ($i = 1; $i <= self::TOTAL_MEDICINES; $i++) {
            $baseName = strtoupper($names[$i - 1] ?? ('Generik Obat ' . $i));
            $name = $baseName . ' ' . strtoupper($this->randomDosage());
            while (isset($seen[$name])) {
                $name = $baseName . ' ' . strtoupper($this->randomDosage());
            }
            $seen[$name] = true;

            $categoryId = $categoryIds[array_rand($categoryIds)];
            $unitId = $unitIds[array_rand($unitIds)];

            $purchase = $this->randomPurchasePrice();
            $sale = (int) (round(($purchase * mt_rand(115, 150) / 100) / 100) * 100);

            [$packUnitId, $packSize] = $this->packFor($units[$unitId], $units);

            $medicine = Medicine::create([
                'name' => $name,
                'category_id' => $categoryId,
                'unit_id' => $unitId,
                'pack_unit_id' => $packUnitId,
                'pack_size' => $packSize,
                'min_stock' => mt_rand(5, 20),
                'stock_status' => 'empty',
                'status' => 'active',
            ]);

            // Harga hidup di transaksi (M4, M5); seeder menyimpannya sementara di memori.
            $this->purchasePrice[$medicine->id] = $purchase;
            $this->salePrice[$medicine->id] = $sale;

            $this->medById[$medicine->id] = $medicine;
            $this->medIds[] = $medicine->id;
            $this->stock[$medicine->id] = 0;
        }
    }

    protected function planMedicines(): void
    {
        foreach ($this->medIds as $id) {
            $roll = mt_rand(1, 100);
            $pop = match (true) {
                $roll <= 8 => 0,                // dead stock (~8%)
                $roll <= 38 => mt_rand(3, 15),  // slow moving
                $roll <= 78 => mt_rand(16, 45), // medium
                default => mt_rand(46, 100),    // fast moving
            };
            $this->pop[$id] = $pop;

            $target = $pop === 0
                ? mt_rand(15, 45)
                : max(12, (int) round($pop * mt_rand(120, 220) / 100));
            $this->target[$id] = $target;
            $this->reorder[$id] = max(5, (int) round($target * 0.4));
        }
    }

    protected function initCounters(): void
    {
        $this->poSeq = $this->maxNumber(PurchaseOrder::withTrashed()->pluck('po_number')->all()) + 1;
        $this->roSeq = $this->maxNumber(ReceiveOrder::withTrashed()->pluck('receive_order_number')->all()) + 1;
        $this->opmSeq = $this->maxNumber(MedicineStockOpname::withTrashed()->pluck('opname_number')->all()) + 1;
    }

    /**
     * @param  array<int,Supplier>  $suppliers
     */
    protected function simulate(array $suppliers): void
    {
        $start = Carbon::today()->subDays(self::DAYS_BACK);

        for ($d = 0; $d <= self::DAYS_BACK; $d++) {
            $date = (clone $start)->addDays($d);
            $key = $date->format('Y-m-d');

            // 1. Barang datang (RO) untuk penerimaan yang jatuh hari ini.
            if (! empty($this->pendingReceipts[$key])) {
                foreach ($this->pendingReceipts[$key] as $spec) {
                    $this->createReceiveOrder($spec, $date);
                }
                unset($this->pendingReceipts[$key]);
            }

            // 2. Procurement (PO) — hari pertama & tiap CYCLE_DAYS.
            if ($d === 0 || $d % self::CYCLE_DAYS === 0) {
                $this->createProcurementCycle($date, $suppliers, $d === 0);
            }

            // 3. Opname berkala.
            if ($d > 0 && $d % self::OPNAME_EVERY === 0) {
                $this->createOpname($date);
            }

            // 4. Penjualan harian (tutup hari Minggu).
            if ($date->dayOfWeek !== Carbon::SUNDAY) {
                $this->createDailySales($date);
            }
        }
    }

    /**
     * Buat PO untuk obat di bawah titik reorder (hari pertama: semua obat).
     *
     * @param  array<int,Supplier>  $suppliers
     */
    protected function createProcurementCycle(Carbon $date, array $suppliers, bool $isInitial): void
    {
        $needing = [];
        foreach ($this->medIds as $id) {
            $qtyNeeded = $isInitial
                ? $this->target[$id]
                : ($this->stock[$id] < $this->reorder[$id] ? $this->target[$id] - $this->stock[$id] : 0);

            if ($qtyNeeded > 0) {
                $needing[$id] = $qtyNeeded;
            }
        }

        if (empty($needing)) {
            return;
        }

        // Kelompokkan ke 1-3 PO (per supplier) supaya realistis.
        $ids = array_keys($needing);
        shuffle($ids);
        $poCount = $isInitial ? 3 : mt_rand(1, 2);
        $chunks = array_chunk($ids, (int) ceil(count($ids) / $poCount));

        foreach ($chunks as $chunk) {
            $supplier = $suppliers[array_rand($suppliers)];
            $lines = [];
            $subTotal = 0;

            foreach ($chunk as $id) {
                $m = $this->medById[$id];
                $qty = $needing[$id];
                $price = $this->purchasePrice[$id];
                $total = $qty * $price;
                $subTotal += $total;
                $lines[] = [
                    'medicine_id' => $id,
                    'qty' => $qty,
                    'price' => $price,
                    'total' => $total,
                    'name' => $m->name,
                ];
            }

            $shipping = mt_rand(0, 3) === 0 ? mt_rand(15000, 75000) : 0;
            $grandTotal = $subTotal + $shipping;

            $po = PurchaseOrder::create([
                'po_number' => $this->nextPoNumber(),
                'supplier_id' => $supplier->id,
                'po_date' => $date->toDateString(),
                'sub_total' => $subTotal,
                'discount' => 0,
                'tax' => 0,
                'total_tax' => 0,
                'shipping_cost' => $shipping,
                'other_cost' => 0,
                'grand_total' => $grandTotal,
                'status' => 'approved',
                'description' => self::MARKER . ' Pengadaan rutin.',
                'estimated_arrival' => $date->copy()->addDays(mt_rand(2, 6))->toDateString(),
                'status_payment' => 'unpaid',
                'status_receive_order' => 'pending',
                'created_by' => $this->userId,
            ]);

            foreach ($lines as $line) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'medicine_id' => $line['medicine_id'],
                    'description' => null,
                    'qty' => $line['qty'],
                    'price' => $line['price'],
                    'discount' => 0,
                    'total' => $line['total'],
                ]);
            }

            // Jadwalkan penerimaan (RO) beberapa hari kemudian.
            $receiveDate = $date->copy()->addDays(mt_rand(1, 5));
            if ($receiveDate->gt(Carbon::today())) {
                // Melewati hari ini → biarkan jadi PO outstanding (tidak diterima).
                continue;
            }

            // 12% penerimaan sebagian.
            $partial = mt_rand(1, 100) <= 12;
            $this->pendingReceipts[$receiveDate->format('Y-m-d')][] = [
                'po' => $po,
                'lines' => $lines,
                'partial' => $partial,
            ];
        }
    }

    protected function createReceiveOrder(array $spec, Carbon $date): void
    {
        /** @var PurchaseOrder $po */
        $po = $spec['po'];
        $partial = $spec['partial'];

        $ro = ReceiveOrder::create([
            'receive_order_number' => $this->nextRoNumber(),
            'purchase_order_id' => $po->id,
            'supplier_id' => $po->supplier_id,
            'receive_date' => $date->toDateString(),
            'description' => self::MARKER . ' Penerimaan dari ' . $po->po_number,
            'status' => 'completed',
            'late_arrival' => $po->estimated_arrival && $date->gt($po->estimated_arrival),
            'received_by' => $this->userId,
        ]);

        $fullyReceived = true;

        foreach ($spec['lines'] as $line) {
            $qty = $line['qty'];
            if ($partial) {
                $qty = max(1, (int) floor($qty * mt_rand(60, 90) / 100));
                if ($qty < $line['qty']) {
                    $fullyReceived = false;
                }
            }

            $m = $this->medById[$line['medicine_id']];

            ReceiveOrderItem::create([
                'receive_order_id' => $ro->id,
                'medicine_id' => $line['medicine_id'],
                'medicine_name' => $m->name,
                'qty' => $qty,
                'price' => $line['price'],
                'batch_number' => 'BN' . $date->format('ym') . '-' . strtoupper(Str::random(5)),
                'manufacture_date' => $date->copy()->subDays(mt_rand(30, 300))->toDateString(),
                'expired_date' => $this->randomExpiryDate($date)->toDateString(),
            ]);

            MedicineStock::create([
                'medicine_id' => $line['medicine_id'],
                'qty' => $qty,
                'type_account' => 'D',
                'date' => $date->toDateString(),
                'hpp' => $line['price'],
                'receive_order_id' => $ro->id,
                'description' => self::MARKER . ' Penerimaan dari ' . $po->po_number,
                'created_by' => $this->userId,
            ]);
            $this->stockEntryTotal++;

            $this->stock[$line['medicine_id']] += $qty;
        }

        // Update status PO sesuai penerimaan.
        $paymentRoll = mt_rand(1, 100);
        $po->update([
            'status' => $fullyReceived ? 'completed' : 'approved',
            'status_receive_order' => $fullyReceived ? 'received' : 'partial',
            'status_payment' => $paymentRoll <= 78 ? 'paid' : ($paymentRoll <= 92 ? 'unpaid' : 'partial'),
        ]);
    }

    protected function createDailySales(Carbon $date): void
    {
        $ordersToday = mt_rand(1, 5);

        for ($o = 0; $o < $ordersToday; $o++) {
            $lineCount = mt_rand(1, 4);
            $picks = $this->pickMeds($lineCount);
            if (empty($picks)) {
                continue;
            }

            $items = [];
            $grandTotal = 0;

            foreach ($picks as $id) {
                $maxQty = max(1, (int) round($this->pop[$id] / 15));
                $qty = min($this->stock[$id], mt_rand(1, $maxQty));
                if ($qty <= 0) {
                    continue;
                }

                $m = $this->medById[$id];
                $price = $this->salePrice[$id];
                $total = $qty * $price;
                $grandTotal += $total;

                $items[] = [
                    'medicine_id' => $id,
                    'medicine_name' => $m->name,
                    'qty' => $qty,
                    'price' => $price,
                    'total' => $total,
                ];
            }

            if (empty($items)) {
                continue;
            }

            $roll = mt_rand(1, 100);
            $status = $roll <= 85 ? 'paid' : ($roll <= 97 ? 'pending' : 'cancelled');

            [$orderCode, $payNumber] = $this->nextOrderNumbers($date);

            $order = Order::create([
                'order_code' => $orderCode,
                'no_payment' => $payNumber,
                'order_date' => $date->toDateString(),
                'grand_total' => $grandTotal,
                'status' => $status,
                'note' => self::MARKER . ' Penjualan demo.',
                'created_by' => $this->userId,
            ]);
            $this->orderTotal++;

            foreach ($items as $item) {
                OrderItem::create([
                    'order_id' => $order->id,
                    'medicine_id' => $item['medicine_id'],
                    'medicine_name' => $item['medicine_name'],
                    'qty' => $item['qty'],
                    'price' => $item['price'],
                    'total' => $item['total'],
                ]);

                // Order batal = barang tidak keluar (stok tidak berkurang, tidak dihitung permintaan).
                if ($status === 'cancelled') {
                    continue;
                }

                MedicineStock::create([
                    'medicine_id' => $item['medicine_id'],
                    'qty' => $item['qty'],
                    'type_account' => 'C',
                    'date' => $date->toDateString(),
                    'hpp' => $item['price'],
                    'order_id' => $order->id,
                    'description' => self::MARKER . ' Penjualan ' . $orderCode,
                    'created_by' => $this->userId,
                ]);
                $this->stockEntryTotal++;

                $this->stock[$item['medicine_id']] -= $item['qty'];
            }
        }
    }

    protected function createOpname(Carbon $date): void
    {
        $candidates = array_values(array_filter($this->medIds, fn ($id) => $this->stock[$id] > 0));
        if (empty($candidates)) {
            return;
        }
        shuffle($candidates);
        $picks = array_slice($candidates, 0, mt_rand(12, 25));

        $opname = MedicineStockOpname::create([
            'opname_number' => $this->nextOpmNumber(),
            'opname_date' => $date->toDateString(),
            'status' => 'in_stock',
            'description' => self::MARKER . ' Stok opname berkala.',
            'created_by' => $this->userId,
        ]);

        foreach ($picks as $id) {
            $m = $this->medById[$id];

            if (mt_rand(1, 100) <= 70) {
                // Selisih kurang (susut) → Credit.
                $type = 'C';
                $qty = min($this->stock[$id], mt_rand(1, 3));
                if ($qty <= 0) {
                    continue;
                }
                $this->stock[$id] -= $qty;
            } else {
                // Selisih lebih (temuan) → Debit.
                $type = 'D';
                $qty = mt_rand(1, 3);
                $this->stock[$id] += $qty;
            }

            $hpp = $this->purchasePrice[$id] * $qty;

            MedicineStockOpnameItem::create([
                'medicine_stock_opname_id' => $opname->id,
                'medicine_id' => $id,
                'qty' => $qty,
                'type_account' => $type,
                'hpp' => $hpp,
            ]);

            MedicineStock::create([
                'medicine_id' => $id,
                'qty' => $qty,
                'type_account' => $type,
                'date' => $date->toDateString(),
                'hpp' => $hpp,
                'medicine_stock_opname_id' => $opname->id,
                'description' => self::MARKER . ' Opname ' . $opname->opname_number,
                'created_by' => $this->userId,
            ]);
            $this->stockEntryTotal++;
        }
    }

    /**
     * PO yang belum diterima (status approved, receive pending) — untuk widget Pending PO.
     *
     * @param  array<int,Supplier>  $suppliers
     */
    protected function seedOutstandingPurchaseOrders(array $suppliers): void
    {
        for ($i = 0; $i < mt_rand(2, 3); $i++) {
            $date = Carbon::today()->subDays(mt_rand(1, 8));
            $ids = $this->medIds;
            shuffle($ids);
            $chunk = array_slice($ids, 0, mt_rand(4, 10));

            $supplier = $suppliers[array_rand($suppliers)];
            $lines = [];
            $subTotal = 0;
            foreach ($chunk as $id) {
                $m = $this->medById[$id];
                $qty = mt_rand(20, 80);
                $price = $this->purchasePrice[$id];
                $total = $qty * $price;
                $subTotal += $total;
                $lines[] = compact('id', 'qty', 'price', 'total');
            }

            $po = PurchaseOrder::create([
                'po_number' => $this->nextPoNumber(),
                'supplier_id' => $supplier->id,
                'po_date' => $date->toDateString(),
                'sub_total' => $subTotal,
                'discount' => 0,
                'tax' => 0,
                'total_tax' => 0,
                'shipping_cost' => 0,
                'other_cost' => 0,
                'grand_total' => $subTotal,
                'status' => 'approved',
                'description' => self::MARKER . ' PO menunggu penerimaan.',
                'estimated_arrival' => Carbon::today()->addDays(mt_rand(1, 5))->toDateString(),
                'status_payment' => 'unpaid',
                'status_receive_order' => 'pending',
                'created_by' => $this->userId,
            ]);

            foreach ($lines as $line) {
                PurchaseOrderItem::create([
                    'purchase_order_id' => $po->id,
                    'medicine_id' => $line['id'],
                    'description' => null,
                    'qty' => $line['qty'],
                    'price' => $line['price'],
                    'discount' => 0,
                    'total' => $line['total'],
                ]);
            }
        }
    }

    /**
     * Pilih obat secara acak berbobot popularitas (tanpa pengulangan), hanya yang stoknya > 0.
     *
     * @return array<int,int>
     */
    protected function pickMeds(int $count): array
    {
        $candidates = [];
        foreach ($this->medIds as $id) {
            if ($this->stock[$id] > 0 && $this->pop[$id] > 0) {
                $candidates[$id] = $this->pop[$id];
            }
        }

        $picked = [];
        for ($i = 0; $i < $count && ! empty($candidates); $i++) {
            $sum = array_sum($candidates);
            $r = mt_rand(1, $sum);
            $acc = 0;
            foreach ($candidates as $id => $w) {
                $acc += $w;
                if ($r <= $acc) {
                    $picked[] = $id;
                    unset($candidates[$id]);
                    break;
                }
            }
        }

        return $picked;
    }

    protected function nextPoNumber(): string
    {
        return 'PO' . str_pad((string) $this->poSeq++, 4, '0', STR_PAD_LEFT);
    }

    protected function nextRoNumber(): string
    {
        return 'RO' . str_pad((string) $this->roSeq++, 4, '0', STR_PAD_LEFT);
    }

    protected function nextOpmNumber(): string
    {
        return 'OPM' . str_pad((string) $this->opmSeq++, 4, '0', STR_PAD_LEFT);
    }

    /**
     * @return array{0:string,1:string} [order_code, no_payment]
     */
    protected function nextOrderNumbers(Carbon $date): array
    {
        $ymd = $date->format('Ymd');
        if (! isset($this->orderSeqByDate[$ymd])) {
            $this->orderSeqByDate[$ymd] = Order::withTrashed()->whereDate('order_date', $date->toDateString())->count();
        }
        $seq = ++$this->orderSeqByDate[$ymd];
        $suffix = str_pad((string) $seq, 4, '0', STR_PAD_LEFT);

        return ['ORD-' . $ymd . $suffix, 'PAY-' . $ymd . $suffix];
    }

    /**
     * @param  array<int,string>  $numbers
     */
    protected function maxNumber(array $numbers): int
    {
        $max = 0;
        foreach ($numbers as $num) {
            $n = (int) preg_replace('/\D/', '', (string) $num);
            $max = max($max, $n);
        }

        return $max;
    }

    /** Berkas penanda obat demo — pengganti penanda di kolom description (M12). */
    protected function seededIdsPath(): string
    {
        return storage_path('app/demo-apotek.json');
    }

    /** @return array<int,int> */
    protected function readSeededMedicineIds(): array
    {
        $path = $this->seededIdsPath();
        if (! is_file($path)) {
            return [];
        }
        $ids = json_decode((string) file_get_contents($path), true);

        return is_array($ids) ? array_map('intval', $ids) : [];
    }

    protected function writeSeededMedicineIds(): void
    {
        file_put_contents($this->seededIdsPath(), json_encode(array_values($this->medIds)));
    }

    /**
     * Kemasan pembelian bawaan per satuan jual: Strip → Box isi 10, Pcs → Box isi 100,
     * lainnya dibeli dalam satuan jualnya sendiri (isi 1).
     *
     * @return array{0:int,1:int} [pack_unit_id, pack_size]
     */
    protected function packFor(Unit $unit, Collection $units): array
    {
        $box = $units->first(fn (Unit $u) => strtolower($u->name) === 'box');
        $name = strtolower($unit->name);

        if ($box && $name === 'strip') {
            return [$box->id, 10];
        }
        if ($box && $name === 'pcs') {
            return [$box->id, 100];
        }

        return [$unit->id, 1];
    }

    protected function randomExpiryDate(Carbon $from): Carbon
    {
        // 15% batch ber-ED pendek (bisa jadi hampir/sudah kedaluwarsa hari ini).
        $daysAhead = mt_rand(1, 100) <= 15 ? mt_rand(120, 300) : mt_rand(420, 1095);

        return $from->copy()->addDays($daysAhead);
    }

    protected function randomPurchasePrice(): int
    {
        $bucket = mt_rand(1, 100);

        return match (true) {
            $bucket <= 30 => mt_rand(2000, 9500),
            $bucket <= 58 => mt_rand(10000, 24000),
            $bucket <= 78 => mt_rand(25000, 49000),
            $bucket <= 91 => mt_rand(50000, 98000),
            default => mt_rand(100000, 240000),
        };
    }

    protected function randomDosage(): string
    {
        $options = ['250 mg', '500 mg', '650 mg', '1000 mg', '5 mg', '10 mg', '20 mg', '40 mg', '50 mg', '100 mg', '125 mg/5ml', '60 ml', '100 ml', '30 g', '15 g', '5 mg/ml'];

        return $options[array_rand($options)];
    }

    /**
     * @return array<int,string>
     */
    protected function medicineNamePool(): array
    {
        return [
            'Paracetamol', 'Sanmol', 'Panadol', 'Tempra', 'Bodrex',
            'Amoxicillin', 'Amoxsan', 'Cefadroxil', 'Cefixime', 'Ciprofloxacin',
            'Erythromycin', 'Azithromycin', 'Clindamycin', 'Tetracycline', 'Levofloxacin',
            'Ibuprofen', 'Proris', 'Naproxen', 'Mefenamic Acid', 'Asam Mefenamat',
            'Diclofenac', 'Voltaren', 'Cataflam', 'Counterpain', 'Hot In Cream',
            'Aspirin', 'Aspilet', 'Cardio Aspirin', 'Bayer Aspirin', 'Miniaspi',
            'Cetirizine', 'Loratadine', 'Claritin', 'Incidal', 'Telfast',
            'Chlorpheniramine', 'CTM', 'Allerin', 'Tremenza', 'Lapifed',
            'Pseudoephedrine', 'Dextromethorphan', 'Bisolvon', 'Mucos', 'Mucopect',
            'Ambroxol', 'Bromhexine', 'Guaifenesin', 'OBH Combi', 'Vicks Formula',
            'Konidin', 'Komix', 'Procold', 'Decolgen', 'Mixagrip',
            'Sanaflu', 'Ultraflu', 'Neozep', 'Inza', 'Anakonidin',
            'Metformin', 'Glucophage', 'Glibenclamide', 'Glimepiride', 'Acarbose',
            'Pioglitazone', 'Sitagliptin', 'Gliclazide', 'Novorapid', 'Lantus',
            'Simvastatin', 'Atorvastatin', 'Lipitor', 'Rosuvastatin', 'Crestor',
            'Lisinopril', 'Captopril', 'Amlodipine', 'Norvasc', 'Tensivask',
            'Bisoprolol', 'Concor', 'Propranolol', 'Atenolol', 'Furosemide',
            'HCT', 'Hydrochlorothiazide', 'Spironolactone', 'Aldactone', 'Valsartan',
            'Losartan', 'Olmesartan', 'Telmisartan', 'Candesartan', 'Irbesartan',
            'Omeprazole', 'Lansoprazole', 'Pantoprazole', 'Esomeprazole', 'Nexium',
            'Ranitidine', 'Cimetidine', 'Famotidine', 'Antasida', 'Mylanta',
            'Promag', 'Polysilane', 'Sucralfate', 'Inpepsa', 'Magasida',
            'Domperidone', 'Vometa', 'Metoclopramide', 'Primperan', 'Ondansetron',
            'Loperamide', 'Diapet', 'Entrostop', 'New Diatab', 'Norit',
        ];
    }
}
