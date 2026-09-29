<?php
$patch = function (string $file, array $pairs) {
    $s = file_get_contents($file);
    foreach ($pairs as $lama => $baru) {
        if (! str_contains($s, $lama)) { fwrite(STDERR, "GAGAL di $file: ".substr($lama, 0, 70)."\n"); exit(1); }
        $s = str_replace($lama, $baru, $s);
    }
    file_put_contents($file, $s);
    echo "ok: $file\n";
};

// --- Stok: 6 baris, judul ke Kartu Stok, baris ke kartu stok obat itu ---
$patch('app/Filament/Widgets/LowStockMedicinesWidget.php', [
    "use App\Models\Medicine;\n" => "use App\Filament\Pages\MedicineStockDetail;\nuse App\Filament\Resources\MedicineStocks\MedicineStockResource;\nuse App\Models\Medicine;\nuse App\Support\TautanWidget;\n",

    "    protected int|string|array \$columnSpan = 1;\n"
    => "    protected int|string|array \$columnSpan = 1;\n\n    /** Baris yang ditampilkan. Selebihnya lewat menu Kartu Stok, yang dicapai dari judul widget. */\n    private const BARIS = 6;\n",

    "            ->heading('Stok Obat')\n" => "            ->heading(TautanWidget::judul('Stok Obat', MedicineStockResource::canViewAny() ? MedicineStockResource::getUrl() : null))\n",

    "            ->description('Seluruh obat aktif, stok tersedia paling sedikit di atas.')\n"
    => "            ->description(self::BARIS.' obat dengan stok tersedia paling sedikit.')\n",

    "                ->orderBy('name'))\n" => "                ->orderBy('name')\n                ->limit(self::BARIS))\n",

    "            ->paginated(false)\n"
    => "            ->recordUrl(fn (Medicine \$record): ?string => MedicineStockDetail::canAccess()\n                ? MedicineStockDetail::getUrl(['record' => \$record->getKey()])\n                : null)\n            ->paginated(false)\n",
]);

// --- Kedaluwarsa: 6 baris, judul ke Laporan, baris ke kartu stok obat itu ---
$patch('app/Filament/Widgets/ExpiringMedicinesWidget.php', [
    "use App\Models\MedicineStock;\n" => "use App\Filament\Pages\LaporanKedaluwarsa;\nuse App\Filament\Pages\MedicineStockDetail;\nuse App\Models\MedicineStock;\nuse App\Support\TautanWidget;\n",

    "    protected int|string|array \$columnSpan = 1;\n"
    => "    protected int|string|array \$columnSpan = 1;\n\n    /** Baris yang ditampilkan. Selebihnya lewat Laporan Akan Kedaluwarsa, dicapai dari judul widget. */\n    private const BARIS = 6;\n",

    "            ->heading('Obat Mendekati Kedaluwarsa')\n"
    => "            ->heading(TautanWidget::judul('Obat Mendekati Kedaluwarsa', LaporanKedaluwarsa::canAccess() ? LaporanKedaluwarsa::getUrl() : null))\n",

    "            ->description('Obat dengan kedaluwarsa terdekat di atas. Warna mengikuti ambang '.AmbangEd::PANTAU.' hari.')\n"
    => "            ->description(self::BARIS.' obat dengan kedaluwarsa terdekat. Warna mengikuti ambang '.AmbangEd::PANTAU.' hari.')\n",

    "            ->query(fn (): Builder => BatchBersisa::perObat()->with('medicine:id,code,name')->orderBy('expired_date'))\n"
    => "            ->query(fn (): Builder => BatchBersisa::perObat()->with('medicine:id,code,name')->orderBy('expired_date')->limit(self::BARIS))\n",

    "            ->paginated(false)\n"
    => "            ->recordUrl(fn (MedicineStock \$record): ?string => MedicineStockDetail::canAccess()\n                ? MedicineStockDetail::getUrl(['record' => \$record->medicine_id])\n                : null)\n            ->paginated(false)\n",
]);

// --- SAW: 6 baris, judul ke halaman SPK, baris ke kartu stok obat itu ---
$patch('app/Filament/Widgets/SawTop10RestockWidget.php', [
    "use App\Services\SawCalculationService;\n" => "use App\Filament\Pages\MedicineStockDetail;\nuse App\Filament\Pages\SawCalculation;\nuse App\Services\SawCalculationService;\nuse App\Support\TautanWidget;\n",

    "    protected int|string|array \$columnSpan = 1;\n"
    => "    protected int|string|array \$columnSpan = 1;\n\n    /** Baris yang ditampilkan. Peringkat selengkapnya di halaman SPK, dicapai dari judul widget. */\n    private const BARIS = 6;\n",

    "            ->heading('Prioritas Restock (SAW)')\n"
    => "            ->heading(TautanWidget::judul('Prioritas Restock (SAW)', SawCalculation::canAccess() ? SawCalculation::getUrl() : null))\n",

    "            ->records(fn (): Collection => collect(\$this->hasil()['rows']))\n"
    => "            ->records(fn (): Collection => collect(array_slice(\$this->hasil()['rows'], 0, self::BARIS)))\n",

    "            ->paginated(false)\n"
    => "            ->recordUrl(fn (\$record): ?string => MedicineStockDetail::canAccess()\n                ? MedicineStockDetail::getUrl(['record' => \$record['medicine_id']])\n                : null)\n            ->paginated(false)\n",
]);

// Tinggi kartu disesuaikan dengan enam baris.
$patch('resources/css/filament/admin/theme.css', [
    "    height: 32rem;\n" => "    height: 24rem;\n",
]);
