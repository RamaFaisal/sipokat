<?php

namespace Database\Seeders;

use App\Models\Medicine;
use App\Models\MedicineCategories;
use App\Models\MedicineStock;
use App\Models\Order;
use App\Models\OrderItem;
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

/**
 * Seeder data SPK SAW.
 *
 * Idempotent: data SPK lama dihapus dulu, generate ulang. Obat demo dikenali lewat
 * daftar id di storage/app/spk-test-data.json (kolom description sudah dihapus, M12)
 * (mis. SIP/PARA500/ANA/TAB/001), bukan prefix custom.
 *
 * Format yang DI-MIRRORKAN dari MedicineForm::generateCode():
 *   1. Nama obat di-uppercase
 *   2. Code = {APP3}/{NAME4|5}{DOSAGE_NUM|GEN}/{CATEGORY3}/{UNIT_ALIAS}/{SEQ3}
 *   3. Konflik 4-char prefix → fallback ke 5-char
 *   4. Sequence per baseCode increment
 *
 * Algoritma data SPK: "plan-then-execute" supaya stok akhir tidak pernah negatif.
 *   1. Untuk tiap medicine, tentukan target_stock_akhir + target_demand (30 hari).
 *   2. RO qty = target_stock_akhir + target_demand → stok akhir = target_stock.
 *   3. Orders di-generate dengan total qty per medicine ≤ target_demand.
 */
class SpkTestDataSeeder extends Seeder
{
    protected const SUPPLIER_CODE = 'SPK-DIST-TEST';

    protected const TOTAL_MEDICINES = 150;

    protected const RO_BATCHES = 5;

    protected const MAX_ORDERS = 250;

    protected const TEST_MARKER = '[SPK_TEST_DATA]';

    /** @var array<int, array{medicine: Medicine, target_stock: int, target_demand: int, ro_qty: int, expired_date: Carbon, consumed: int}> */
    protected array $plans = [];

    /** @var array<int,int> harga beli per obat (dipakai RO & hpp) */
    protected array $purchasePrice = [];

    /** @var array<int,int> harga jual per obat (dipakai order) */
    protected array $salePrice = [];

    public function run(): void
    {
        $this->command->info('Cleaning up previous SPK test data...');
        $this->cleanup();

        $this->command->info('Seeding master supplier...');
        $supplier = $this->ensureSupplier();

        $this->command->info('Seeding ' . self::TOTAL_MEDICINES . ' medicines (sesuai format Filament form)...');
        $medicines = $this->seedMedicines();
        $this->writeSeededMedicineIds($medicines);

        $this->command->info('Planning distribution (target stok + demand)...');
        $this->planDistribution($medicines);

        $this->command->info('Seeding receive orders + batch with expired_date...');
        $this->seedReceiveOrders($supplier, $medicines);

        $this->command->info('Seeding sales orders (last 30 days) for C2 demand...');
        $this->seedOrders();

        $this->command->info('Updating stock_status for all SPK medicines...');
        $stockService = app(StockCardService::class);
        foreach ($medicines as $m) {
            $stockService->updateMedicineStockStatus($m->id);
        }

        $this->command->info('Done. Jalankan: php artisan sipokat:recalculate-saw');
    }

    protected function cleanup(): void
    {
        // 1. Orders dengan prefix ORD-SPK- (termasuk yang soft-deleted)
        $orderIds = Order::withTrashed()->where('order_code', 'like', 'ORD-SPK-%')->pluck('id')->all();
        if (! empty($orderIds)) {
            MedicineStock::whereIn('order_id', $orderIds)->delete();
            OrderItem::whereIn('order_id', $orderIds)->delete();
            Order::withTrashed()->whereIn('id', $orderIds)->forceDelete();
        }

        // 2. Receive Orders dengan prefix RO-SPK- (termasuk yang soft-deleted)
        $roIds = ReceiveOrder::withTrashed()->where('receive_order_number', 'like', 'RO-SPK-%')->pluck('id')->all();
        if (! empty($roIds)) {
            MedicineStock::whereIn('receive_order_id', $roIds)->delete();
            ReceiveOrderItem::whereIn('receive_order_id', $roIds)->delete();
            ReceiveOrder::withTrashed()->whereIn('id', $roIds)->forceDelete();
        }

        // 3. Medicines — dikenali lewat daftar id yang disimpan seeder (rencana §1.4, M12)
        $oldMedicineIds = $this->readSeededMedicineIds();
        if (! empty($oldMedicineIds)) {
            // sweep sisa MedicineStock yang nyangkut (mis. dari opname manual)
            MedicineStock::whereIn('medicine_id', $oldMedicineIds)->delete();
            Medicine::whereIn('id', $oldMedicineIds)->delete();
        }

        // 4. Supplier test
        Supplier::where('code', self::SUPPLIER_CODE)->delete();
    }

    protected function ensureSupplier(): Supplier
    {
        return Supplier::updateOrCreate(
            ['code' => self::SUPPLIER_CODE],
            [
                'name' => 'PT. Distributor SPK Test',
                'pic' => 'Tester SPK',
                'phone' => '081200000000',
                'address' => 'Jl. Testing No. 1, Semarang',
                'status' => 'active',
            ],
        );
    }

    /**
     * @return array<int, Medicine>
     */
    protected function seedMedicines(): array
    {
        $categories = MedicineCategories::all()->keyBy('id');
        $units = Unit::all()->keyBy('id');

        $names = $this->medicineNamePool();
        $medicines = [];
        $seen = []; // nama harus unik (M6): dosis ikut di nama seperti di faktur

        for ($i = 1; $i <= self::TOTAL_MEDICINES; $i++) {
            $baseName = strtoupper($names[$i - 1] ?? ('Generic Obat ' . $i));
            $name = $baseName . ' ' . strtoupper($this->randomDosage());
            while (isset($seen[$name])) {
                $name = $baseName . ' ' . strtoupper($this->randomDosage());
            }
            $seen[$name] = true;

            $categoryId = array_rand($categories->toArray());
            $unitId = array_rand($units->toArray());

            $purchase = $this->randomPurchasePrice();
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
            $this->salePrice[$medicine->id] = $purchase + mt_rand(1000, 10000);
            $medicines[] = $medicine;
        }

        return $medicines;
    }

    /** Berkas penanda obat demo — pengganti penanda di kolom description (M12). */
    protected function seededIdsPath(): string
    {
        return storage_path('app/spk-test-data.json');
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

    /** @param array<int, Medicine> $medicines */
    protected function writeSeededMedicineIds(array $medicines): void
    {
        file_put_contents($this->seededIdsPath(), json_encode(array_map(fn (Medicine $m) => $m->id, $medicines)));
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

    /**
     * @param  array<int, Medicine>  $medicines
     */
    protected function planDistribution(array $medicines): void
    {
        $this->plans = [];
        foreach ($medicines as $m) {
            $targetStock = $this->randomTargetStock();
            $targetDemand = $this->randomTargetDemand();
            $this->plans[$m->id] = [
                'medicine' => $m,
                'target_stock' => $targetStock,
                'target_demand' => $targetDemand,
                'ro_qty' => $targetStock + $targetDemand,
                'expired_date' => $this->randomExpiryDate(),
                'consumed' => 0,
            ];
        }
    }

    /**
     * @param  array<int, Medicine>  $medicines
     */
    protected function seedReceiveOrders(Supplier $supplier, array $medicines): void
    {
        $chunks = array_chunk($medicines, (int) ceil(count($medicines) / self::RO_BATCHES));
        $userId = User::value('id');

        foreach ($chunks as $idx => $chunkItems) {
            $roNumber = sprintf('RO-SPK-%03d', $idx + 1);

            $ro = ReceiveOrder::create([
                'receive_order_number' => $roNumber,
                'purchase_order_id' => null,
                'supplier_id' => $supplier->id,
                'invoice_number' => 'SPK-' . ($idx + 1),
                'receive_date' => Carbon::now()->subDays(mt_rand(35, 60)),
                'received_by' => $userId,
            ]);

            foreach ($chunkItems as $medicine) {
                $plan = $this->plans[$medicine->id];
                $qty = $plan['ro_qty'];
                if ($qty === 0) {
                    continue;
                }

                ReceiveOrderItem::create([
                    'receive_order_id' => $ro->id,
                    'medicine_id' => $medicine->id,
                    'medicine_name' => $medicine->name,
                    'pack_unit_id' => $medicine->unit_id,
                    'pack_size' => 1,
                    'pack_qty' => $qty,
                    'qty' => $qty,
                    'price' => $this->purchasePrice[$medicine->id],
                    'batch_number' => 'BATCH-' . strtoupper(Str::random(6)),
                    'expired_date' => $plan['expired_date'],
                ]);

                MedicineStock::create([
                    'medicine_id' => $medicine->id,
                    'qty' => $qty,
                    'type_account' => 'D',
                    'date' => $ro->receive_date,
                    'hpp' => $this->purchasePrice[$medicine->id],
                    'receive_order_id' => $ro->id,
                    'description' => 'Penerimaan ' . $roNumber,
                    'created_by' => $userId,
                ]);
            }
        }
    }

    protected function seedOrders(): void
    {
        $userId = User::value('id');
        $orderCount = 0;
        $itemCount = 0;

        while ($orderCount < self::MAX_ORDERS) {
            $candidates = collect($this->plans)
                ->filter(fn ($p) => $p['target_demand'] > $p['consumed'])
                ->keys()
                ->all();

            if (empty($candidates)) {
                break;
            }

            shuffle($candidates);
            $picks = array_slice($candidates, 0, min(mt_rand(1, 4), count($candidates)));

            $orderDate = Carbon::now()->subDays(mt_rand(0, 29));
            $code = sprintf('ORD-SPK-%04d', $orderCount + 1);

            $items = [];
            $grandTotal = 0;

            foreach ($picks as $medicineId) {
                $plan = &$this->plans[$medicineId];
                $remaining = $plan['target_demand'] - $plan['consumed'];
                if ($remaining <= 0) {
                    continue;
                }
                $qty = min($remaining, mt_rand(1, max(1, (int) ceil($remaining / 3))));
                $plan['consumed'] += $qty;

                $m = $plan['medicine'];
                $items[] = [
                    'medicine_id' => $m->id,
                    'medicine_name' => $m->name,
                    'qty' => $qty,
                    'price' => $this->salePrice[$m->id],
                    'total' => $qty * $this->salePrice[$m->id],
                ];
                $grandTotal += $qty * $this->salePrice[$m->id];
            }
            unset($plan);

            if (empty($items)) {
                continue;
            }

            $order = Order::create([
                'order_code' => $code,
                'no_payment' => 'PAY-SPK-' . sprintf('%04d', $orderCount + 1),
                'order_date' => $orderDate,
                'grand_total' => $grandTotal,
                'status' => 'paid',
                'note' => self::TEST_MARKER . ' Test data SPK seeder',
                'created_by' => $userId,
            ]);

            foreach ($items as $itemData) {
                OrderItem::create(array_merge($itemData, ['order_id' => $order->id]));

                MedicineStock::create([
                    'medicine_id' => $itemData['medicine_id'],
                    'qty' => $itemData['qty'],
                    'type_account' => 'C',
                    'date' => $orderDate,
                    'hpp' => $this->purchasePrice[$itemData['medicine_id']],
                    'order_id' => $order->id,
                    'description' => 'Penjualan ' . $code,
                    'created_by' => $userId,
                ]);
                $itemCount++;
            }
            $orderCount++;
        }

        $this->command->info("  → {$orderCount} orders dibuat dengan total {$itemCount} item.");
    }

    /** Stok AKHIR (setelah penjualan). Cover semua bracket Tabel 3.5. */
    protected function randomTargetStock(): int
    {
        $bucket = mt_rand(1, 100);
        return match (true) {
            $bucket <= 18 => mt_rand(0, 10),
            $bucket <= 38 => mt_rand(11, 30),
            $bucket <= 60 => mt_rand(31, 60),
            $bucket <= 82 => mt_rand(61, 100),
            default => mt_rand(101, 200),
        };
    }

    /** Total demand (qty terjual) per 30 hari. */
    protected function randomTargetDemand(): int
    {
        $bucket = mt_rand(1, 100);
        return match (true) {
            $bucket <= 22 => mt_rand(0, 19),
            $bucket <= 44 => mt_rand(20, 40),
            $bucket <= 64 => mt_rand(41, 60),
            $bucket <= 82 => mt_rand(61, 80),
            default => mt_rand(81, 130),
        };
    }

    protected function randomExpiryDate(): Carbon
    {
        $bucket = mt_rand(1, 100);
        $daysAhead = match (true) {
            $bucket <= 20 => mt_rand(30, 90),
            $bucket <= 40 => mt_rand(91, 180),
            $bucket <= 65 => mt_rand(181, 365),
            $bucket <= 85 => mt_rand(366, 730),
            default => mt_rand(731, 1095),
        };
        return Carbon::now()->addDays($daysAhead);
    }

    protected function randomPurchasePrice(): int
    {
        $bucket = mt_rand(1, 100);
        return match (true) {
            $bucket <= 28 => mt_rand(2000, 9000),
            $bucket <= 56 => mt_rand(10000, 24000),
            $bucket <= 76 => mt_rand(25000, 49000),
            $bucket <= 90 => mt_rand(50000, 99000),
            default => mt_rand(100000, 250000),
        };
    }

    protected function randomDosage(): string
    {
        $options = ['250 mg', '500 mg', '650 mg', '1000 mg', '5 mg', '10 mg', '20 mg', '50 mg', '100 mg', '125 mg/5ml', '60 ml', '100 ml', '30 g', '15 g'];
        return $options[array_rand($options)];
    }

    /**
     * Pool 150 nama obat (mix nama generik + brand) untuk apotek umum.
     *
     * @return array<int, string>
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
            'Cetirizine', 'Loratadine', 'Claritin', 'Incidal-OD', 'Telfast',
            'Chlorpheniramine', 'CTM', 'Allerin', 'Tremenza', 'Lapifed',
            'Pseudoephedrine', 'Dextromethorphan', 'Bisolvon', 'Mucos', 'Mucopect',
            'Ambroxol', 'Bromhexine', 'Glyceryl Guaiacolate', 'OBH Combi', 'Vicks Formula 44',
            'Konidin', 'Komix', 'Procold', 'Decolgen', 'Mixagrip',
            'Sanaflu', 'Ultraflu', 'Neozep', 'Inza', 'Anakonidin',
            'Metformin', 'Glucophage', 'Glibenclamide', 'Glimepiride', 'Acarbose',
            'Pioglitazone', 'Sitagliptin', 'Insulin Pen', 'Insulin Novorapid', 'Lantus',
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
            'Bisacodyl', 'Dulcolax', 'Microlax', 'Lactulose', 'Duphalac',
            'Salbutamol', 'Ventolin', 'Berotec', 'Theophylline', 'Aminophylline',
            'Diazepam', 'Alprazolam', 'Lorazepam', 'Clobazam', 'Phenobarbital',
            'Carbamazepine', 'Phenytoin', 'Valproic Acid', 'Levothyroxine', 'Thyrax',
            'Allopurinol', 'Zyloric', 'Colchicine', 'Methylprednisolone', 'Dexamethasone',
            'Prednisone', 'Hydrocortisone', 'Bedak Salicyl', 'Betadine', 'Hibitane',
            'Sangobion', 'Hemaviton', 'Imboost', 'Enervon-C', 'Vitamin C IPI',
            'Curcuma Plus', 'Stimuno', 'Biolysin', 'Neurobion', 'Becombion',
        ];
    }
}
