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
use App\Settings\GeneralSettings;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Seeder data SPK SAW.
 *
 * Idempotent: data SPK lama dihapus dulu, generate ulang. Identifikasi via marker
 * di description ("[SPK_TEST_DATA]") karena code-nya mengikuti format Filament form
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

    public function run(): void
    {
        $this->command->info('Cleaning up previous SPK test data...');
        $this->cleanup();

        $this->command->info('Seeding master supplier...');
        $supplier = $this->ensureSupplier();

        $this->command->info('Seeding ' . self::TOTAL_MEDICINES . ' medicines (sesuai format Filament form)...');
        $medicines = $this->seedMedicines();

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
            MedicineStock::whereIn('order_id', $orderIds)->forceDelete();
            OrderItem::whereIn('order_id', $orderIds)->delete();
            Order::withTrashed()->whereIn('id', $orderIds)->forceDelete();
        }

        // 2. Receive Orders dengan prefix RO-SPK- (termasuk yang soft-deleted)
        $roIds = ReceiveOrder::withTrashed()->where('receive_order_number', 'like', 'RO-SPK-%')->pluck('id')->all();
        if (! empty($roIds)) {
            MedicineStock::whereIn('receive_order_id', $roIds)->forceDelete();
            ReceiveOrderItem::whereIn('receive_order_id', $roIds)->delete();
            ReceiveOrder::withTrashed()->whereIn('id', $roIds)->forceDelete();
        }

        // 3. Medicines — match by description marker baru ATAU prefix code lama (backward compat)
        $oldMedicineIds = Medicine::query()
            ->where('description', 'like', self::TEST_MARKER . '%')
            ->orWhere('code', 'like', 'SPK-MED-%')
            ->pluck('id')->all();
        if (! empty($oldMedicineIds)) {
            // sweep sisa MedicineStock yang nyangkut (mis. dari opname manual)
            MedicineStock::whereIn('medicine_id', $oldMedicineIds)->forceDelete();
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
        $seen = []; // track name+dosage uniqueness (mirror form unique rule)

        for ($i = 1; $i <= self::TOTAL_MEDICINES; $i++) {
            $name = strtoupper($names[$i - 1] ?? ('Generic Obat ' . $i));
            $dosage = $this->randomDosage();

            // Skip duplicate name+dosage (mirror form's unique rule)
            $dupKey = $name . '|' . $dosage;
            while (isset($seen[$dupKey])) {
                $dosage = $this->randomDosage();
                $dupKey = $name . '|' . $dosage;
            }
            $seen[$dupKey] = true;

            $categoryId = array_rand($categories->toArray());
            $unitId = array_rand($units->toArray());

            $code = $this->generateMedicineCode($name, $dosage, $categories[$categoryId], $units[$unitId]);
            $purchase = $this->randomPurchasePrice();

            $medicines[] = Medicine::create([
                'code' => $code,
                'name' => $name,
                'dosage' => $dosage,
                'category_id' => $categoryId,
                'unit_id' => $unitId,
                'purchase_price' => $purchase,
                'sale_price' => $purchase + mt_rand(1000, 10000),
                'min_stock' => mt_rand(5, 20),
                'stock_status' => 'empty',
                'status' => 'active',
                'description' => self::TEST_MARKER . ' Test data SPK auto-generated.',
            ]);
        }

        return $medicines;
    }

    /**
     * Mirror dari MedicineForm::generateCode() — format kode obat yang sama
     * dengan saat user input via Filament form.
     */
    protected function generateMedicineCode(string $name, ?string $dosage, MedicineCategories $category, Unit $unit): string
    {
        $appName = strtoupper(substr(app(GeneralSettings::class)->app_name ?? 'SIP', 0, 3));

        // 4-char prefix; expand ke 5-char kalau ada konflik dengan medicine lain
        $namePrefix = strtoupper(substr($name, 0, 4));
        $conflict = Medicine::whereRaw('UPPER(SUBSTRING(name, 1, 4)) = ?', [$namePrefix])
            ->where('name', '!=', $name)
            ->exists();
        if ($conflict) {
            $namePrefix = strtoupper(substr($name, 0, 5));
        }

        $dosageNumber = $dosage ? preg_replace('/[^0-9]/', '', $dosage) : 'GEN';
        if ($dosageNumber === '') {
            $dosageNumber = 'GEN';
        }

        $categoryCode = strtoupper($category->alias ?? substr($category->name, 0, 3));
        $unitAlias = strtoupper($unit->alias ?? substr($unit->name, 0, 3));

        $baseCode = $appName . '/' . $namePrefix . $dosageNumber . '/' . $categoryCode . '/' . $unitAlias;

        $lastRecord = Medicine::where('code', 'like', $baseCode . '/%')
            ->orderBy('code', 'desc')
            ->first();
        if ($lastRecord) {
            $lastNumber = (int) substr($lastRecord->code, strrpos($lastRecord->code, '/') + 1);
            $newNumber = str_pad((string) ($lastNumber + 1), 3, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '001';
        }

        return $baseCode . '/' . $newNumber;
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
                'receive_date' => Carbon::now()->subDays(mt_rand(35, 60)),
                'description' => self::TEST_MARKER . ' Test data SPK seeder',
                'status' => 'completed',
                'late_arrival' => false,
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
                    'qty' => $qty,
                    'price' => $medicine->purchase_price,
                    'batch_number' => 'BATCH-' . strtoupper(Str::random(6)),
                    'manufacture_date' => Carbon::now()->subDays(mt_rand(30, 365)),
                    'expired_date' => $plan['expired_date'],
                ]);

                MedicineStock::create([
                    'medicine_id' => $medicine->id,
                    'qty' => $qty,
                    'type_account' => 'D',
                    'date' => $ro->receive_date,
                    'hpp' => $medicine->purchase_price,
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
                    'price' => $m->sale_price,
                    'total' => $qty * $m->sale_price,
                ];
                $grandTotal += $qty * $m->sale_price;
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
                    'hpp' => $this->plans[$itemData['medicine_id']]['medicine']->purchase_price,
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
