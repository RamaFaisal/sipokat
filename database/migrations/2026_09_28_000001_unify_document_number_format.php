<?php

use App\Models\MedicineStockOpname;
use App\Models\Order;
use App\Models\PurchaseOrder;
use App\Models\ReceiveOrder;
use App\Support\DocumentNumber;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Satu format nomor dokumen: PREFIKS + YYYYMMDD + urutan empat digit, tanpa pemisah (K12, K13).
 *
 * Sebelum ini tiap dokumen punya bentuknya sendiri:
 *   PO20260916-0001   -> PO202609160001
 *   RO20260916-0001   -> RO202609160001
 *   ORD-202609270001  -> ORD202609270001
 *   OPM0001           -> OPM{tanggal opname}0001
 *
 * Nomor lama wajib ikut ditulis ulang, bukan hanya generatornya: pencarian nomor berikutnya memakai
 * prefiks bertanggal, jadi data campuran dua format akan membuat urutan berikutnya salah hitung.
 *
 * Opname beda sendiri karena nomor lamanya tidak memuat tanggal. Tanggalnya diambil dari kolom
 * `opname_date` tiap baris, lalu urutannya dinomori ulang per hari mengikuti urutan nomor lama,
 * sehingga urutan kronologisnya tetap.
 *
 * Penjualan bertanda [SIMULASI] ikut ditanggal ulang. Seeder simulasi dulu memberi nomor bertanggal
 * hari seeder dijalankan, bukan tanggal penjualannya, sehingga seluruh penjualan demo bernomor satu
 * hari yang sama padahal tersebar berbulan-bulan. Yang berubah hanya string nomornya: jumlah, harga,
 * tanggal, kartu stok, C2, dan peringkat SAW tidak tersentuh. Penjualan yang diinput lewat form
 * **tidak** ikut, karena di sana nomor memang sengaja mengikuti tanggal entri (lihat
 * OrderForm::generateOrderCode).
 *
 * Baris yang sudah soft-deleted ikut ditulis ulang (query builder, bukan model): nomornya tetap
 * terpakai dan tidak boleh tabrakan dengan nomor baru.
 *
 * Nomor data demo sintetis (`RO-SPK-0001`, `ORD-SPK-00001` dari SpkTestDataSeeder) sengaja
 * dibiarkan: polanya tidak pernah cocok dengan pencarian prefiks bertanggal, jadi tidak mengganggu.
 *
 * down() sengaja kosong, mengikuti migrasi revisi lain: pemulihan lewat backup DB.
 */
return new class extends Migration
{
    /** Awalan catatan yang dipakai seeder simulasi sampai 2026-09-28; data lama masih memuatnya. */
    private const PENANDA_SIMULASI = '[SIMULASI]';

    public function up(): void
    {
        DB::transaction(function () {
            $this->dropSeparator('purchase_orders', 'po_number', PurchaseOrder::CODE_PREFIX);
            $this->dropSeparator('receive_orders', 'receive_order_number', ReceiveOrder::CODE_PREFIX);
            $this->dropSeparator('orders', 'order_code', Order::CODE_PREFIX);
            $this->redateSimulatedSales();
            $this->renumberOpnames();
        });
    }

    public function down(): void
    {
        // Pemulihan lewat backup DB.
    }

    /**
     * Buang pemisah pada nomor bertanggal, di mana pun letaknya: PO20260916-0001 dan
     * ORD-202609270001 sama-sama menjadi prefiks + 8 digit tanggal + urutan.
     */
    private function dropSeparator(string $table, string $column, string $prefix): void
    {
        $rows = DB::table($table)
            ->select('id', $column)
            ->where($column, 'like', $prefix.'%')
            ->orderBy('id')
            ->get();

        foreach ($rows as $row) {
            $lama = (string) $row->{$column};

            if (! preg_match('/^'.preg_quote($prefix, '/').'\D?(\d{8})\D?(\d+)$/', $lama, $cocok)) {
                continue;
            }

            $baru = $prefix.$cocok[1].DocumentNumber::pad((int) $cocok[2]);

            if ($baru !== $lama) {
                DB::table($table)->where('id', $row->id)->update([$column => $baru]);
            }
        }
    }

    /**
     * Nomor penjualan simulasi ditanggal ulang mengikuti `order_date`-nya sendiri.
     *
     * Dua fase karena `order_code` unik: nomor tujuan sebuah baris bisa jadi masih dipegang baris
     * lain yang belum sempat diubah, sehingga semua baris yang terdampak dipindahkan dulu ke nomor
     * sementara. Urutan per hari melanjutkan nomor tertinggi yang sudah dipakai penjualan lain di
     * hari itu, supaya penjualan riil tidak pernah tertabrak.
     *
     * Penanda yang dipakai adalah awalan `[SIMULASI]`, bukan seluruh kalimat catatan: kalimatnya
     * pernah berubah (em dash dibersihkan di commit e4e953a) sehingga baris lama menyimpan teks yang
     * tidak lagi sama dengan konstanta di kode, dan pencocokan persis tidak menemukan apa pun.
     */
    private function redateSimulatedSales(): void
    {
        $rows = DB::table('orders')
            ->select('id', 'order_date')
            ->where('note', 'like', self::PENANDA_SIMULASI.'%')
            ->orderBy('id')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        foreach ($rows as $row) {
            DB::table('orders')->where('id', $row->id)->update(['order_code' => 'TMP'.$row->id]);
        }

        $urutanPerHari = [];

        foreach ($rows as $row) {
            $head = DocumentNumber::head(Order::CODE_PREFIX, Carbon::parse($row->order_date));

            if (! array_key_exists($head, $urutanPerHari)) {
                $lain = DB::table('orders')
                    ->where('order_code', 'like', $head.'%')
                    ->orderByDesc('order_code')
                    ->value('order_code');

                $urutanPerHari[$head] = $lain ? (int) substr($lain, strlen($head)) : 0;
            }

            $urutanPerHari[$head]++;

            DB::table('orders')
                ->where('id', $row->id)
                ->update(['order_code' => $head.DocumentNumber::pad($urutanPerHari[$head])]);
        }
    }

    /** OPM0001 menjadi OPM{YYYYMMDD}{XXXX}, dinomori ulang per tanggal opname. */
    private function renumberOpnames(): void
    {
        $rows = DB::table('medicine_stock_opnames')
            ->select('id', 'opname_number', 'opname_date')
            ->where('opname_number', 'like', MedicineStockOpname::CODE_PREFIX.'%')
            ->get()
            ->filter(fn ($row) => (bool) preg_match('/^'.MedicineStockOpname::CODE_PREFIX.'\d+$/', (string) $row->opname_number))
            // Urut menurut angka nomor lama supaya kronologinya terjaga saat dinomori ulang.
            ->sortBy(fn ($row) => (int) substr((string) $row->opname_number, strlen(MedicineStockOpname::CODE_PREFIX)))
            ->values();

        $urutanPerHari = [];

        foreach ($rows as $row) {
            $head = DocumentNumber::head(MedicineStockOpname::CODE_PREFIX, Carbon::parse($row->opname_date));
            $urutanPerHari[$head] = ($urutanPerHari[$head] ?? 0) + 1;

            DB::table('medicine_stock_opnames')
                ->where('id', $row->id)
                ->update(['opname_number' => $head.DocumentNumber::pad($urutanPerHari[$head])]);
        }
    }
};
