<?php

use App\Models\Medicine;
use App\Models\ReceiveOrder;
use App\Services\RealDataImporter;
use App\Services\StockCardService;
use App\Support\RealDataTemplate;

/**
 * Template data riil + importer (rencana D5, C1–C3): contoh baris di template (dari faktur
 * PT. Nisa Permata Mulia) harus bisa dimuat apa adanya lewat jalur kartu stok.
 */
beforeEach(function () {
    seedMasterFixtures();
    foreach ([['Flask', 'FLS'], ['Tube', 'TUB'], ['Box', 'BOX']] as [$n, $a]) {
        \App\Models\Unit::create(['name' => $n, 'alias' => $a]);
    }
    \App\Models\MedicineCategories::create(['name' => 'Obat Keras', 'alias' => 'OBK']);
    $this->path = sys_get_temp_dir().'/sipokat-template-'.uniqid().'.xlsx';
    RealDataTemplate::write($this->path);
});

afterEach(fn () => @unlink($this->path));

it('memuat contoh template: obat, saldo awal, faktur per nomor, penjualan — dan HPP terbentuk', function () {
    $result = app(RealDataImporter::class)->import($this->path, \Carbon\Carbon::parse('2024-05-01'));

    expect($result['errors'])->toBe([])
        ->and($result['summary'])->toMatchArray(['obat' => 3, 'saldo_awal' => 2, 'faktur' => 3, 'faktur_baris' => 3, 'penjualan' => 2, 'penjualan_baris' => 2]);

    $calortusin = Medicine::where('name', 'CALORTUSIN KAPLET')->first();
    $stockCard = app(StockCardService::class);

    // Saldo awal 35 @4.100 + faktur 10 Box × 10 @41.000 → 100 @4.100; terjual 3 → 132 tersedia.
    expect($calortusin->pack_size)->toBe(10)
        ->and($stockCard->physicalStock($calortusin->id))->toBe(132)
        ->and($stockCard->currentHpp($calortusin->id))->toBe(4100);

    // Satu RO per faktur (R10) + satu RO saldo awal.
    expect(ReceiveOrder::count())->toBe(4)
        ->and(ReceiveOrder::where('invoice_number', '02028/NPM/5/24')->exists())->toBeTrue()
        ->and(ReceiveOrder::where('invoice_number', 'like', 'SALDO-AWAL-%')->first()->receive_date->toDateString())->toBe('2024-05-01');

    // ED bulan-tahun → tanggal 1.
    $layer = $stockCard->layers($calortusin->id)->firstWhere('batch_number', 'T10088BC');
    expect($layer->expired_date->toDateString())->toBe('2026-10-01');
});

it('menolak seluruh muatan bila ada satu baris yang salah (transaksional)', function () {
    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->path);
    $spreadsheet->getSheetByName('Faktur')->setCellValue('D3', 'OBAT TIDAK ADA');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet))->save($this->path);

    $result = app(RealDataImporter::class)->import($this->path, \Carbon\Carbon::parse('2024-05-01'));

    expect($result['errors'])->not->toBe([])
        ->and($result['errors'][0])->toContain('tidak ada di sheet Obat')
        ->and(Medicine::count())->toBe(0)
        ->and(ReceiveOrder::count())->toBe(0);
});

it('dry-run memvalidasi tanpa menyimpan', function () {
    $result = app(RealDataImporter::class)->import($this->path, \Carbon\Carbon::parse('2024-05-01'), dryRun: true);

    expect($result['errors'])->toBe([])->and(Medicine::count())->toBe(0);
});
