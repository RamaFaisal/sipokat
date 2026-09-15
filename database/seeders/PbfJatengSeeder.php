<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

/**
 * Delapan PBF di sekitar apotek (Semarang, Demak, Kudus): tujuh dari "Daftar Alamat PBF
 * Propinsi Jawa Tengah" (Dinkes) plus PT. Nisa Permata Mulia, PBF Demak yang fakturnya dipakai
 * FakturNpmSeeder. Kolom mengikuti daftar itu (nama, alamat, telp, fax);
 * kode dibuat model dari inisial nama. Idempoten — kunci pada nama.
 */
class PbfJatengSeeder extends Seeder
{
    /** PBF langganan apotek; satu sumber untuk FakturNpmSeeder supaya nama (kunci dedup) tidak beda. */
    public const NPM = ['name' => 'PT. Nisa Permata Mulia', 'address' => 'Jl. Demak–Jepara, Dukuh Genting, Desa Sedo, Kec. Demak, Kab. Demak', 'phone' => '085640778829', 'fax' => null];

    public const PBF = [
        ['name' => 'PT Enseval Putera Megatrading Tbk', 'address' => 'Jl. Tambak Aji No. 1A RT 1/12, Kel. Tambak Aji, Kec. Ngaliyan, Semarang', 'phone' => '(024) 8664117', 'fax' => '(024) 8664123'],
        ['name' => 'PT Antar Mitra Sembada', 'address' => 'Jl. Stadion Timur No. 8, Semarang', 'phone' => '(0271) 351135, 48438', 'fax' => null],
        ['name' => 'PT Kimia Farma', 'address' => 'Jl. Tentara Pelajar 16 A, Semarang', 'phone' => '(0431) 62723', 'fax' => null],
        ['name' => 'PT Penta Valent', 'address' => 'Jl. Plampitan No. 64, Kel. Kranggan, Kec. Semarang Tengah, Semarang', 'phone' => '(0751) 21063, 21764', 'fax' => '(0751) 34006'],
        ['name' => 'PT Millenium Pharmacon International Tbk', 'address' => 'Jl. Merapi 16 RT 01/01, Kel. Gajah Mungkur, Kec. Gajah Mungkur, Semarang', 'phone' => '(024) 8446753', 'fax' => '(024) 8312557'],
        self::NPM,
        ['name' => 'PT Farmandika Al-Nur', 'address' => 'Jl. Pucangsari Timur Raya No. 14, Demak', 'phone' => '(024) 3566539', 'fax' => null],
        ['name' => 'PT Sehat Bersama Sejahtera', 'address' => 'Jl. A. Yani No. 97, Kudus', 'phone' => '(0431) 52456, 52458', 'fax' => null],
    ];

    public function run(): void
    {
        foreach (self::PBF as $pbf) {
            Supplier::updateOrCreate(['name' => $pbf['name']], $pbf + ['status' => 'active']);
        }
    }
}
