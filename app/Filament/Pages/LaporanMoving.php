<?php

namespace App\Filament\Pages;

use App\Models\Medicine;
use App\Models\OrderItem;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
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

class LaporanMoving extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected string $view = 'filament.pages.laporan-moving';

    protected static ?string $title = 'Laporan Fast/Slow Moving';

    protected static ?string $navigationLabel = 'Fast / Slow Moving';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowsRightLeft;

    protected static string|\UnitEnum|null $navigationGroup = 'Laporan';

    protected static ?int $navigationSort = 2;

    public ?array $data = [];

    public Collection $fastMoving;

    public Collection $slowMoving;

    public Collection $deadStock;

    public ?array $meta = null;

    public function mount(): void
    {
        $this->data = [
            'period_start' => now()->subDays(89)->toDateString(),
            'period_end' => now()->toDateString(),
            'top_n' => 20,
        ];
        $this->fastMoving = collect();
        $this->slowMoving = collect();
        $this->deadStock = collect();
        $this->generate();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Filter Periode')
                    ->description('Periode analisis penjualan obat. Default 90 hari ke belakang.')
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
                        TextInput::make('top_n')
                            ->label('Tampilkan Top N')
                            ->numeric()
                            ->default(20)
                            ->minValue(5)
                            ->maxValue(100)
                            ->required()
                            ->helperText('Jumlah obat tertinggi di tiap kategori (fast / slow / dead stock).'),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Analisis Sekarang')
                ->icon(Heroicon::OutlinedPlay)
                ->color('primary')
                ->action('generate'),
            Action::make('export')
                ->label('Export Excel')
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->color('success')
                ->action('exportExcel')
                ->visible(fn () => $this->fastMoving->isNotEmpty() || $this->slowMoving->isNotEmpty()),
        ];
    }

    public function generate(): void
    {
        $data = $this->form->getState();
        $start = Carbon::parse($data['period_start'])->startOfDay();
        $end = Carbon::parse($data['period_end'])->endOfDay();
        $topN = (int) $data['top_n'];

        $days = max(1, $start->diffInDays($end) + 1);

        // Aggregate semua medicines aktif dengan penjualan dalam periode
        $salesAgg = OrderItem::query()
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

        $allMedicines = Medicine::query()
            ->where('status', 'active')
            ->with('category:id,name', 'unit:id,name,alias')
            ->get(['id', 'code', 'name', 'category_id', 'unit_id']);

        $rows = $allMedicines->map(function (Medicine $m) use ($salesAgg, $days) {
            $sale = $salesAgg[$m->id] ?? null;
            $totalQty = (int) ($sale->total_qty ?? 0);
            $monthlyAvg = (int) round(($totalQty / $days) * 30);

            return [
                'medicine_id' => $m->id,
                'code' => $m->code,
                'name' => $m->name,
                'category' => $m->category?->name ?? '-',
                'unit' => $m->unit?->alias ?? $m->unit?->name ?? '-',
                'current_stock' => $m->currentStock(),
                'total_qty' => $totalQty,
                'monthly_avg' => $monthlyAvg,
                'total_value' => (float) ($sale->total_value ?? 0),
                'transaksi' => (int) ($sale->transaksi ?? 0),
            ];
        });

        // Fast moving: top N by monthly_avg (descending) yang punya transaksi
        $this->fastMoving = $rows
            ->where('total_qty', '>', 0)
            ->sortByDesc('monthly_avg')
            ->take($topN)
            ->values();

        // Slow moving: yang ada transaksi tapi sedikit (≤ 20/bln per Tabel 3.6 PDF = score 1)
        $this->slowMoving = $rows
            ->where('total_qty', '>', 0)
            ->filter(fn ($r) => $r['monthly_avg'] < 20)
            ->sortBy('monthly_avg')
            ->take($topN)
            ->values();

        // Dead stock: tidak ada transaksi sama sekali dalam periode
        $this->deadStock = $rows
            ->where('total_qty', 0)
            ->sortByDesc('current_stock')
            ->take($topN)
            ->values();

        $this->meta = [
            'periode' => $start->format('d M Y') . ' s/d ' . $end->format('d M Y'),
            'days' => $days,
            'total_obat_aktif' => $allMedicines->count(),
            'obat_dengan_transaksi' => $rows->where('total_qty', '>', 0)->count(),
            'obat_tanpa_transaksi' => $rows->where('total_qty', 0)->count(),
        ];
    }

    public function exportExcel()
    {
        if ($this->fastMoving->isEmpty() && $this->slowMoving->isEmpty() && $this->deadStock->isEmpty()) {
            return;
        }

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);

        foreach ([
            'Fast Moving' => $this->fastMoving,
            'Slow Moving' => $this->slowMoving,
            'Dead Stock' => $this->deadStock,
        ] as $sheetName => $rows) {
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);

            $sheet->setCellValue('A1', strtoupper($sheetName) . ' — ' . $this->meta['periode']);
            $sheet->mergeCells('A1:H1');
            $sheet->getStyle('A1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 13],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ]);

            $headers = ['Kode', 'Nama Obat', 'Kategori', 'Satuan', 'Stok Saat Ini', 'Qty Terjual', 'Demand/Bulan', 'Nilai Penjualan (Rp)'];
            $sheet->fromArray($headers, null, 'A3');
            $sheet->getStyle('A3:H3')->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1F4E78']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
            ]);

            $row = 4;
            foreach ($rows as $r) {
                $sheet->setCellValue("A{$row}", $r['code']);
                $sheet->setCellValue("B{$row}", $r['name']);
                $sheet->setCellValue("C{$row}", $r['category']);
                $sheet->setCellValue("D{$row}", $r['unit']);
                $sheet->setCellValue("E{$row}", $r['current_stock']);
                $sheet->setCellValue("F{$row}", $r['total_qty']);
                $sheet->setCellValue("G{$row}", $r['monthly_avg']);
                $sheet->setCellValue("H{$row}", $r['total_value']);
                $row++;
            }

            if ($row > 4) {
                $sheet->getStyle("A3:H" . ($row - 1))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("H4:H" . ($row - 1))->getNumberFormat()->setFormatCode('#,##0');
            }

            foreach (range('A', 'H') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'fast_slow_moving_' . now()->format('Ymd_His') . '.xlsx');
    }
}
