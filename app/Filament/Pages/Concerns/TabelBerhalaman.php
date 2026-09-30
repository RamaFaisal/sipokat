<?php

namespace App\Filament\Pages\Concerns;

use App\Support\PaginatorRingkas;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\WithPagination;

/**
 * Paginasi untuk tabel laporan yang datanya sudah ada di memori.
 *
 * Tabel laporan bukan query Eloquent melainkan `Collection` hasil hitungan `generate()`, sehingga
 * paginasi Filament yang biasa (`InteractsWithTable`) tidak bisa dipakai. Yang di sini memotong
 * koleksi itu dan membungkusnya jadi paginator, supaya tombol halaman dan pemilih jumlah baris
 * bawaan Filament bisa dipakai apa adanya.
 *
 * Ekspor Excel dan PDF tetap memakai `$rows` utuh, bukan potongan halaman: yang dibatasi hanya apa
 * yang dirender ke layar.
 */
trait TabelBerhalaman
{
    use WithPagination;

    /** Jumlah baris per halaman. Nilai "all" menampilkan seluruhnya. */
    public int|string $perPage = 10;

    /**
     * Kata kunci pencarian tabel.
     *
     * Properti biasa, bukan bagian dari `$data` milik form filter, karena sifatnya berbeda: filter
     * menentukan **isi** laporan (ikut ke ringkasan dan ekspor), pencarian hanya menyaring **yang
     * terlihat**. Dengan begitu ringkasan tidak ikut berubah saat mengetik, dan ekspor tetap
     * menulis seluruh baris periode itu.
     */
    public string $pencarian = '';

    /**
     * Kunci baris yang ikut dicocokkan dengan kata kunci.
     *
     * @return array<int, string>
     */
    abstract public function kolomPencarian(): array;

    /** Baris yang lolos pencarian; tanpa kata kunci, seluruh baris. */
    public function barisTersaring(): Collection
    {
        $baris = $this->rows instanceof Collection ? $this->rows : collect($this->rows);
        $kata = mb_strtolower(trim($this->pencarian));

        if ($kata === '') {
            return $baris->values();
        }

        $kolom = $this->kolomPencarian();

        return $baris
            ->filter(fn (array $isi): bool => collect($kolom)->contains(
                fn (string $kunci): bool => str_contains(mb_strtolower((string) ($isi[$kunci] ?? '')), $kata),
            ))
            ->values();
    }

    public function updatedPencarian(): void
    {
        $this->resetPage();
    }

    /** @return array<int, int|string> */
    public function pilihanPerHalaman(): array
    {
        return [10, 25, 50, 'all'];
    }

    /**
     * Baris pada halaman yang sedang dibuka.
     *
     * Nomor halaman dijepit ke halaman terakhir yang ada. Tanpa itu, laporan yang menyusut karena
     * filter baru bisa berhenti di halaman yang sudah tidak ada isinya, dan tabelnya tampak kosong
     * padahal datanya ada.
     */
    public function halaman(): LengthAwarePaginator
    {
        $baris = $this->barisTersaring();

        $perHalaman = $this->perPage === 'all'
            ? max(1, $baris->count())
            : max(1, (int) $this->perPage);

        $halamanTerakhir = max(1, (int) ceil($baris->count() / $perHalaman));
        $halaman = min(max(1, (int) $this->getPage()), $halamanTerakhir);

        // PaginatorRingkas, bukan paginator biasa: barisan nomor halamannya dipendekkan sendiri.
        return new PaginatorRingkas(
            $baris->forPage($halaman, $perHalaman)->values(),
            $baris->count(),
            $perHalaman,
            $halaman,
            ['path' => PaginatorRingkas::resolveCurrentPath()],
        );
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }
}
