<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\LaporanSeragam;
use App\Models\MedicineStockOpnameItem;
use App\Support\Tanggal;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Riwayat penyesuaian stok opname beserta nilai selisihnya (K7).
 *
 * Berguna sebagai bukti pengendalian: berapa sering fisik berbeda dari sistem, pada batch mana, dan
 * berapa nilainya. Satu baris per penyesuaian batch, bukan per dokumen opname, karena satu opname
 * bisa menyesuaikan banyak batch dengan arah yang berbeda.
 *
 * Nilai dihitung bertanda: penambahan (D) positif, pengurangan (C) negatif, memakai HPP yang
 * tersimpan pada baris itu.
 *
 * Bentuk halamannya sama dengan Rekap dan Fast/Slow Moving: filter, tiga aksi header, ringkasan,
 * lalu tabel (`LaporanSeragam`).
 */
class LaporanOpname extends Page implements HasSchemas
{
    use HasPageShield;
    use InteractsWithSchemas;
    use LaporanSeragam;

    protected string $view = 'filament.pages.laporan-opname';

    protected static ?string $title = 'Laporan Hasil Stok Opname';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public Collection $rows;

    public function mount(): void
    {
        $this->data = [
            // Opname dilakukan sesekali, bukan harian. Rentang bawaan 30 hari seperti laporan lain
            // akan hampir selalu kosong, jadi bawaannya tahun berjalan.
            'period_start' => now()->startOfYear()->toDateString(),
            'period_end' => now()->toDateString(),
            'arah' => 'semua',
        ];
        $this->rows = collect();
        $this->generate();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter Periode')
                    ->description('Pilih rentang tanggal opname lalu klik "Tampilkan Laporan" di header.')
                    ->columns(3)
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
                        Select::make('arah')
                            ->label('Arah')
                            ->options([
                                'semua' => 'Lebih + Kurang',
                                'D' => 'Lebih dari sistem',
                                'C' => 'Kurang dari sistem',
                            ])
                            ->required()
                            ->native(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function generate(): void
    {
        $this->rows = $this->query()->get()->map(fn (MedicineStockOpnameItem $i): array => [
            'nomor' => $i->medicineStockOpname?->opname_number,
            'tanggal' => $i->medicineStockOpname?->opname_date,
            'obat' => $i->medicine?->name,
            'batch' => $i->batch_number ?: ($i->layer?->batch_number ?: 'tanpa nomor'),
            'arah' => $i->type_account,
            'qty' => (int) $i->qty,
            'nilai' => ($i->type_account === 'C' ? -1 : 1) * $i->qty * (float) $i->hpp,
            'note' => $i->note,
        ]);
    }

    private function query(): Builder
    {
        [$mulai, $sampai] = $this->periode();

        $query = MedicineStockOpnameItem::query()
            ->with(['medicine', 'layer', 'medicineStockOpname'])
            ->whereHas('medicineStockOpname', fn ($q) => $q
                ->whereDate('opname_date', '>=', $mulai->toDateString())
                ->whereDate('opname_date', '<=', $sampai->toDateString()))
            ->orderByDesc('id');

        $arah = (string) ($this->data['arah'] ?? 'semua');

        return in_array($arah, ['D', 'C'], true)
            ? $query->where('type_account', $arah)
            : $query;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periode(): array
    {
        return [
            Carbon::parse($this->data['period_start'] ?? now()->startOfYear())->startOfDay(),
            Carbon::parse($this->data['period_end'] ?? now())->startOfDay(),
        ];
    }

    public function judulLaporan(): string
    {
        return 'Laporan Hasil Stok Opname';
    }

    public function labelPeriode(): string
    {
        [$mulai, $sampai] = $this->periode();

        return $mulai->translatedFormat(Tanggal::TAMPIL).' s/d '.$sampai->translatedFormat(Tanggal::TAMPIL);
    }

    public function ringkasan(): array
    {
        $lebih = $this->rows->where('arah', 'D');
        $kurang = $this->rows->where('arah', 'C');

        return [
            'Dokumen Opname' => number_format($this->rows->pluck('nomor')->unique()->count(), 0, ',', '.'),
            'Baris Penyesuaian' => number_format($this->rows->count(), 0, ',', '.'),
            'Nilai Lebih' => 'Rp '.number_format($lebih->sum('nilai'), 0, ',', '.'),
            'Nilai Kurang' => 'Rp '.number_format(abs($kurang->sum('nilai')), 0, ',', '.'),
        ];
    }

    public function kolomEkspor(): array
    {
        return ['Nomor Opname', 'Tanggal', 'Obat', 'Batch', 'Arah', 'Selisih', 'Nilai Selisih', 'Keterangan'];
    }

    public function barisEkspor(): iterable
    {
        return $this->rows->map(fn (array $r): array => [
            $r['nomor'],
            $r['tanggal']?->translatedFormat(Tanggal::TAMPIL),
            $r['obat'],
            $r['batch'],
            $r['arah'] === 'D' ? 'Lebih' : 'Kurang',
            $r['qty'],
            round($r['nilai']),
            $r['note'],
        ]);
    }

    public function namaBerkas(): string
    {
        return 'laporan-opname';
    }
}
