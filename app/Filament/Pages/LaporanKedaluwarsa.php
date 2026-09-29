<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\LaporanSeragam;
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

    protected string $view = 'filament.pages.laporan-kedaluwarsa';

    protected static ?string $title = 'Laporan Obat Akan Kedaluwarsa';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public Collection $rows;

    public function mount(): void
    {
        $this->data = [
            'horizon' => AmbangEd::PANTAU,
            'tingkat' => 'semua',
        ];
        $this->rows = collect();
        $this->generate();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter')
                    ->description('Pilih rentang pantauan lalu klik "Tampilkan Laporan" di header.')
                    ->columns(2)
                    ->schema([
                        Select::make('horizon')
                            ->label('Kedaluwarsa sampai')
                            ->options([
                                AmbangEd::MENDESAK => AmbangEd::MENDESAK.' hari ke depan',
                                AmbangEd::WASPADA => AmbangEd::WASPADA.' hari ke depan',
                                AmbangEd::PANTAU => AmbangEd::PANTAU.' hari ke depan',
                                180 => '180 hari ke depan',
                                365 => '365 hari ke depan',
                            ])
                            ->required()
                            ->native(false),
                        Select::make('tingkat')
                            ->label('Tingkat')
                            ->options([
                                'semua' => 'Semua tingkat',
                                'lewat' => 'Sudah kedaluwarsa',
                                'mendesak' => 'Mendesak (≤ '.AmbangEd::MENDESAK.' hari)',
                                'waspada' => 'Waspada ('.(AmbangEd::MENDESAK + 1).' sampai '.AmbangEd::WASPADA.' hari)',
                            ])
                            ->required()
                            ->native(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
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
        $hariIni = today();

        $query = BatchBersisa::query()
            ->whereDate('expired_date', '<=', $hariIni->copy()->addDays($this->horizon())->toDateString())
            ->with(['medicine.unit'])
            ->orderBy('expired_date');

        return match ($this->tingkat()) {
            'lewat' => $query->whereDate('expired_date', '<=', $hariIni->toDateString()),
            'mendesak' => $query->whereDate('expired_date', '<=', $hariIni->copy()->addDays(AmbangEd::MENDESAK)->toDateString()),
            'waspada' => $query
                ->whereDate('expired_date', '>', $hariIni->copy()->addDays(AmbangEd::MENDESAK)->toDateString())
                ->whereDate('expired_date', '<=', $hariIni->copy()->addDays(AmbangEd::WASPADA)->toDateString()),
            default => $query,
        };
    }

    private function horizon(): int
    {
        return (int) ($this->data['horizon'] ?? AmbangEd::PANTAU);
    }

    private function tingkat(): string
    {
        return (string) ($this->data['tingkat'] ?? 'semua');
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
        $tingkat = match ($this->tingkat()) {
            'lewat' => 'sudah kedaluwarsa',
            'mendesak' => 'mendesak',
            'waspada' => 'waspada',
            default => 'semua tingkat',
        };

        return 'Sampai '.$this->horizon().' hari ke depan ('.$tingkat.'), per '
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
