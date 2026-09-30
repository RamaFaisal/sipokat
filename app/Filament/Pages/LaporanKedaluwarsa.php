<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\LaporanSeragam;
use App\Filament\Pages\Concerns\TabelBerhalaman;
use App\Models\MedicineStock;
use App\Support\AmbangEd;
use App\Support\BatchBersisa;
use App\Support\Tanggal;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Batch yang mendekati kedaluwarsa beserta nilai rupiah yang terancam (K7).
 *
 * Per **batch**, bukan per obat: satu obat bisa punya batch aman dan batch mepet sekaligus, dan yang
 * perlu ditindak adalah batch-nya. Nilai terancam memakai HPP baris lapisan itu sendiri, yaitu harga
 * yang benar-benar tertanam di stok tersebut.
 *
 * Bentuk halamannya sama dengan Rekap dan Fast/Slow Moving: filter, tiga aksi header, ringkasan,
 * lalu tabel (`LaporanSeragam`).
 */
class LaporanKedaluwarsa extends Page implements HasSchemas
{
    use HasPageShield;
    use InteractsWithSchemas;
    use LaporanSeragam;
    use TabelBerhalaman;

    protected string $view = 'filament.pages.laporan-kedaluwarsa';

    protected static ?string $title = 'Laporan Obat Akan Kedaluwarsa';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public Collection $rows;

    /** Rentang terjauh yang masih dihitung mundur, sekaligus batas pilihan "lebih dari". */
    private const RENTANG_TERJAUH = 360;

    /** Nilai pilihan "> 360 hari"; bukan angka karena arah bandingnya terbalik. */
    private const RENTANG_JAUH = 'jauh';

    /** Batas bawah tiap golongan sisa stok, dalam satuan jual. */
    private const SISA_SEDIKIT = 10;

    private const SISA_BANYAK = 50;

    public function mount(): void
    {
        $this->data = [
            'horizon' => AmbangEd::PANTAU,
            'sisa' => 'semua',
        ];
        $this->rows = collect();
        $this->generate();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter')
                    ->description('Tabel langsung menyesuaikan begitu filter diubah.')
                    ->columns(2)
                    ->schema([
                        Select::make('horizon')
                            ->label('Kedaluwarsa dalam')
                            ->options([
                                AmbangEd::MENDESAK => '< '.AmbangEd::MENDESAK.' hari',
                                AmbangEd::WASPADA => '< '.AmbangEd::WASPADA.' hari',
                                AmbangEd::PANTAU => '< '.AmbangEd::PANTAU.' hari',
                                180 => '< 180 hari',
                                self::RENTANG_TERJAUH => '< '.self::RENTANG_TERJAUH.' hari',
                                // Kebalikan arah dari pilihan di atasnya: batch yang justru masih
                                // lama, untuk melihat stok yang aman tanpa tercampur yang mepet.
                                self::RENTANG_JAUH => '> '.self::RENTANG_TERJAUH.' hari',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->generate()),
                        Select::make('sisa')
                            ->label('Sisa stok')
                            ->options([
                                'semua' => 'Semua sisa',
                                'sedikit' => 'Sisa sedikit (< '.self::SISA_SEDIKIT.')',
                                'sedang' => 'Sisa sedang ('.self::SISA_SEDIKIT.' sampai '.self::SISA_BANYAK.')',
                                'banyak' => 'Sisa banyak (> '.self::SISA_BANYAK.')',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->generate()),
                    ]),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $this->resetPage();

        $this->rows = $this->query()->get()->map(fn (MedicineStock $b): array => [
            'code' => $b->medicine?->code,
            'name' => $b->medicine?->name,
            'batch' => $b->batch_number ?: 'tanpa nomor',
            'ed' => $b->expired_date,
            'sisa_hari' => BatchBersisa::sisaHari($b),
            'sisa' => (int) $b->sisa,
            'unit' => $b->medicine?->unit?->name ?? '',
            'nilai' => (float) $b->nilai,
        ]);
    }

    /**
     * Satu baris per batch, bukan per lapisan. Batch yang dibeli dua kali tetap satu tumpukan di
     * rak, dan laporan ini menjawab "apa yang perlu ditindak", bukan "dari faktur mana asalnya".
     * Rincian per faktur tetap ada di Kartu Stok.
     */
    private function query(): Builder
    {
        $query = BatchBersisa::query()
            ->with(['medicine.unit'])
            ->orderBy('expired_date');

        $query = $this->rentang() === self::RENTANG_JAUH
            ? $query->whereDate('expired_date', '>', today()->addDays(self::RENTANG_TERJAUH)->toDateString())
            : $query->whereDate('expired_date', '<=', today()->addDays((int) $this->rentang())->toDateString());

        // `sisa` kolom biasa milik tabel turunan BatchBersisa, jadi disaring dengan where biasa.
        return match ($this->golonganSisa()) {
            'sedikit' => $query->where('sisa', '<', self::SISA_SEDIKIT),
            'sedang' => $query->where('sisa', '>=', self::SISA_SEDIKIT)->where('sisa', '<=', self::SISA_BANYAK),
            'banyak' => $query->where('sisa', '>', self::SISA_BANYAK),
            default => $query,
        };
    }

    /** Dibaca sebagai teks karena pilihannya campur angka dan penanda "jauh". */
    private function rentang(): string
    {
        return (string) ($this->data['horizon'] ?? AmbangEd::PANTAU);
    }

    private function golonganSisa(): string
    {
        return (string) ($this->data['sisa'] ?? 'semua');
    }

    /** Warna baris mengikuti ambang yang sama dengan dashboard dan notifikasi harian. */
    public function warna(int $sisaHari): string
    {
        return AmbangEd::warna($sisaHari);
    }

    public function judulLaporan(): string
    {
        return 'Laporan Obat Akan Kedaluwarsa';
    }

    public function labelPeriode(): string
    {
        $sisa = match ($this->golonganSisa()) {
            'sedikit' => 'sisa < '.self::SISA_SEDIKIT,
            'sedang' => 'sisa '.self::SISA_SEDIKIT.' sampai '.self::SISA_BANYAK,
            'banyak' => 'sisa > '.self::SISA_BANYAK,
            default => 'semua sisa',
        };

        $rentang = $this->rentang() === self::RENTANG_JAUH
            ? '> '.self::RENTANG_TERJAUH.' hari'
            : '< '.$this->rentang().' hari';

        return 'Kedaluwarsa '.$rentang.' ('.$sisa.'), per '
            .today()->translatedFormat(Tanggal::TAMPIL);
    }

    public function ringkasan(): array
    {
        $lewat = $this->rows->filter(fn (array $r): bool => $r['sisa_hari'] < 0);
        $mendesak = $this->rows->filter(fn (array $r): bool => $r['sisa_hari'] >= 0 && $r['sisa_hari'] <= AmbangEd::MENDESAK);

        return [
            'Jumlah Batch' => number_format($this->rows->count(), 0, ',', '.'),
            'Sudah Kedaluwarsa' => number_format($lewat->count(), 0, ',', '.').' batch',
            'Mendesak (≤ '.AmbangEd::MENDESAK.' Hari)' => number_format($mendesak->count(), 0, ',', '.').' batch',
            'Nilai Terancam' => 'Rp '.number_format($this->rows->sum('nilai'), 0, ',', '.'),
        ];
    }

    /** @return array<int, string> */
    public function kolomPencarian(): array
    {
        return ['code', 'name', 'batch'];
    }

    public function kolomEkspor(): array
    {
        return ['Kode', 'Nama Obat', 'Batch', 'Kedaluwarsa', 'Sisa Hari', 'Sisa Stok', 'Satuan', 'Nilai Terancam'];
    }

    public function barisEkspor(): iterable
    {
        return $this->rows->map(fn (array $r): array => [
            $r['code'],
            $r['name'],
            $r['batch'],
            $r['ed']->translatedFormat(Tanggal::TAMPIL),
            $r['sisa_hari'],
            $r['sisa'],
            $r['unit'],
            round($r['nilai']),
        ]);
    }

    public function namaBerkas(): string
    {
        return 'laporan-kedaluwarsa';
    }
}
