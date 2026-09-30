<?php

use App\Filament\Imports\MedicineImporter;
use App\Filament\Imports\SupplierImporter;
use App\Filament\Resources\Medicines\Pages\ListMedicines;
use App\Filament\Resources\Suppliers\Pages\ListSuppliers;
use App\Models\Medicine;
use App\Models\Supplier;
use App\Models\User;
use App\Support\ImporterTemplate;
use App\Support\SpreadsheetImporter;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Import master data menerima Excel, bukan hanya CSV (permintaan peneliti 2026-09-30).
 *
 * Aturan kolomnya tidak ditulis ulang: yang diuji adalah berkas .xlsx benar-benar sampai ke kelas
 * Importer yang sudah ada, lengkap dengan tebakan judul kolom, validasi, dan pengenalan data lama.
 */
beforeEach(function () {
    seedMasterFixtures();
    Gate::before(fn () => true);
    $this->actingAs(User::factory()->create());
});

/** Menulis baris ke berkas .xlsx sementara dan mengembalikan pathnya. */
function berkasExcel(array $baris): string
{
    $spreadsheet = new Spreadsheet;
    $spreadsheet->getActiveSheet()->fromArray($baris, null, 'A1');

    $path = tempnam(sys_get_temp_dir(), 'uji-impor').'.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return $path;
}

it('mengimpor PBF dari berkas Excel beserta kode otomatisnya', function () {
    $path = berkasExcel([
        ['Nama Supplier', 'Alamat', 'Telp'],
        ['PT. Nisa Permata Mulia', 'Semarang', '(024) 8664117'],
        ['PT. Anugrah Argon Medica', 'Demak', '(024) 8664123'],
    ]);

    $hasil = SpreadsheetImporter::jalankan(SupplierImporter::class, $path, 'pbf.xlsx');
    @unlink($path);

    expect($hasil['berhasil'])->toBe(2)
        ->and($hasil['gagal'])->toBe(0)
        ->and(Supplier::where('name', 'PT. Nisa Permata Mulia')->value('code'))->toBe('NPM')
        ->and(Supplier::where('name', 'PT. Anugrah Argon Medica')->value('address'))->toBe('Demak');
});

it('mengimpor obat dari berkas Excel lewat aturan importer yang sudah ada', function () {
    $path = berkasExcel([
        ['Nama Obat', 'Kategori', 'Satuan Jual', 'Kemasan Pembelian', 'Isi per Kemasan', 'Stok Minimum'],
        ['PARACETAMOL EXCEL 500MG', 'Obat Bebas', 'Strip', 'Strip', 10, 20],
    ]);

    $hasil = SpreadsheetImporter::jalankan(MedicineImporter::class, $path, 'obat.xlsx');
    @unlink($path);

    $obat = Medicine::where('name', 'PARACETAMOL EXCEL 500MG')->first();

    expect($hasil['berhasil'])->toBe(1)
        ->and($obat)->not->toBeNull()
        ->and($obat->pack_size)->toBe(10)
        ->and($obat->min_stock)->toBe(20)
        ->and($obat->code)->toStartWith('OBT');
});

it('masih menerima berkas CSV lama', function () {
    $path = tempnam(sys_get_temp_dir(), 'uji-impor').'.csv';
    file_put_contents($path, "Nama Supplier,Alamat\nPT. Bina Sehat Prima,Kudus\n");

    $hasil = SpreadsheetImporter::jalankan(SupplierImporter::class, $path, 'pbf.csv');
    @unlink($path);

    expect($hasil['berhasil'])->toBe(1)
        ->and(Supplier::where('name', 'PT. Bina Sehat Prima')->exists())->toBeTrue();
});

it('mengenali PBF yang namanya sudah ada, bukan membuat ganda', function () {
    $path = berkasExcel([
        ['Nama Supplier', 'Alamat'],
        ['pbf fixture', 'Alamat Baru'],
    ]);

    $sebelum = Supplier::count();
    $hasil = SpreadsheetImporter::jalankan(SupplierImporter::class, $path, 'pbf.xlsx');
    @unlink($path);

    // Nama di berkas menimpa ejaan yang tersimpan, jadi dicocokkan tanpa memandang huruf besar.
    expect($hasil['berhasil'])->toBe(1)
        ->and(Supplier::count())->toBe($sebelum)
        ->and(Supplier::whereRaw('LOWER(name) = ?', ['pbf fixture'])->value('address'))->toBe('Alamat Baru');
});

it('menolak berkas yang kehilangan kolom wajib dan menyebut kolomnya', function () {
    $path = berkasExcel([
        ['Alamat', 'Telp'],
        ['Semarang', '(024) 8664117'],
    ]);

    $sebelum = Supplier::count();
    $hasil = SpreadsheetImporter::jalankan(SupplierImporter::class, $path, 'pbf.xlsx');
    @unlink($path);

    expect($hasil['berhasil'])->toBe(0)
        ->and($hasil['pesan'][0])->toContain('Nama Supplier')
        ->and(Supplier::count())->toBe($sebelum);
});

it('melaporkan baris yang gagal validasi tanpa menggagalkan baris lainnya', function () {
    $path = berkasExcel([
        ['Nama Obat', 'Kategori', 'Satuan Jual', 'Kemasan Pembelian', 'Isi per Kemasan'],
        ['OBAT KATEGORI SALAH', 'Kategori Ngawur', 'Strip', 'Strip', 10],
        ['OBAT KATEGORI BENAR', 'Obat Bebas', 'Strip', 'Strip', 10],
    ]);

    $hasil = SpreadsheetImporter::jalankan(MedicineImporter::class, $path, 'obat.xlsx');
    @unlink($path);

    expect($hasil['berhasil'])->toBe(1)
        ->and($hasil['gagal'])->toBe(1)
        // Nomor baris mengikuti yang terlihat di Excel supaya mudah dibuka.
        ->and($hasil['pesan'][0])->toStartWith('Baris 2:')
        ->and(Medicine::where('name', 'OBAT KATEGORI BENAR')->exists())->toBeTrue()
        ->and(Medicine::where('name', 'OBAT KATEGORI SALAH')->exists())->toBeFalse();
});

it('menyediakan tombol import beserta unduhan template di dalam modalnya', function (string $halaman, string $importer) {
    $uji = Livewire::test($halaman)
        ->assertActionExists('impor')
        // Template bukan tombol header, melainkan tautan di dalam modal import.
        ->assertActionDoesNotExist('unduhTemplate')
        ->assertOk();

    $deskripsi = (string) $uji->instance()->getAction('impor')->getModalDescription();

    expect($deskripsi)->toContain('Unduh Template')
        ->and($deskripsi)->toContain("mountAction('unduhTemplate')")
        ->and(ImporterTemplate::xlsx($importer, 'template.xlsx'))->toBeInstanceOf(StreamedResponse::class);
})->with([
    'obat' => [ListMedicines::class, MedicineImporter::class],
    'supplier' => [ListSuppliers::class, SupplierImporter::class],
]);

it('tidak lagi meminta kolom status dan tetap mengaktifkan data hasil import', function () {
    // Kolom Status dari berkas lama sengaja ikut disertakan: harus diabaikan, bukan bikin gagal.
    $pbfPath = berkasExcel([
        ['Nama Supplier', 'Alamat', 'Status'],
        ['PT. Tanpa Status', 'Semarang', 'inactive'],
    ]);
    $obatPath = berkasExcel([
        ['Nama Obat', 'Kategori', 'Satuan Jual', 'Kemasan Pembelian', 'Isi per Kemasan'],
        ['OBAT TANPA STATUS', 'Obat Bebas', 'Strip', 'Strip', 10],
    ]);

    SpreadsheetImporter::jalankan(SupplierImporter::class, $pbfPath, 'pbf.xlsx');
    SpreadsheetImporter::jalankan(MedicineImporter::class, $obatPath, 'obat.xlsx');
    @unlink($pbfPath);
    @unlink($obatPath);

    $kolom = fn (string $importer): array => collect($importer::getColumns())
        ->map(fn ($k): string => $k->getName())
        ->all();

    expect($kolom(SupplierImporter::class))->not->toContain('status')
        ->and($kolom(MedicineImporter::class))->not->toContain('status')
        // Bawaan kolom status di tabel suppliers adalah inactive, jadi harus diaktifkan sendiri.
        ->and(Supplier::where('name', 'PT. Tanpa Status')->value('status'))->toBe('active')
        ->and(Medicine::where('name', 'OBAT TANPA STATUS')->value('status'))->toBe('active');
});
