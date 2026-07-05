<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SalesSummaryWidget extends ChartWidget
{
    use HasWidgetShield;

    protected ?string $heading = 'Penjualan 30 Hari Terakhir';

    protected ?string $description = 'Total grand_total harian dari Orders (status != cancelled).';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '400px';

    protected function getData(): array
    {
        $start = Carbon::now()->subDays(29)->startOfDay();
        $end = Carbon::now()->endOfDay();

        $totals = Order::query()
            ->where('status', '!=', 'cancelled')
            ->whereBetween('order_date', [$start->toDateString(), $end->toDateString()])
            ->select(DB::raw('DATE(order_date) as day'), DB::raw('SUM(grand_total) as total'),)
            ->groupBy('day')
            ->pluck('total', 'day');

        $labels = [];
        $data = [];

        for ($i = 0; $i < 30; $i++) {
            $date = $start->copy()->addDays($i);
            $key = $date->toDateString();
            $labels[] = $date->format('d M');
            $data[] = (float) ($totals[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Total Penjualan (Rp)',
                    'data' => $data,
                    'borderColor' => '#0bf52e',
                    'backgroundColor' => 'rgba(27, 177, 16, 0.15)',
                    'fill' => true,
                    'tension' => 0.5,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
