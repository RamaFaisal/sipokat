<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\MedicineStock;
use App\Support\Tanggal;
use Filament\Actions\DeleteAction;
use Filament\Forms\Contracts\HasForms;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

/**
 * Penjualan tidak bisa diedit (S6): halaman ini hanya menampilkan, dengan aksi hapus.
 *
 * Memakai infolist sendiri, bukan skema form dalam mode nonaktif. Form penjualan memuat medan
 * bantu yang tidak disimpan (`subtotal`, `fefo_batches`, `grand_total_display` semuanya
 * `dehydrated(false)`), sehingga halaman lihat yang menumpang form itu selalu menampilkannya kosong.
 *
 * Kolom Batch di sini diisi dari **alokasi yang sesungguhnya terjadi**, yaitu baris C kartu stok yang
 * menunjuk lapisannya, bukan dari pratinjau FEFO yang dihitung ulang atas stok hari ini.
 */
class ViewOrder extends ViewRecord implements HasForms, HasTable
{
    use InteractsWithTable;

    protected static string $resource = OrderResource::class;

    protected static ?string $title = 'Detail Penjualan';

    protected string $view = 'filament.pages.order-detail';

    /** Baris C penjualan ini, dikelompokkan per obat; dibaca sekali untuk seluruh tabel. */
    protected ?Collection $alokasi = null;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Stok yang terjual akan dikembalikan ke batch asalnya. Untuk mengoreksi, buat penjualan baru setelah menghapus.'),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informasi Penjualan')
                    ->columns(4)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('order_code')
                            ->label('Nomor'),

                        TextEntry::make('order_date')
                            ->label('Tanggal')
                            ->date(Tanggal::TAMPIL),

                        TextEntry::make('creator.name')
                            ->label('Dicatat Oleh')
                            ->placeholder('-'),

                        TextEntry::make('grand_total')
                            ->label('Total')
                            ->money('IDR')
                            ->weight('semibold'),

                        TextEntry::make('note')
                            ->label('Catatan')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->record->items()->with('medicine.unit')->getQuery())
            ->heading('Item Penjualan')
            ->description('Batch diambil otomatis dari kartu stok, mulai dari kedaluwarsa terdekat (FEFO).')
            ->columns([
                TextColumn::make('medicine_name')
                    ->label('Obat')
                    ->wrap(),

                TextColumn::make('qty')
                    ->label('Jumlah')
                    ->numeric()
                    ->suffix(fn ($record) => $record->medicine?->unit?->name ? ' '.$record->medicine->unit->name : '')
                    ->alignEnd(),

                TextColumn::make('price')
                    ->label('Harga')
                    ->money('IDR')
                    ->alignEnd(),

                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->state(fn ($record) => (int) $record->qty * (float) $record->price)
                    ->money('IDR')
                    ->weight('semibold')
                    ->alignEnd(),

                TextColumn::make('batch')
                    ->label('Batch diambil')
                    ->state(fn ($record) => $this->batchLabel((int) $record->medicine_id))
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->paginated(false);
    }

    /** "B-001 (ED Sep 2026) 5 · B-002 (ED Nov 2026) 3" satu penjualan bisa terpecah ke beberapa lapisan. */
    protected function batchLabel(int $medicineId): ?string
    {
        $baris = $this->alokasiPerObat()->get($medicineId);

        if ($baris === null || $baris->isEmpty()) {
            return null;
        }

        return $baris
            ->map(function (MedicineStock $c): string {
                $lapisan = $c->layer;
                $batch = $lapisan?->batch_number ?: 'tanpa batch';
                $ed = $lapisan?->expired_date ? ' (ED '.$lapisan->expired_date->translatedFormat(Tanggal::BULAN_TAHUN).')' : '';

                return $batch.$ed.' '.number_format($c->qty, 0, ',', '.');
            })
            ->implode(' · ');
    }

    /** @return Collection<int, Collection<int, MedicineStock>> */
    protected function alokasiPerObat(): Collection
    {
        return $this->alokasi ??= MedicineStock::query()
            ->where('order_id', $this->record->id)
            ->where('type_account', 'C')
            ->with('layer')
            ->orderBy('id')
            ->get()
            ->groupBy('medicine_id');
    }
}
