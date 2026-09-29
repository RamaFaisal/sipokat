<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\LaporanSeragam;
use App\Models\ReceiveOrderItem;
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
 * Nilai pembelian dikelompokkan per PBF dalam satu periode (K7).
 *
 * Menjawab pertanyaan yang tidak terjawab menu mana pun: PBF mana yang paling banyak dipakai dan
 * berapa nilainya. Dihitung dari item penerimaan (harga sudah termasuk PPN, R13), bukan dari PO,
 * karena yang benar-benar dibeli adalah yang diterima.
 *
 * Bentuk halamannya sama dengan Rekap dan Fast/Slow Moving: filter, tiga aksi header, ringkasan,
 * lalu tabel (`LaporanSeragam`).
 */
class LaporanPembelianPbf extends Page implements HasSchemas
{
    use HasPageShield;
    use InteractsWithSchemas;
    use LaporanSeragam;

    protected string $view = 'filament.pages.laporan-pembelian-pbf';

    protected static ?string $title = 'Laporan Pembelian per PBF';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static bool $shouldRegisterNavigation = false;

    public ?array $data = [];

    public Collection $rows;

    public function mount(): void
    {
        $this->data = [
            'period_start' => now()->subDays(29)->toDateString(),
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

        $this->rows = ReceiveOrderItem::query()
            ->join('receive_orders', 'receive_orders.id', '=', 'receive_order_items.receive_order_id')
            ->join('suppliers', 'suppliers.id', '=', 'receive_orders.supplier_id')
            ->whereNull('receive_orders.deleted_at')
            ->whereNull('receive_order_items.deleted_at')
            ->whereDate('receive_orders.receive_date', '>=', $mulai->toDateString())
            ->whereDate('receive_orders.receive_date', '<=', $sampai->toDateString())
            ->groupBy('suppliers.id', 'suppliers.code', 'suppliers.name')
            ->selectRaw('suppliers.code as kode, suppliers.name as nama')
            ->selectRaw('count(distinct receive_orders.id) as faktur')
            ->selectRaw('count(distinct receive_order_items.medicine_id) as ragam_obat')
            ->selectRaw('sum(receive_order_items.qty) as jumlah')
            ->selectRaw('sum(receive_order_items.qty * receive_order_items.price) as nilai')
            ->orderByDesc('nilai')
            ->get()
            ->map(fn ($r): array => [
                'kode' => $r->kode,
                'nama' => $r->nama,
                'faktur' => (int) $r->faktur,
                'ragam_obat' => (int) $r->ragam_obat,
                'jumlah' => (int) $r->jumlah,
                'nilai' => (float) $r->nilai,
            ]);
    }

    public function totalNilai(): float
    {
        return (float) $this->rows->sum('nilai');
    }

    /** Porsi tiap PBF terhadap total pembelian periode itu. */
    public function porsi(float $nilai): float
    {
        $total = $this->totalNilai();

        return $total > 0 ? round($nilai / $total * 100, 1) : 0.0;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function periode(): array
    {
        return [
            Carbon::parse($this->data['period_start'] ?? now()->subDays(29))->startOfDay(),
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
        return 'Laporan Pembelian per PBF';
    }

    public function ringkasan(): array
    {
        $terbesar = $this->rows->first();

        return [
            'Jumlah PBF' => number_format($this->rows->count(), 0, ',', '.'),
            'Jumlah Faktur' => number_format($this->rows->sum('faktur'), 0, ',', '.'),
            'Nilai Pembelian' => 'Rp '.number_format($this->totalNilai(), 0, ',', '.'),
            'PBF Terbesar' => $terbesar === null
                ? '-'
                : $terbesar['kode'].' ('.number_format($this->porsi($terbesar['nilai']), 1, ',', '.').'%)',
        ];
    }

    public function kolomEkspor(): array
    {
        return ['Kode PBF', 'Nama PBF', 'Jumlah Faktur', 'Ragam Obat', 'Jumlah Satuan', 'Nilai Pembelian', 'Porsi (%)'];
    }

    public function barisEkspor(): iterable
    {
        return $this->rows->map(fn (array $r): array => [
            $r['kode'], $r['nama'], $r['faktur'], $r['ragam_obat'], $r['jumlah'], round($r['nilai']), $this->porsi($r['nilai']),
        ]);
    }

    public function namaBerkas(): string
    {
        return 'laporan-pembelian-pbf';
    }
}
