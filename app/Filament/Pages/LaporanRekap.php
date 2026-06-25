<?php

namespace App\Filament\Pages;

use App\Models\Medicine;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ReceiveOrder;
use App\Models\ReceiveOrderItem;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.laporan-rekap';

    protected static ?string $title = 'Laporan Rekap Penjualan & Pembelian';

    protected static ?string $navigationLabel = 'Rekap Penjualan & Pembelian';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

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
                        Select::make('tipe')
                            ->label('Tipe Laporan')
                            ->options([
                                'keduanya' => 'Penjualan + Pembelian',
                                'penjualan' => 'Penjualan Saja',
                                'pembelian' => 'Pembelian Saja',
                            ])
                            ->required()
                            ->native(false),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Tampilkan Laporan')
                ->icon(Heroicon::OutlinedPlay)
                ->color('primary')
                ->action('generate'),
            Action::make('export')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('success')
                ->action('exportExcel')
                ->visible(fn () => $this->rows->isNotEmpty()),
        ];
    }

    public function generate(): void
    {
        $data = $this->form->getState();
        $start = Carbon::parse($data['period_start'])->startOfDay();
        $end = Carbon::parse($data['period_end'])->endOfDay();
        $tipe = $data['tipe'];

        $sales = collect();
        $purchases = collect();

        if (in_array($tipe, ['keduanya', 'penjualan'])) {
            $sales = OrderItem::query()
                ->whereHas('order', fn ($q) => $q
                    ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
                    ->where('status', '!=', 'cancelled'))
                ->select(
                    'medicine_id',
                    DB::raw('SUM(qty) as total_qty'),
                    DB::raw('SUM(total) as total_value'),
                    DB::raw('COUNT(DISTINCT order_id) as transaksi'),
                )
                ->groupBy('medicine_id')
                ->get()
                ->keyBy('medicine_id');
        }

        if (in_array($tipe, ['keduanya', 'pembelian'])) {
            $purchases = ReceiveOrderItem::query()
                ->whereHas('receiveOrder', fn ($q) => $q
                    ->whereBetween('receive_date', [$start->toDateString(), $end->toDateString()]))
                ->select(
                    'medicine_id',
                    DB::raw('SUM(qty) as total_qty'),
                    DB::raw('SUM(qty * price) as total_value'),
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
            if (! $m) return null;

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
        })->filter()->sortByDesc('jual_nilai')->values();

        $this->summary = collect([
            'total_jual' => $this->rows->sum('jual_nilai'),
            'total_beli' => $this->rows->sum('beli_nilai'),
            'total_jual_qty' => $this->rows->sum('jual_qty'),
            'total_beli_qty' => $this->rows->sum('beli_qty'),
            'jumlah_transaksi_jual' => Order::whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', '!=', 'cancelled')->count(),
            'jumlah_transaksi_beli' => ReceiveOrder::whereBetween('receive_date', [$start->toDateString(), $end->toDateString()])->count(),
            'margin_kotor' => $this->rows->sum('margin_kotor'),
            'periode' => $start->format('d M Y') . ' s/d ' . $end->format('d M Y'),
            'tipe' => $tipe,
        ]);
    }

    public function exportExcel()
    {
        if ($this->rows->isEmpty()) {
            return;
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Penjualan & Pembelian');

        // Header info
        $sheet->setCellValue('A1', 'LAPORAN REKAP PENJUALAN & PEMBELIAN');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
        $sheet->setCellValue('A2', 'Periode: ' . $this->summary['periode']);
        $sheet->mergeCells('A2:J2');
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Column headers
        $headers = ['Kode', 'Nama Obat', 'Kategori', 'Qty Beli', 'Nilai Beli (Rp)', 'Trans. Beli', 'Qty Jual', 'Nilai Jual (Rp)', 'Trans. Jual', 'Margin Kotor (Rp)'];
        $sheet->fromArray($headers, null, 'A4');
        $sheet->getStyle('A4:J4')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E78']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Data rows
        $row = 5;
        foreach ($this->rows as $r) {
            $sheet->setCellValue("A{$row}", $r['code']);
            $sheet->setCellValue("B{$row}", $r['name']);
            $sheet->setCellValue("C{$row}", $r['category']);
            $sheet->setCellValue("D{$row}", $r['beli_qty']);
            $sheet->setCellValue("E{$row}", $r['beli_nilai']);
            $sheet->setCellValue("F{$row}", $r['beli_transaksi']);
            $sheet->setCellValue("G{$row}", $r['jual_qty']);
            $sheet->setCellValue("H{$row}", $r['jual_nilai']);
            $sheet->setCellValue("I{$row}", $r['jual_transaksi']);
            $sheet->setCellValue("J{$row}", $r['margin_kotor']);
            $row++;
        }

        // Total row
        $sheet->setCellValue("A{$row}", 'TOTAL');
        $sheet->mergeCells("A{$row}:C{$row}");
        $sheet->setCellValue("D{$row}", $this->summary['total_beli_qty']);
        $sheet->setCellValue("E{$row}", $this->summary['total_beli']);
        $sheet->setCellValue("F{$row}", $this->summary['jumlah_transaksi_beli']);
        $sheet->setCellValue("G{$row}", $this->summary['total_jual_qty']);
        $sheet->setCellValue("H{$row}", $this->summary['total_jual']);
        $sheet->setCellValue("I{$row}", $this->summary['jumlah_transaksi_jual']);
        $sheet->setCellValue("J{$row}", $this->summary['margin_kotor']);
        $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFE699']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);

        // Borders all
        $sheet->getStyle("A4:J{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("E5:E{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("H5:H{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $sheet->getStyle("J5:J{$row}")->getNumberFormat()->setFormatCode('#,##0');

        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'rekap_penjualan_pembelian_' . now()->format('Ymd_His') . '.xlsx');
    }
}
