<?php

namespace App\Filament\Pages;

use App\Models\Medicine;
use App\Models\MedicineStock;
use App\Models\ReceiveOrder;
use App\Models\Supplier;
use App\Services\StockCardService;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MedicineStockDetail extends Page implements HasTable
{
    use InteractsWithTable;

    protected string $view = 'filament.pages.medicine-stock-detail';
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $title = 'Kartu Stok Obat Detail';

    public $stocks;

    public $firstStock;

    public $lastStock;

    public $firstDebitStocks;

    public $lastDebitStocks;

    public $firstKreditStocks;

    public $lastKreditStocks;

    public $startDate;

    public $endDate;

    public $record;

    public $recordId;

    public $currentStock;

    public $medicines;

    public $suppliers;

    public $selectedSupplier = 'all';

    public $year;

    public $month;

    public function mount()
    {
        $requestId = request()->query('record');
        if (!$requestId) {
            abort(404, 'Record not found');
        }

        $medicine = Medicine::findOrFail($requestId);

        $supplierByMedicine = ReceiveOrder::whereHas('items', function ($query) use ($medicine) {
            $query->where('medicine_id', $medicine->id);
        })->pluck('supplier_id')->unique();

        $this->medicines = Medicine::where('code', $medicine->code)->get();
        $this->suppliers = Supplier::whereIn('id', $supplierByMedicine)->orderBy('name')->get();

        $this->year  = now()->year;
        $this->month = now()->month;

        $this->loadStockCard();
        $this->applyFilters();
    }

    public function exportAction(): Action
    {
        return Action::make('export')
            ->label('Export to Excel')
            ->icon('heroicon-o-document-arrow-down')
            ->color('success')
            ->action(function () {
                $this->applyFilters();

                $spreadsheet = new Spreadsheet();
                $sheet = $spreadsheet->getActiveSheet();

                $headers = [
                    'Nomor Referensi',
                    'Supplier',
                    'Tanggal Stok',
                    'Refer Table',
                    'Debit',
                    'Kredit',
                    'Stok',
                ];
                $sheet->fromArray($headers, null, 'A1');

                $rowIndex = 2;

                $sheet->setCellValue('A' . $rowIndex, 'Saldo Awal');
                $sheet->setCellValue('G' . $rowIndex, $this->firstStock);
                $sheet->getStyle("A$rowIndex:G$rowIndex")->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => [
                            'argb' => 'FFFFFF00',
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);
                $rowIndex++;

                foreach ($this->stocks as $stock) {
                    $sheet->setCellValue('A' . $rowIndex, $stock['reference_number']);
                    $sheet->setCellValue('B' . $rowIndex, $stock['supplier']);
                    $sheet->setCellValue('C' . $rowIndex, $stock['date']);
                    $sheet->setCellValue('D' . $rowIndex, $stock['refer_table']);
                    $sheet->setCellValue('E' . $rowIndex, $stock['debit']);
                    $sheet->setCellValue('F' . $rowIndex, $stock['credit']);
                    $sheet->setCellValue('G' . $rowIndex, $stock['current_stock']);
                    $rowIndex++;
                }

                $sheet->setCellValue('A' . $rowIndex, 'Saldo Akhir');
                $sheet->setCellValue('G' . $rowIndex, $this->lastStock);
                $sheet->getStyle("A$rowIndex:G$rowIndex")->applyFromArray([
                    'font' => [
                        'bold' => true,
                    ],
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['argb' => 'FF00FF00',],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_RIGHT,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                $sheet->getStyle('A1:G1')->applyFromArray([
                    'font' => ['bold' => true],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                $sheet->getStyle('A2:G' . $rowIndex)->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                ]);

                foreach (range('A', 'G') as $col) {
                    $sheet->getColumnDimension($col)->setAutoSize(true);
                }

                $writer = new Xlsx($spreadsheet);

                return response()->streamDownload(function () use ($writer) {
                    $writer->save('php://output');
                }, 'laporan_stok_' . now()->format('Ymd_His') . '.xlsx');
            });
    }

    public function applyFilters()
    {
        $startDate = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $endDate   = Carbon::create($this->year, $this->month, 1)->endOfMonth();

        $this->recordId = MedicineStock::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->where(function ($q) {
                $q->whereHas('receiveOrder', function ($r) {
                    $r->whereNull('deleted_at');
                })
                    ->orWhereNull('receive_order_id');
            })
            ->when($this->selectedSupplier && $this->selectedSupplier !== 'all', function ($q) {
                $q->whereHas('receiveOrder', function ($r) {
                    $r->where('supplier_id', $this->selectedSupplier)
                        ->whereNull('deleted_at');
                });
            })
            ->pluck('medicine_id')
            ->unique();

        $this->loadStockCard();
    }

    public function loadStockCard()
    {
        $startDate = Carbon::create($this->year, $this->month, 1)->startOfMonth();
        $endDate   = Carbon::create($this->year, $this->month, 1)->endOfMonth();

        $selectedSupplier = $this->selectedSupplier === 'all'
            ? null
            : (int) $this->selectedSupplier;

        $data = app(StockCardService::class)->getStockCardData(
            medicineId: $this->medicines->first()->id,
            startDate: $startDate,
            endDate: $endDate,
            supplierId: $selectedSupplier
        );

        $this->firstStock = $data['opening_stock'];
        $this->stocks     = $data['transactions'];
        $this->lastStock  = $data['closing_stock'];
    }

    public function updated($property)
    {
        if (in_array($property, ['selectedSupplier', 'month', 'year'])) {
            $this->loadStockCard();
        }
    }
}
