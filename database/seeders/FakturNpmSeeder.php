<?php

namespace Database\Seeders;

use App\Filament\Forms\PackLine;
use App\Models\Medicine;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockMovementService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 14 faktur asli PT. Nisa Permata Mulia (contoh-data.zip, 07–11 Mei 2024) ditulis sebagai RO
 * lewat StockMovementService — jalur yang sama dengan form Penerimaan.
 *
 * Tanggal faktur dan ED digeser sejumlah bulan yang sama, dari Mei 2024 ke bulan berjalan saat
 * seeder dijalankan (Sep 2026 → +28 bulan): ED 12-26 menjadi 04-29, dst. Tanggal terima yang jatuh
 * setelah hari ini dijepit ke hari ini.
 *
 * Idempoten: faktur yang nomornya sudah ada untuk PBF ini dilewati.
 * Obat harus sudah ada di master (sipokat:data-riil:import master-data-obat.xlsx).
 */
class FakturNpmSeeder extends Seeder
{
    public const SUPPLIER = PbfJatengSeeder::NPM;

    /** Bulan-tahun asal faktur; offset dihitung ke bulan berjalan. */
    public const ORIGIN = '2024-05';

    /** ED kosong di faktur → tanggal terima + sekian bulan (perlu dikonfirmasi ke fisik kemasan). */
    public const BLANK_ED_MONTHS = 24;

    /**
     * ASUMSI isi kemasan untuk baris yang di faktur dibeli per BOX padahal master mencatat kemasan
     * beli STRIP isi 10 (isi box tidak tercetak di faktur). Ubah di sini bila fisiknya berbeda.
     */
    public const ASSUMED_BOX_SIZE = [
        'MELOXICAM 7,5 MG' => 100,
        'CEFIXIME 200MG DEXA' => 100,
        'GLIMEPIRIDE 2 MG HXP' => 100,
        'WIROS 20MG' => 100,
        'SELVIM 10MG' => 100,
        'CIPROFLOXACIN 500MG HXP' => 100,
        'LANSOPRAZOLE 30MG NULAB' => 100,
        'METHYLPREDNISOLONE 4MG DEXA' => 100,
        'PIROCAM 20MG' => 100,
        'CEFADROXIL 500MG TAB MEPRO' => 100,
        'CEFADROXIL 500MG HXP' => 100,
        'DOBRIZOL 30 MG' => 100,
        'GLIMEPIRIDE 4MG DEXA' => 100,
        'GLIMEPIRIDE 2MG DEXA' => 100,
        'GLIMEPIRIDE 1MG DEXA' => 100,
        'FAXIDEN 20MG' => 100,
        'RHEFICAM 20MG' => 100,
        // Pil KB: master "STRIP isi 28" dibaca sebagai 1 BOX = 28 strip.
        'ANDALAN PIL KB' => 28,
        "MICROGYNON LIBI 10'S" => 28,
    ];

    /**
     * Faktur: [no_faktur, tanggal asal d-m-Y, total tercetak (pengaman salin), baris...]
     * Baris: [nama obat, kemasan di faktur, jumlah kemasan, @harga (sudah PPN), no. batch, ED 'MM-YY' | null]
     */
    public const INVOICES = [
        ['01955/NPM/5/24', '07-05-2024', 2128900, [
            ['KETOROLAC INJ MEPRO', 'AMP', 100, 1900, 'F4C745', '03-27'],
            ['GG TRIMAN KLG', 'KLG', 20, 6750, 'D24002', '04-26'],
            ['IFIDEX 0,5MG KLG', 'KLG', 50, 4500, '22260310', '10-26'],
            ['ACIFAR CR', 'TUBE', 20, 5900, '40423', '03-27'],
            ['ERLADERM-N KRIM', 'TUB', 18, 4800, 'O1958030', '09-25'],
            ['GEN OINT SK', 'TUBE', 10, 4800, 'O175901', '01-26'],
            ['ORSADERM CR', 'TUBE', 20, 3400, '20127', '07-24'],
            ['PRODERMIS CR 5GR', 'TUBE', 20, 5300, '30531', '11-26'],
            ['GENTAMICIN CR BERNO 5GR 0.1%', 'TUBE', 50, 2700, 'LCL46252', '10-25'],
            ['RECO TM', 'FLS', 25, 8600, '0091223019', null],
            ['RECO TT', 'FLS', 25, 8200, '0101223007', null],
            ["MICROGYNON LIBI 10'S", 'BOX', 2, 197500, '2310430', '02-25'],
            ['ANDALAN PIL KB', 'BOX', 1, 202500, '3193198', '09-28'],
        ]],
        ['01980/NPM/5/24', '08-05-2024', 3482500, [
            ['VIBRAMOX 500MG', 'BOX', 10, 39000, 'AY046', '01-26'],
            ['AMOSTERA 500MG', 'BOX', 10, 50000, '34415', '11-26'],
            ['GRATHAZONE 0.5MG', 'BOX', 10, 28250, 'TE014G', '05-27'],
            ['LICODEXON 0.5MG', 'BOX', 10, 25250, 'CR1022019', '03-26'],
            ['SALBUTAMOL 2MG FM', 'BOX', 10, 10500, '006843', '03-26'],
            ['AMBROXOL 30MG IFI', 'BOX', 10, 9750, '24711310', '10-26'],
            ['GLIMEPIRIDE 4MG DEXA', 'BOX', 10, 32500, '54K0269', '09-26'],
            ['GLIMEPIRIDE 2MG DEXA', 'BOX', 10, 21750, '54I4207', '07-27'],
            ['GLIMEPIRIDE 1MG DEXA', 'BOX', 10, 19750, '54J4109', '09-26'],
            ['FAXIDEN 20MG', 'BOX', 10, 20500, '32432', '12-25'],
            ['RHEFICAM 20MG', 'BOX', 10, 20250, 'A1001301', '01-27'],
            ['GINIFAR', 'BOX', 10, 24750, '31430', '10-27'],
            ['HUFRALGIN', 'BOX', 10, 46000, 'M200144', '02-26'],
        ]],
        ['01981/NPM/5/24', '08-05-2024', 2449250, [
            ['KALMETHASONE 0,5MG', 'BOX', 10, 21000, 'D41169', '01-26'],
            ['MEXON KAP', 'BOX', 10, 23750, 'CB 0211', '02-28'],
            ['VESPERUM TABLET', 'BOX', 10, 21500, '3Z032', '12-27'],
            ['AMLODIPINE 5MG MERSI', 'BOX', 10, 9250, 'A31329', '09-25'],
            ['DOBRIZOL 30 MG', 'BOX', 10, 36750, '40721', '02-27'],
            ['YEKAFLAM 50MG', 'BOX', 10, 19000, 'BP2823012', '11-25'],
            ['FENAREN 50MG', 'BOX', 10, 23750, 'TSL93252', '10-28'],
            ['GASELA 150 MG', 'BOX', 10, 26750, 'C0459045', '01-26'],
            ['HUFADINE', 'BOX', 10, 31250, 'K800034', '01-27'],
            ['SIMVASTATIN 10MG MERSI', 'BOX', 10, 2750, 'A30904', '07-25'],
            ['SIMVASTATIN 20MG SAMPHARINDO', 'BOX', 10, 18000, 'CB 0901', '02-26'],
            ['NIFEDIPINE 10MG DEXA', 'BOX', 3, 16000, '54C4167', '02-27'],
            ['GRAFALIN 2MG', 'BOX', 5, 12750, 'TL071G', '12-27'],
        ]],
        ['01982/NPM/5/24', '08-05-2024', 2013500, [
            ['GRAFALIN 4MG', 'BOX', 5, 17500, 'TH260G', '08-26'],
            ['TRICLOFEM 150MG/ML INJ', 'BOX', 5, 165000, '052L232A', '12-28'],
            ['GENALTEN CR 5GR', 'TUBE', 30, 3700, '40123', '03-27'],
            ['NORVOM SYR 60ML', 'FLS', 48, 5200, '30332', '12-25'],
            ['ROVERTON SYR 60ML', 'FLS', 48, 4800, '40722', '02-28'],
            ['LOSTACEF 125MG DRY SYR', 'FLS', 60, 8500, '31232', '12-26'],
        ]],
        ['01985/NPM/5/24', '08-05-2024', 1673800, [
            ['FLUTOP-C SYR 60ML', 'FLS', 48, 8100, '40623', '03-27'],
            ['COPARCETIN KAPLET', 'BOX', 10, 47750, 'CA 1281', '01-26'],
            ['COLPICA', 'BOX', 10, 40500, 'PF0701', '06-25'],
            ['FLUTAMOL KAPLET', 'BOX', 10, 40250, 'KR116', '11-25'],
        ]],
        ['01986/NPM/5/24', '08-05-2024', 170000, [
            ['IFARSYL KAPLET', 'BOX', 5, 34000, '41622', '02-27'],
        ]],
        ['02022/NPM/5/24', '11-05-2024', 8202500, [
            ['ETAMOX 500MG', 'BOX', 10, 55250, 'FP4033', '06-26'],
            ['YUSIMOX 500 MG', 'BOX', 10, 50750, '40122', '02-28'],
            ['HUFANOXIL 500MG', 'BOX', 10, 49750, 'L901454', '04-27'],
            ['ZEMOXIL 500MG', 'BOX', 10, 52500, '11701403', '01-26'],
            ['BROADAMOX 500MG', 'BOX', 10, 51000, 'BH 2961', '08-26'],
            ['BINTAMOX 500MG', 'BOX', 10, 52000, 'TCBTXA33761', null],
            ['AMOXICILLIN 500 MG ERITA', 'BOX', 10, 49000, 'IP 4039', '09-26'],
            ['BIMAFLOX 500MG', 'BOX', 10, 57750, '0370242', '02-29'],
            ['BUFACIPRO BOX', 'BOX', 10, 48000, 'B0211312', '11-25'],
            ['FLOXIFAR 500MG', 'BOX', 10, 49250, '32029', '09-27'],
            ['SAMQUINOR 500MG', 'BOX', 10, 50500, '1130602', '03-29'],
            ['CEFADROXIL 500MG TAB MEPRO', 'BOX', 10, 79500, 'B3F493', '07-25'],
            ['CEFADROXIL 500MG HXP', 'BOX', 10, 76500, 'KCFDC32902', null],
            ['LOSTACEF 500MG', 'BOX', 10, 98500, '44121', '01-28'],
        ]],
        ['02023/NPM/5/24', '11-05-2024', 4462500, [
            ['GRAFAZOL 500MG', 'BOX', 10, 34500, 'TB 196 G', '02-28'],
            ['CEFIXIME 200MG DEXA', 'BOX', 10, 126000, 'S4I0340', '09-26'],
            ['ROVERTON KAPLET', 'BOX', 10, 16000, '40123', '03-28'],
            ['BROXAL TAB 30MG', 'BOX', 10, 23250, 'TPJ11552', '08-28'],
            ['ELTAZON 5MG', 'BOX', 10, 16250, '30832', '12-26'],
            ['CARBIDU 0.5MG', 'BOX', 10, 25750, 'BJ 3591', '10-25'],
            ['GLIMEPIRIDE 2 MG HXP', 'BOX', 10, 24000, 'HTGMPK35334', null],
            ['GLIBENCLAMIDE 5MG INF', 'BOX', 10, 15750, '23G01271', '07-25'],
            ['TEOSAL TABLET', 'BOX', 10, 14750, '5414300', '08-26'],
            ['WIROS 20MG', 'BOX', 10, 22000, '311025', '11-27'],
            ['BIOPYRON KAPLET', 'BOX', 10, 41500, '0170249', '02-28'],
            ['MIXALGIN TAB', 'BOX', 10, 55000, 'C0859010', '12-25'],
            ['DANASONE 0,5MG', 'BOX', 10, 19250, 'TDSNA30154', null],
            ['LANADEXON KAP 0,5 MG', 'BOX', 10, 12250, '27A029', '01-27'],
        ]],
        ['02024/NPM/5/24', '11-05-2024', 3367500, [
            ['XICORTA 0,5MG', 'BOX', 10, 21250, '03801301', '01-27'],
            ["ETADEX 0,5MG 200'S", 'BOX', 10, 22250, 'KP 1120', '11-27'],
            ['MOLACORT 0,75MG', 'BOX', 10, 26750, 'M05GJ002', '01-26'],
            ['MELOXICAM 7,5 MG', 'BOX', 10, 15000, '22M6808', '12-26'],
            ['FARMOTEN 25MG', 'BOX', 10, 25500, '1HH016', '08-25'],
            ['AMLODIPINE 5MG MERSI', 'BOX', 10, 9250, 'A31329', '09-25'],
            ['ALTAMED GLOVE SIZE M', 'BOX', 5, 35000, '3025', '08-28'],
            ['GRAFACHLOR', 'BOX', 10, 18000, 'TC183G', '03-28'],
            ['BUFACARYL TAB', 'BOX', 10, 22750, 'A0309303', '09-25'],
            ['DEXTEEM PLUS 2', 'BOX', 10, 26500, 'T0623601', '01-28'],
            ['HUFADEXTA-M', 'BOX', 10, 20500, 'O100064', '02-26'],
            ['MILORIN 300 MG', 'BOX', 10, 68500, '30128', '08-25'],
            ['INAMID 2MG', 'BOX', 10, 19500, '4040008.2', '02-26'],
            ['HISTIGO KAPLET', 'BOX', 10, 23500, '41122', '02-27'],
        ]],
        ['02025/NPM/5/24', '11-05-2024', 4693500, [
            ['BIDIUM', 'BOX', 10, 14250, '0020141', '01-26'],
            ['SELVIM 10MG', 'BOX', 10, 16000, '40721', '02-28'],
            ['CIPROFLOXACIN 500MG HXP', 'BOX', 15, 43500, 'HTCFXC41968', null],
            ['ALLOPURINOL 100MG IFI', 'BOX', 15, 17000, '24402310', '10-25'],
            ['ALLOPURINOL 100MG NOVA', 'BOX', 15, 17500, '2402-02106', '02-26'],
            ['ALOFAR 100MG', 'BOX', 15, 26750, '41423', '03-28'],
            ['LANSOPRAZOLE 30MG NULAB', 'BOX', 15, 12500, 'N2402079', '03-26'],
            ['RANITIDINE 150MG HXP', 'BOX', 15, 18750, 'HTRNTB36754', null],
            ['RANCUS 150MG', 'BOX', 15, 27750, 'A40078', '01-26'],
            ['DIONICOL 500MG', 'BOX', 5, 109750, '41822', '02-27'],
            ['METHYLPREDNISOLONE 4MG DEXA', 'BOX', 30, 17000, '54H4260', '07-25'],
            ['PIROCAM 20MG', 'BOX', 24, 20250, '54K0399', '10-28'],
            ['ERLAMICETIN TM', 'FLS', 20, 9750, 'D0759004', '07-25'],
            ['ERLAMICETIN TT', 'FLS', 20, 9750, 'D0659024', '01-26'],
        ]],
        ['02026/NPM/5/24', '11-05-2024', 4139400, [
            ['RECO TM', 'FLS', 25, 8600, '0091223003', null],
            ['ANDALAN PIL KB', 'BOX', 1, 202500, '3193198', '09-28'],
            ["MICROGYNON LIBI 10'S", 'BOX', 1, 197500, '2310430', '02-25'],
            ['CAZETIN DROP 15ML', 'FLS', 20, 18250, '40321', '01-26'],
            ['ETAMOXUL SUSP', 'FLS', 50, 4400, 'LP 2002', '12-26'],
            ['HELIXIM 100MG DRY SYR', 'FLS', 48, 10500, '46121', '01-26'],
            ['LOSTACEF 125MG DRY SYR', 'FLS', 60, 8500, '31232', '12-26'],
            ['SUCRALFATE SYR 100ML DEXA', 'FLS', 48, 11500, '5584263', '01-26'],
            ['YUSIMOX DRY SYR 60ML', 'FLS', 60, 4800, '45222', '02-27'],
            ['SAMMOXIN DRY SY 60ML', 'FLS', 50, 5100, '7125CL', '10-26'],
            ['ZEMOXIL DRY SYR 60ML', 'FLS', 60, 5100, '06103403', '03-26'],
            ['HUFADON SYR', 'FLS', 60, 4500, 'A300144', '03-27'],
            ['VESPERUM SYR 60ML', 'FLS', 48, 5300, '40524', '04-26'],
        ]],
        ['02027/NPM/5/24', '11-05-2024', 2904500, [
            ['CTM 4MG KLG TRIMAN', 'KLG', 50, 3100, 'F23081', '06-25'],
            ['IFIDEX 0,5MG KLG', 'KLG', 50, 4500, '22260310', '10-26'],
            ['ZENCLOVIR CREAM', 'TUBE', 24, 6000, '15202401A', '02-28'],
            ['ACIFAR CR', 'TUBE', 20, 5900, '40423', '03-27'],
            ['ELOMOX CR 5GR', 'TUBE', 20, 7900, '30132', '12-26'],
            ['PRODERMIS CR 5GR', 'TUBE', 20, 5300, '30531', '11-26'],
            ['GEN OINT SK', 'TUBE', 20, 4800, 'O1759024', '02-26'],
            ['HYDROCOTISONE CR BERNO 5GR', 'TUBE', 50, 4000, 'LCM51952', '11-25'],
            ['CYCLOFEM INJ 0.5ML', 'BOX', 5, 175500, 'CI07431A', '08-28'],
            ['TRICLOFEM 150MG/ML INJ', 'BOX', 5, 165000, '052L232A', '12-28'],
        ]],
        ['02028/NPM/5/24', '11-05-2024', 2683000, [
            ['CALORTUSIN KAPLET', 'BOX', 10, 41000, 'T10088BC', '10-26'],
            ['FLUCADEX', 'BOX', 10, 61500, 'TE256G', '05-26'],
            ['ALPARA KAPLET', 'BOX', 5, 94000, 'A06FO299', '10-25'],
            ['COPARCETIN SYR', 'FLS', 100, 7500, 'CB 2281', '02-26'],
            ['PASABA COUGH & FLU SYR', 'FLS', 60, 7300, 'C200064', '03-26'],
        ]],
        ['02029/NPM/5/24', '11-05-2024', 1396500, [
            ['COLFIN SYR', 'FLS', 60, 7900, '3067073', '12-26'],
            ['LODECON FORTE', 'BOX', 10, 53000, 'TNZ 30', '09-25'],
            ['GRANTUSIF', 'BOX', 10, 39250, 'B48011', '02-28'],
        ]],
    ];

    protected int $monthOffset;

    public function run(): void
    {
        $this->monthOffset = (int) Carbon::parse(self::ORIGIN.'-01')->startOfDay()->diffInMonths(Carbon::today()->startOfMonth());

        $supplier = Supplier::firstOrCreate(['name' => self::SUPPLIER['name']], self::SUPPLIER + ['status' => 'active']); // kode otomatis: NPM
        $userId = User::query()->orderBy('id')->value('id');
        $units = [];
        foreach (Unit::all() as $u) {
            $units[strtolower($u->name)] = $u;
            $units[strtolower($u->alias)] = $u;
        }
        $medicines = Medicine::all()->keyBy('name');
        $movement = app(StockMovementService::class);

        $missing = [];
        foreach (self::INVOICES as [$no, $date, $total, $lines]) {
            $sum = array_sum(array_map(fn ($l) => $l[2] * $l[3], $lines));
            if ($sum !== $total) {
                throw new RuntimeException("Faktur {$no}: jumlah baris {$sum} ≠ total tercetak {$total} — periksa salinan.");
            }
            foreach ($lines as $line) {
                $name = Medicine::normalizeName($line[0]);
                if (! isset($medicines[$name])) {
                    $missing[$name] = true;
                }
            }
        }
        if ($missing) {
            throw new \RuntimeException('Obat belum ada di master: '.implode(', ', array_keys($missing)));
        }

        $written = 0;
        $skipped = 0;
        $medicineIds = [];

        foreach (self::INVOICES as [$no, $originDate, $total, $lines]) {
            if (ReceiveOrder::withTrashed()->where('supplier_id', $supplier->id)->where('invoice_number', $no)->exists()) {
                $skipped++;

                continue;
            }

            $receiveDate = Carbon::createFromFormat('d-m-Y', $originDate)->startOfDay()->addMonths($this->monthOffset);
            if ($receiveDate->gt(Carbon::today())) {
                $receiveDate = Carbon::today();
            }

            DB::transaction(function () use ($no, $receiveDate, $lines, $supplier, $userId, $units, $medicines, $movement, &$medicineIds) {
                $ro = ReceiveOrder::create([
                    'receive_order_number' => ReceiveOrder::nextNumber($receiveDate),
                    'invoice_number' => $no,
                    'purchase_order_id' => null,
                    'supplier_id' => $supplier->id,
                    'receive_date' => $receiveDate->toDateString(),
                    'received_by' => $userId,
                ]);

                foreach ($lines as [$name, $packAlias, $packQty, $packPrice, $batch, $ed]) {
                    /** @var Medicine $m */
                    $m = $medicines[Medicine::normalizeName($name)];
                    $pack = $units[strtolower($packAlias)] ?? null;
                    if (! $pack) {
                        throw new \RuntimeException("Satuan '{$packAlias}' tidak dikenal ({$name}).");
                    }
                    $packSize = $this->packSize($m, $pack);
                    [$qty, $price] = PackLine::convert($packQty, $packSize, $packPrice);

                    ReceiveOrderItem::create([
                        'receive_order_id' => $ro->id,
                        'medicine_id' => $m->id,
                        'medicine_name' => $m->name,
                        'pack_unit_id' => $pack->id,
                        'pack_size' => $packSize,
                        'pack_qty' => $packQty,
                        'qty' => $qty,
                        'price' => $price,
                        'batch_number' => $batch,
                        'expired_date' => $this->expiredDate($ed, $receiveDate)->toDateString(),
                    ]);
                    $medicineIds[$m->id] = $m->id;
                }

                $movement->recordReceipt($ro);
            });
            $written++;
        }

        if ($medicineIds) {
            $movement->refreshStockStatus(array_values($medicineIds));
        }

        $this->command?->info("FakturNpmSeeder: {$written} faktur ditulis, {$skipped} dilewati (sudah ada); geser +{$this->monthOffset} bulan.");
    }

    /** Isi kemasan per baris: sama dengan master bila kemasannya sama; satuan jual → 1; selain itu ASSUMED_BOX_SIZE. */
    protected function packSize(Medicine $m, Unit $pack): int
    {
        if ((int) $pack->id === (int) $m->pack_unit_id) {
            return max(1, (int) $m->pack_size);
        }
        if ((int) $pack->id === (int) $m->unit_id) {
            return 1;
        }
        if (isset(self::ASSUMED_BOX_SIZE[$m->name])) {
            return self::ASSUMED_BOX_SIZE[$m->name];
        }

        throw new \RuntimeException("Isi kemasan {$pack->name} untuk {$m->name} tidak diketahui — tambahkan ke ASSUMED_BOX_SIZE.");
    }

    /** 'MM-YY' digeser sejumlah bulan yang sama dengan faktur; kosong → tanggal terima + BLANK_ED_MONTHS. */
    protected function expiredDate(?string $ed, Carbon $receiveDate): Carbon
    {
        if (! $ed) {
            return $receiveDate->copy()->addMonths(self::BLANK_ED_MONTHS)->startOfMonth();
        }
        [$mm, $yy] = explode('-', $ed);

        return Carbon::create(2000 + (int) $yy, (int) $mm, 1)->addMonths($this->monthOffset);
    }
}
