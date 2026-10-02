<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\TabelBerhalaman;
use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderItem;
use App\Support\Tanggal;
use Barryvdh\DomPDF\Facade\Pdf;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LaporanRekap extends Page implements HasSchemas
{
    use HasPageShield;
    use InteractsWithSchemas;
    use TabelBerhalaman;

    protected string $view = 'filament.pages.laporan-rekap';

    protected static ?string $title = 'Laporan Rekap Penjualan & Pembelian';

    protected static ?string $navigationLabel = 'Laporan';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public Collection $summary;

    public Collection $rows;

    public function mount(): void
    {
        $this->data = [
            'period_start' => now()->subDays(29)->toDateString(),
            'period_end' => now()->toDateString(),
            'tipe' => 'keduanya',
        ];
        $this->summary = collect();
        $this->rows = collect();
        $this->generate();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter Periode')
                    ->description('Tabel langsung menyesuaikan begitu filter diubah.')
                    ->columns(3)
                    ->schema([
                        DatePicker::make('period_start')
                            ->label('Mulai')
                            ->required()
                            ->native(false)
                            ->maxDate(now())
                            ->live()
                            ->afterStateUpdated(fn () => $this->generate()),
                        DatePicker::make('period_end')
                            ->label('Sampai')
                            ->required()
                            ->native(false)
                            ->maxDate(now())
                            ->afterOrEqual('period_start')
                            ->live()
                            ->afterStateUpdated(fn () => $this->generate()),
                        Select::make('tipe')
                            ->label('Tipe Laporan')
                            ->options([
                                'keduanya' => 'Penjualan + Pembelian',
                                'penjualan' => 'Penjualan Saja',
                                'pembelian' => 'Pembelian Saja',
                            ])
                            ->required()
                            ->native(false)
                            ->live()
                            ->afterStateUpdated(fn () => $this->generate()),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Muat Ulang')
                ->icon(Heroicon::OutlinedArrowPath)
                ->color('primary')
                ->action('generate'),
            Action::make('export')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('success')
                ->action('exportExcel')
                ->visible(fn () => $this->rows->isNotEmpty()),
            Action::make('exportPdf')
                ->label('Export PDF')
                ->icon(Heroicon::OutlinedDocumentText)
                ->color('danger')
                ->action('exportPdf')
                ->visible(fn () => $this->rows->isNotEmpty()),
        ];
    }

    public function generate(): void
    {
        $this->resetPage();

        $data = $this->form->getState();
        $start = Carbon::parse($data['period_start'])->startOfDay();
        $end = Carbon::parse($data['period_end'])->endOfDay();
        $tipe = $data['tipe'];

        $sales = collect();
        $purchases = collect();

        if (in_array($tipe, ['keduanya', 'penjualan'])) {
            $sales = OrderItem::query()
                ->whereHas('order', fn ($q) => $q
                    ->whereDate('order_date', '>=', $start->toDateString())->whereDate('order_date', '<=', $end->toDateString()))
                ->select(
                    'medicine_id',
                    DB::raw('SUM(qty) as total_qty'),
                    DB::raw('SUM(qty * price) as total_value'),
                    DB::raw('COUNT(DISTINCT order_id) as transaksi'),
                )
                ->groupBy('medicine_id')
                ->get()
                ->keyBy('medicine_id');
        }

        if (in_array($tipe, ['keduanya', 'pembelian'])) {
            $purchases = ReceiveOrderItem::query()
                ->whereHas('receiveOrder', fn ($q) => $q
                    ->whereDate('receive_date', '>=', $start->toDateString())->whereDate('receive_date', '<=', $end->toDateString()))
                ->select(
                    'medicine_id',
                    DB::raw('SUM(qty) as total_qty'),
                    // Nilai dari pack_qty x pack_price (harga faktur), bukan qty x price: isi kemasan
                    // yang tidak habis dibagi membuat price (2 desimal) meleset dari nomor faktur
                    // asli. Baris lama tanpa pack_price dihitung balik dari price.
                    DB::raw('SUM(pack_qty * COALESCE(pack_price, ROUND(price * pack_size))) as total_value'),
                    DB::raw('COUNT(DISTINCT receive_order_id) as transaksi'),
                )
                ->groupBy('medicine_id')
                ->get()
                ->keyBy('medicine_id');
        }

        $medicineIds = $sales->keys()->merge($purchases->keys())->unique();
        $medicines = Medicine::whereIn('id', $medicineIds)
            ->with('category:id,name')
            ->get(['id', 'code', 'name', 'category_id'])
            ->keyBy('id');

        $this->rows = $medicineIds->map(function ($medicineId) use ($medicines, $sales, $purchases) {
            $m = $medicines[$medicineId] ?? null;
            if (! $m) {
                return null;
            }

            $sale = $sales[$medicineId] ?? null;
            $purchase = $purchases[$medicineId] ?? null;

            $saleQty = (int) ($sale->total_qty ?? 0);
            $saleVal = (float) ($sale->total_value ?? 0);
            $buyQty = (int) ($purchase->total_qty ?? 0);
            $buyVal = (float) ($purchase->total_value ?? 0);

            return [
                'code' => $m->code,
                'name' => $m->name,
                'category' => $m->category?->name ?? '-',
                'beli_qty' => $buyQty,
                'beli_nilai' => $buyVal,
                'beli_transaksi' => (int) ($purchase->transaksi ?? 0),
                'jual_qty' => $saleQty,
                'jual_nilai' => $saleVal,
                'jual_transaksi' => (int) ($sale->transaksi ?? 0),
                'margin_kotor' => $saleVal - ($buyVal > 0 && $buyQty > 0 ? ($buyVal / max($buyQty, 1)) * $saleQty : 0),
            ];
        })
            ->filter()
            // Pada "Pembelian Saja" seluruh nilai jual nol, jadi mengurutkannya dengan nilai jual
            // membuat urutan baris praktis acak. Urutan mengikuti sisi yang sedang diminta.
            ->sortByDesc($tipe === 'pembelian' ? 'beli_nilai' : 'jual_nilai')
            ->values();

        $this->summary = collect([
            'total_jual' => $this->rows->sum('jual_nilai'),
            'total_beli' => $this->rows->sum('beli_nilai'),
            'total_jual_qty' => $this->rows->sum('jual_qty'),
            'total_beli_qty' => $this->rows->sum('beli_qty'),
            'jumlah_transaksi_jual' => Order::whereDate('order_date', '>=', $start->toDateString())->whereDate('order_date', '<=', $end->toDateString())->count(),
            'jumlah_transaksi_beli' => ReceiveOrder::whereDate('receive_date', '>=', $start->toDateString())->whereDate('receive_date', '<=', $end->toDateString())->count(),
            'margin_kotor' => $this->rows->sum('margin_kotor'),
            'periode' => $start->translatedFormat(Tanggal::TAMPIL).' s/d '.$end->translatedFormat(Tanggal::TAMPIL),
            'tipe' => $tipe,
        ]);
    }

    /**
     * Tipe laporan yang sedang dipilih.
     *
     * Dibaca dari `$summary`, bukan dari `$data`, supaya kolom yang tampil selalu cocok dengan angka
     * yang sudah dihitung. Kalau diambil dari form, mengubah tipe tanpa menghitung ulang akan
     * menyembunyikan kolom yang isinya masih ada.
     */
    public function tipeLaporan(): string
    {
        return (string) ($this->summary['tipe'] ?? 'keduanya');
    }

    public function tampilBeli(): bool
    {
        return $this->tipeLaporan() !== 'penjualan';
    }

    public function tampilJual(): bool
    {
        return $this->tipeLaporan() !== 'pembelian';
    }

    /** Margin hanya berarti bila kedua sisi ikut dihitung. */
    public function tampilMargin(): bool
    {
        return $this->tipeLaporan() === 'keduanya';
    }

    public function exportPdf()
    {
        if ($this->rows->isEmpty()) {
            return;
        }

        $tipeLabel = match ($this->tipeLaporan()) {
            'penjualan' => 'Penjualan Saja',
            'pembelian' => 'Pembelian Saja',
            default => 'Penjualan + Pembelian',
        };

        $pdf = Pdf::loadView('pdf.laporan-rekap', [
            'rows' => $this->rows,
            'summary' => $this->summary,
            'tipeLabel' => $tipeLabel,
            'tampilBeli' => $this->tampilBeli(),
            'tampilJual' => $this->tampilJual(),
            'tampilMargin' => $this->tampilMargin(),
            'printedAt' => now()->translatedFormat(Tanggal::TAMPIL_JAM),
        ])->setPaper('a4', 'landscape');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'rekap_penjualan_pembelian_'.now()->format('Ymd_His').'.pdf',
        );
    }

    /** @return array<int, string> */
    public function kolomPencarian(): array
    {
        return ['code', 'name', 'category'];
    }

    /**
     * Susunan kolom ekspor menurut tipe laporan.
     *
     * Tiap kolom membawa judul, cara mengambil nilainya dari satu baris, dan angka barisnya di
     * baris TOTAL. Dulu sepuluh kolom ditulis tetap, sehingga "Penjualan Saja" tetap menghasilkan
     * tiga kolom pembelian berisi nol.
     *
     * @return array<int, array{judul: string, nilai: callable, total: int|float|null, uang?: bool}>
     */
    private function kolomEkspor(): array
    {
        $kolom = [
            ['judul' => 'Kode', 'nilai' => fn (array $r) => $r['code'], 'total' => null],
            ['judul' => 'Nama Obat', 'nilai' => fn (array $r) => $r['name'], 'total' => null],
            ['judul' => 'Kategori', 'nilai' => fn (array $r) => $r['category'], 'total' => null],
        ];

        if ($this->tampilBeli()) {
            $kolom[] = ['judul' => 'Qty Beli', 'nilai' => fn (array $r) => $r['beli_qty'], 'total' => $this->summary['total_beli_qty']];
            $kolom[] = ['judul' => 'Nilai Beli (Rp)', 'nilai' => fn (array $r) => $r['beli_nilai'], 'total' => $this->summary['total_beli'], 'uang' => true];
            $kolom[] = ['judul' => 'Trans. Beli', 'nilai' => fn (array $r) => $r['beli_transaksi'], 'total' => $this->summary['jumlah_transaksi_beli']];
        }

        if ($this->tampilJual()) {
            $kolom[] = ['judul' => 'Qty Jual', 'nilai' => fn (array $r) => $r['jual_qty'], 'total' => $this->summary['total_jual_qty']];
            $kolom[] = ['judul' => 'Nilai Jual (Rp)', 'nilai' => fn (array $r) => $r['jual_nilai'], 'total' => $this->summary['total_jual'], 'uang' => true];
            $kolom[] = ['judul' => 'Trans. Jual', 'nilai' => fn (array $r) => $r['jual_transaksi'], 'total' => $this->summary['jumlah_transaksi_jual']];
        }

        if ($this->tampilMargin()) {
            $kolom[] = ['judul' => 'Margin Kotor (Rp)', 'nilai' => fn (array $r) => $r['margin_kotor'], 'total' => $this->summary['margin_kotor'], 'uang' => true];
        }

        return $kolom;
    }

    public function exportExcel()
    {
        if ($this->rows->isEmpty()) {
            return;
        }

        $kolom = $this->kolomEkspor();
        $kolomTerakhir = chr(ord('A') + count($kolom) - 1);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Penjualan & Pembelian');

        // Header info
        $sheet->setCellValue('A1', 'LAPORAN REKAP PENJUALAN & PEMBELIAN');
        $sheet->mergeCells("A1:{$kolomTerakhir}1");
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue('A2', 'Periode: '.$this->summary['periode']);
        $sheet->mergeCells("A2:{$kolomTerakhir}2");
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Column headers
        $sheet->fromArray(array_column($kolom, 'judul'), null, 'A4');
        $sheet->getStyle("A4:{$kolomTerakhir}4")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Data rows
        $row = 5;
        foreach ($this->rows as $r) {
            foreach ($kolom as $i => $definisi) {
                $sheet->setCellValue(chr(ord('A') + $i).$row, ($definisi['nilai'])($r));
            }
            $row++;
        }

        // Total row
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->mergeCells("A{$row}:C{$row}");
        foreach ($kolom as $i => $definisi) {
            if ($definisi['total'] !== null) {
                $sheet->setCellValue(chr(ord('A') + $i).$row, $definisi['total']);
            }
        }
        $sheet->getStyle("A{$row}:{$kolomTerakhir}{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFE699']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Borders all
        $sheet->getStyle("A4:{$kolomTerakhir}{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        foreach ($kolom as $i => $definisi) {
            $huruf = chr(ord('A') + $i);

            if ($definisi['uang'] ?? false) {
                $sheet->getStyle("{$huruf}5:{$huruf}{$row}")->getNumberFormat()->setFormatCode('#,##0');
            }

            $sheet->getColumnDimension($huruf)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'rekap_penjualan_pembelian_'.now()->format('Ymd_His').'.xlsx');
    }
}
