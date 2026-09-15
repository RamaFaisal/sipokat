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
use App\Services\StockMovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Data demo SPK SAW — versi rencana-revisi-2026-09 (tahap E6, T2).
 *
 * Dipakai untuk Bab IV bila data apotek terlambat. Dibangun lewat jalur yang sama dengan
 * UI (StockMovementService): lapisan batch, HPP rata-rata bergerak, alokasi FEFO.
 *
 * Rancangan distribusi (rencana §7.2): tiap obat diberi target di kelima bracket skala
 * wawancara untuk C1 (rasio stok ÷ min_stock), C2 (permintaan/bulan), C3 (ED batch terjauh),
 * C4 (HPP). Selain itu:
 *   - ~35% obat punya dua batch (ED dekat + ED jauh) → C3 membaca batch terjauh (K3);
 *   - ~8% obat punya batch kedaluwarsa yang masih bersisa → stok fisik > stok tersedia (F2);
 *   - ~10% obat stok tersedia 0 → C3 = 0 hari, HPP tetap (T2, K4).
 *
 * Faktur meniru pola PBF: satu RO = satu faktur ≤ 14 baris (R10), harga per kemasan, ED bulan-tahun.
 * Idempotent: obat demo dikenali lewat storage/app/spk-test-data.json; RO/order lewat prefiks.
 *
 * Jalankan: php artisan db:seed --class=SpkTestDataSeeder && php artisan sipokat:recalculate-saw
 */
class SpkTestDataSeeder extends Seeder
{
    protected const SUPPLIER_NAME = 'PT. Distributor SPK Test'; // kode otomatis: DST

    protected const TOTAL_MEDICINES = 150;

    protected const INVOICE_LINES = 14;

    protected const DEMAND_DAYS = 30;

    protected ?int $userId = null;

    protected StockMovementService $movement;

    /** @var array<int, array{medicine: Medicine, min: int, stock: int, demand: int, price: int, ed_far: int, ed_near: ?int, expired_leftover: int}> */
    protected array $plans = [];

    protected int $invoiceSeq = 0;

    protected int $roSeq = 0;

    protected int $orderSeq = 0;

    public function run(): void
    {
        $this->userId = User::value('id');
        $this->movement = app(StockMovementService::class);

        $this->command?->info('Membersihkan data demo SPK lama...');
        $this->cleanup();

        $supplier = $this->ensureSupplier();
        $units = $this->ensureUnits();

        $this->command?->info('Membuat '.self::TOTAL_MEDICINES.' obat...');
        $medicines = $this->seedMedicines($units);
        $this->writeSeededMedicineIds($medicines);

        $this->command?->info('Menyusun rencana distribusi per bracket skala...');
        $this->planDistribution($medicines);

        $this->command?->info('Menulis penerimaan (faktur ≤ 14 baris, lapisan batch)...');
        $this->seedReceipts($supplier);

        $this->command?->info('Menulis penjualan 30 hari (alokasi FEFO)...');
        $this->seedSales();

        $this->command?->info('Menyegarkan status stok...');
        $this->movement->refreshStockStatus(array_keys($this->plans));

        $this->command?->info(sprintf(
            'Selesai: %d obat, %d faktur, %d penjualan. Jalankan: php artisan sipokat:recalculate-saw',
            count($this->plans), $this->roSeq, $this->orderSeq,
        ));
    }

    // ---------------------------------------------------------------------
    // Cleanup & master
    // ---------------------------------------------------------------------

    protected function cleanup(): void
    {
        $orderIds = Order::withTrashed()->where('order_code', 'like', 'ORD-SPK-%')->pluck('id')->all();
        if ($orderIds) {
            MedicineStock::whereIn('order_id', $orderIds)->delete();
            OrderItem::withTrashed()->whereIn('order_id', $orderIds)->forceDelete();
            Order::withTrashed()->whereIn('id', $orderIds)->forceDelete();
        }

        $roIds = ReceiveOrder::withTrashed()->where('receive_order_number', 'like', 'RO-SPK-%')->pluck('id')->all();
        if ($roIds) {
            MedicineStock::whereIn('receive_order_id', $roIds)->delete();
            ReceiveOrderItem::withTrashed()->whereIn('receive_order_id', $roIds)->forceDelete();
            ReceiveOrder::withTrashed()->whereIn('id', $roIds)->forceDelete();
        }

        $medIds = $this->readSeededMedicineIds();
        if ($medIds) {
            MedicineStock::whereIn('medicine_id', $medIds)->delete();
            Medicine::withTrashed()->whereIn('id', $medIds)->forceDelete();
        }
    }

    protected function ensureSupplier(): Supplier
    {
        return Supplier::updateOrCreate(
            ['name' => self::SUPPLIER_NAME],
            [
                'phone' => '081200000000',
                'address' => 'Jl. Testing No. 1, Demak',
                'status' => 'active',
            ],
        );
    }

    /** @return Collection<string, Unit> keyed by lower name */
    protected function ensureUnits(): Collection
    {
        foreach ([['Pcs', 'PCS'], ['Strip', 'STR'], ['Botol', 'FLS'], ['Sachet', 'SCH'], ['Box', 'BOX'], ['Tube', 'TUB'], ['Ampul', 'AMP'], ['Kaleng', 'KLG']] as [$n, $a]) {
            Unit::updateOrCreate(['alias' => $a], ['name' => $n, 'alias' => $a]);
        }
        foreach ([['Obat Bebas', 'OBB'], ['Obat Keras', 'OBK']] as [$n, $a]) {
            MedicineCategories::updateOrCreate(['name' => $n], ['name' => $n, 'alias' => $a]);
        }

        return Unit::all()->keyBy(fn (Unit $u) => strtolower($u->name));
    }

    /**
     * Profil satuan jual: [satuan, kemasan, isi, pilihan min_stock, bobot pemilihan].
     * min_stock bervariasi supaya rasio C1 tidak terkunci pada satu patokan (K1).
     */
    protected function unitProfiles(): array
    {
        return [
            ['strip', 'box', 10, [20, 20, 20, 30, 10], 50],
            ['botol', 'botol', 1, [6, 6, 10, 4], 18],
            ['tube', 'box', 20, [5, 5, 8], 12],
            ['sachet', 'box', 30, [30, 60], 6],
            ['pcs', 'box', 100, [50, 100], 8],
            ['ampul', 'box', 10, [10, 20], 6],
        ];
    }

    /** @return array<int, Medicine> */
    protected function seedMedicines(Collection $units): array
    {
        $categories = MedicineCategories::all();
        $profiles = $this->unitProfiles();
        $totalWeight = array_sum(array_column($profiles, 4));
        $names = $this->medicineNamePool();
        $seen = [];
        $medicines = [];

        for ($i = 0; $i < self::TOTAL_MEDICINES; $i++) {
            $base = strtoupper($names[$i % count($names)]);
            $name = $base.' '.$this->randomStrength();
            while (isset($seen[$name])) {
                $name = $base.' '.$this->randomStrength();
            }
            $seen[$name] = true;

            $roll = mt_rand(1, $totalWeight);
            $profile = $profiles[0];
            foreach ($profiles as $p) {
                if ($roll <= $p[4]) {
                    $profile = $p;
                    break;
                }
                $roll -= $p[4];
            }
            [$unitName, $packName, $packSize, $minOptions] = $profile;

            $medicines[] = Medicine::create([
                'name' => $name,
                'category_id' => $categories->random()->id,
                'unit_id' => $units[$unitName]->id,
                'pack_unit_id' => $units[$packName]->id,
                'pack_size' => $packSize,
                'min_stock' => $minOptions[array_rand($minOptions)],
                'stock_status' => 'empty',
                'status' => 'active',
            ]);
        }

        return $medicines;
    }

    // ---------------------------------------------------------------------
    // Rencana distribusi
    // ---------------------------------------------------------------------

    /** @param array<int, Medicine> $medicines */
    protected function planDistribution(array $medicines): void
    {
        foreach ($medicines as $m) {
            $min = max(1, (int) $m->min_stock);
            $ratio = $this->randomRatio();               // bracket C1
            $stock = (int) round($ratio * $min);
            $demand = $this->randomDemand();             // bracket C2
            $price = $this->randomPrice();               // bracket C4 (per satuan jual)
            $edFar = $this->randomExpiryDays();          // bracket C3 (batch terjauh)

            $hasNear = mt_rand(1, 100) <= 35 && $stock > 0;
            $edNear = $hasNear ? max(30, (int) round($edFar * mt_rand(30, 70) / 100)) : null;

            $expiredLeftover = mt_rand(1, 100) <= 8 ? mt_rand(2, 12) : 0; // F2: fisik > tersedia

            $this->plans[$m->id] = [
                'medicine' => $m,
                'min' => $min,
                'stock' => $stock,
                'demand' => $demand,
                'price' => $price,
                'ed_far' => $edFar,
                'ed_near' => $edNear,
                'expired_leftover' => $expiredLeftover,
            ];
        }
    }

    /** Rasio stok ÷ min_stock per bracket C1 (≤1 / 1,01–2 / 2,01–3,5 / 3,51–5 / >5), ~10% tepat 0 (T2). */
    protected function randomRatio(): float
    {
        $bucket = mt_rand(1, 100);

        return match (true) {
            $bucket <= 10 => 0.0,
            $bucket <= 30 => mt_rand(5, 100) / 100,
            $bucket <= 52 => mt_rand(101, 200) / 100,
            $bucket <= 72 => mt_rand(201, 350) / 100,
            $bucket <= 88 => mt_rand(351, 500) / 100,
            default => mt_rand(501, 900) / 100,
        };
    }

    /** Permintaan per 30 hari per bracket C2 (≤10 / 11–30 / 31–65 / 66–100 / ≥101). */
    protected function randomDemand(): int
    {
        $bucket = mt_rand(1, 100);

        return match (true) {
            $bucket <= 20 => mt_rand(0, 10),
            $bucket <= 42 => mt_rand(11, 30),
            $bucket <= 64 => mt_rand(31, 65),
            $bucket <= 84 => mt_rand(66, 100),
            default => mt_rand(101, 160),
        };
    }

    /** Sisa hari ED batch terjauh per bracket C3 (≤90 / 91–180 / 181–365 / 366–730 / ≥731). */
    protected function randomExpiryDays(): int
    {
        $bucket = mt_rand(1, 100);

        return match (true) {
            $bucket <= 15 => mt_rand(35, 90),
            $bucket <= 35 => mt_rand(91, 180),
            $bucket <= 62 => mt_rand(181, 365),
            $bucket <= 85 => mt_rand(366, 730),
            default => mt_rand(731, 1000),
        };
    }

    /** Harga per satuan jual per bracket C4 (≤2.000 / 2.001–10.000 / 10.001–50.000 / 50.001–100.000 / >100.000). */
    protected function randomPrice(): int
    {
        $bucket = mt_rand(1, 100);

        return match (true) {
            $bucket <= 22 => mt_rand(500, 2000),
            $bucket <= 50 => mt_rand(2001, 10000),
            $bucket <= 76 => mt_rand(10001, 50000),
            $bucket <= 90 => mt_rand(50001, 100000),
            default => mt_rand(100001, 250000),
        };
    }

    // ---------------------------------------------------------------------
    // Penerimaan
    // ---------------------------------------------------------------------

    protected function seedReceipts(Supplier $supplier): void
    {
        // Tiap baris = satu batch. Batch dekat (kalau ada) datang lebih dulu dan menampung
        // sebagian permintaan; batch jauh datang belakangan. Batch kedaluwarsa (F2) datang jauh
        // sebelumnya dan sengaja tidak habis.
        $oldLines = [];   // batch kedaluwarsa, ~200 hari lalu
        $nearLines = [];  // 40–60 hari lalu
        $farLines = [];   // 5–20 hari lalu

        foreach ($this->plans as $plan) {
            $m = $plan['medicine'];
            $needed = $plan['stock'] + $plan['demand'];

            if ($plan['expired_leftover'] > 0) {
                $oldLines[] = [$m, $plan['expired_leftover'], $plan['price'], -mt_rand(35, 90)];
            }

            if ($plan['ed_near'] !== null && $needed > 0) {
                $nearQty = max(1, (int) round($needed * mt_rand(30, 60) / 100));
                $nearLines[] = [$m, $nearQty, $plan['price'], $plan['ed_near']];
                $needed -= $nearQty;
            }

            // Obat tanpa stok & permintaan tetap butuh satu lapisan supaya punya HPP dan ikut SAW (K0):
            // diterima 1 lalu tidak terjual — stok tersedia 1, bukan 0. Untuk kasus stok 0 sungguhan,
            // permintaan > 0 menghabiskannya lewat penjualan.
            if ($needed > 0 || $plan['ed_near'] === null) {
                $farLines[] = [$m, max(1, $needed), $plan['price'], $plan['ed_far']];
            }
        }

        $this->writeInvoices($supplier, $oldLines, [200, 260]);
        $this->writeInvoices($supplier, $nearLines, [40, 60]);
        $this->writeInvoices($supplier, $farLines, [5, 20]);
    }

    /**
     * @param  array<int, array{0: Medicine, 1: int, 2: int, 3: int}>  $lines  [obat, qty satuan jual, harga/satuan jual, ED +hari]
     * @param  array{0:int,1:int}  $daysAgoRange
     */
    protected function writeInvoices(Supplier $supplier, array $lines, array $daysAgoRange): void
    {
        foreach (array_chunk($lines, self::INVOICE_LINES) as $chunk) {
            $date = Carbon::today()->subDays(mt_rand($daysAgoRange[0], $daysAgoRange[1]));
            $this->roSeq++;
            $this->invoiceSeq++;

            $ro = ReceiveOrder::create([
                'receive_order_number' => sprintf('RO-SPK-%04d', $this->roSeq),
                'invoice_number' => sprintf('%05d/SPK/%d/%s', 1900 + $this->invoiceSeq, $date->month, $date->format('y')),
                'purchase_order_id' => null,
                'supplier_id' => $supplier->id,
                'receive_date' => $date->toDateString(),
                'received_by' => $this->userId,
            ]);

            foreach ($chunk as [$m, $qty, $price, $edDays]) {
                // Faktur dalam kemasan bila qty kelipatan isi; kalau tidak, eceran (satuan jual).
                $packSize = max(1, (int) $m->pack_size);
                $inPack = $qty % $packSize === 0 && $qty >= $packSize;

                ReceiveOrderItem::create([
                    'receive_order_id' => $ro->id,
                    'medicine_id' => $m->id,
                    'medicine_name' => $m->name,
                    'pack_unit_id' => $inPack ? $m->pack_unit_id : $m->unit_id,
                    'pack_size' => $inPack ? $packSize : 1,
                    'pack_qty' => $inPack ? intdiv($qty, $packSize) : $qty,
                    'qty' => $qty,
                    'price' => $price,
                    'batch_number' => strtoupper(Str::random(2)).mt_rand(1000, 9999).strtoupper(Str::random(1)),
                    'expired_date' => Carbon::today()->addDays($edDays)->startOfMonth()->toDateString(),
                ]);
            }

            $this->movement->recordReceipt($ro);
        }
    }

    // ---------------------------------------------------------------------
    // Penjualan
    // ---------------------------------------------------------------------

    protected function seedSales(): void
    {
        // Sebar permintaan tiap obat ke hari-hari dalam 30 hari terakhir, lalu kelompokkan per hari
        // menjadi beberapa struk berisi ≤ 5 item.
        $daily = []; // date => [medicine_id => qty]
        foreach ($this->plans as $mid => $plan) {
            $left = $plan['demand'];
            while ($left > 0) {
                $day = Carbon::today()->subDays(mt_rand(0, self::DEMAND_DAYS - 1))->toDateString();
                $take = min($left, mt_rand(1, max(1, (int) ceil($plan['demand'] / 6))));
                $daily[$day][$mid] = ($daily[$day][$mid] ?? 0) + $take;
                $left -= $take;
            }
        }
        ksort($daily);

        foreach ($daily as $day => $perMedicine) {
            $pairs = [];
            foreach ($perMedicine as $mid => $qty) {
                $pairs[] = [$mid, $qty];
            }
            shuffle($pairs);

            foreach (array_chunk($pairs, 5) as $chunk) {
                $this->orderSeq++;
                $order = Order::create([
                    'order_code' => sprintf('ORD-SPK-%05d', $this->orderSeq),
                    'order_date' => $day,
                    'grand_total' => 0,
                    'note' => 'Data demo SPK',
                    'created_by' => $this->userId,
                ]);

                $total = 0;
                foreach ($chunk as [$mid, $qty]) {
                    $plan = $this->plans[$mid];
                    $salePrice = (int) (ceil($plan['price'] * mt_rand(115, 140) / 100 / 100) * 100);
                    OrderItem::create([
                        'order_id' => $order->id,
                        'medicine_id' => $mid,
                        'medicine_name' => $plan['medicine']->name,
                        'qty' => $qty,
                        'price' => $salePrice,
                    ]);
                    $total += $qty * $salePrice;
                }
                $order->update(['grand_total' => $total]);

                $this->movement->recordSale($order);
            }
        }
    }

    // ---------------------------------------------------------------------
    // Penanda & pool
    // ---------------------------------------------------------------------

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

    protected function randomStrength(): string
    {
        $options = ['250MG', '500MG', '650MG', '1000MG', '5MG', '10MG', '20MG', '50MG', '100MG', '125MG/5ML', '60ML', '100ML', '30G', '15G', '4MG', '0,5MG'];

        return $options[array_rand($options)];
    }

    /** @return array<int, string> */
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
            'Prednisone', 'Betamethasone', 'Hydrocortisone', 'Triamcinolone', 'Cetirizine Syrup',
            'Vitamin C', 'Vitamin B Complex', 'Vitamin D3', 'Sangobion', 'Enervon-C',
            'Imboost', 'Stimuno', 'Becom-C', 'Neurobion', 'Calcium Lactate',
            'Zinc', 'Ferrous Sulfate', 'Folic Acid', 'Vitamin E', 'Fish Oil',
            'Ketorolac', 'Tramadol', 'Codeine', 'Morphine', 'Fentanyl',
            'Ketoconazole', 'Fluconazole', 'Nystatin', 'Miconazole', 'Clotrimazole',
            'Acyclovir', 'Gentamicin', 'Chloramphenicol', 'Bacitracin', 'Neomycin',
        ];
    }
}
