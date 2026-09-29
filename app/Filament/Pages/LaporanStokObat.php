<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\LaporanSeragam;
use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Support\Tanggal;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Rekap stok per obat dalam satu periode: stok awal, masuk, keluar, stok akhir (K7).
 *
 * Bentuk ringkas kartu stok, satu baris per obat, untuk dicetak bulanan. Angkanya diturunkan
 * langsung dari kartu stok (`medicine_stocks`), sumber yang sama dengan kartu stok per obat, jadi
 * tidak ada kemungkinan dua laporan menghasilkan angka berbeda.
 *
 * Dihitung dengan tiga agregat, bukan panggilan service per obat: 127 obat berarti 127 panggilan
 * dan tiga di antaranya menyentuh tabel yang sama.
 *
 * Bentuk halamannya sama dengan Rekap dan Fast/Slow Moving: filter, tiga aksi header, ringkasan,
 * lalu tabel (`LaporanSeragam`).
 */
class LaporanStokObat extends Page implements HasSchemas
{
    use HasPageShield;
    use InteractsWithSchemas;
    use LaporanSeragam;

    protected string $view = 'filament.pages.laporan-stok-obat';

    protected static ?string $title = 'Laporan Rekap Stok per Obat';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public Collection $rows;

    public function mount(): void
    {
        $this->data = [
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->toDateString(),
        ];
        $this->rows = collect();
        $this->generate();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Periode')
                    ->columns(2)
                    ->schema([
                        DatePicker::make('period_start')
                            ->label('Mulai')
                            ->required()
                            ->native(false)
                            ->maxDate(now()),
                        DatePicker::make('period_end')
                            ->label('Sampai')
                            ->required()
                            ->native(false)
                            ->maxDate(now())
                            ->afterOrEqual('period_start'),
                    ]),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        [$mulai, $sampai] = $this->periode();

        // Stok awal = seluruh mutasi sebelum periode; masuk/keluar = mutasi di dalam periode.
        $awal = $this->mutasi(null, $mulai->copy()->subDay());
        $masuk = $this->mutasi($mulai, $sampai, 'D');
        $keluar = $this->mutasi($mulai, $sampai, 'C');

        $this->rows = Medicine::query()
            ->where('status', 'active')
            ->with('unit')
            ->orderBy('name')
            ->get()
            ->map(function (Medicine $obat) use ($awal, $masuk, $keluar): array {
                $stokAwal = (int) ($awal[$obat->id] ?? 0);
                $jumlahMasuk = (int) ($masuk[$obat->id] ?? 0);
                $jumlahKeluar = (int) ($keluar[$obat->id] ?? 0);

                return [
                    'code' => $obat->code,
                    'name' => $obat->name,
                    'unit' => $obat->unit?->name ?? '',
                    'awal' => $stokAwal,
                    'masuk' => $jumlahMasuk,
                    'keluar' => $jumlahKeluar,
                    'akhir' => $stokAwal + $jumlahMasuk - $jumlahKeluar,
                ];
            })
            // Obat tanpa mutasi apa pun dan tanpa saldo hanya memanjangkan laporan.
            ->filter(fn (array $r): bool => $r['awal'] !== 0 || $r['masuk'] !== 0 || $r['keluar'] !== 0)
            ->values();
    }

    /**
     * Saldo atau mutasi per obat. Tanpa `$tipe` hasilnya saldo bersih (D dikurangi C).
     *
     * @return array<int, int>
     */
    private function mutasi(?Carbon $mulai, Carbon $sampai, ?string $tipe = null): array
    {
        $query = MedicineStock::query()
            ->whereDate('date', '<=', $sampai->toDateString())
            ->groupBy('medicine_id');

        if ($mulai !== null) {
            $query->whereDate('date', '>=', $mulai->toDateString());
        }

        if ($tipe !== null) {
            return $query->where('type_account', $tipe)
                ->selectRaw('medicine_id, sum(qty) as jumlah')
                ->pluck('jumlah', 'medicine_id')
                ->all();
        }

        return $query
            ->selectRaw("medicine_id, sum(case when type_account = 'D' then qty else -qty end) as jumlah")
            ->pluck('jumlah', 'medicine_id')
            ->all();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periode(): array
    {
        return [
            Carbon::parse($this->data['period_start'] ?? now()->startOfMonth())->startOfDay(),
            Carbon::parse($this->data['period_end'] ?? now())->startOfDay(),
        ];
    }

    public function labelPeriode(): string
    {
        [$mulai, $sampai] = $this->periode();

        return $mulai->translatedFormat(Tanggal::TAMPIL).' s/d '.$sampai->translatedFormat(Tanggal::TAMPIL);
    }

    public function judulLaporan(): string
    {
        return 'Laporan Rekap Stok per Obat';
    }

    public function ringkasan(): array
    {
        return [
            'Obat Bermutasi' => number_format($this->rows->count(), 0, ',', '.'),
            'Total Masuk' => number_format($this->rows->sum('masuk'), 0, ',', '.').' satuan',
            'Total Keluar' => number_format($this->rows->sum('keluar'), 0, ',', '.').' satuan',
            'Stok Akhir' => number_format($this->rows->sum('akhir'), 0, ',', '.').' satuan',
        ];
    }

    public function kolomEkspor(): array
    {
        return ['Kode', 'Nama Obat', 'Satuan', 'Stok Awal', 'Masuk', 'Keluar', 'Stok Akhir'];
    }

    public function barisEkspor(): iterable
    {
        return $this->rows->map(fn (array $r): array => [
            $r['code'], $r['name'], $r['unit'], $r['awal'], $r['masuk'], $r['keluar'], $r['akhir'],
        ]);
    }

    public function namaBerkas(): string
    {
        return 'laporan-stok-obat';
    }
}
