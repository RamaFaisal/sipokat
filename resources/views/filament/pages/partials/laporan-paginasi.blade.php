{{--
    Kontrol halaman tabel laporan.

    Memakai komponen paginasi bawaan Filament supaya bentuknya sama dengan tabel di menu lain,
    lengkap dengan pemilih jumlah baris. Paginatornya dari trait TabelBerhalaman, jadi yang dipotong
    hanya tampilan; ekspor tetap seluruh baris.
--}}
@if ($paginator->total() > 0)
    <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
        <x-filament::pagination
            :paginator="$paginator"
            :page-options="$this->pilihanPerHalaman()"
            current-page-option-property="perPage"
        />
    </div>
@endif
