<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Medicine extends Model
{
    use SoftDeletes;

    protected $table = 'medicines';

    /** Batas waspada bawaan untuk satuan jual Strip (batas waspada wawancara). */
    public const DEFAULT_MIN_STOCK_STRIP = 20;

    public const CODE_PREFIX = 'OBT';

    protected $fillable = [
        'code',
        'name',
        'category_id',
        'unit_id',
        'pack_unit_id',
        'pack_size',
        'min_stock',
        'stock_status',
        'status',
    ];

    protected static function booted(): void
    {
        static::creating(function (Medicine $medicine) {
            $medicine->name = self::normalizeName($medicine->name);

            if (blank($medicine->code)) {
                $medicine->code = self::nextCode();
            }

            if ((int) $medicine->min_stock <= 0) {
                $medicine->min_stock = self::defaultMinStock($medicine->unit_id, (int) $medicine->pack_size);
            }
        });

        static::updating(function (Medicine $medicine) {
            $medicine->name = self::normalizeName($medicine->name);
        });
    }

    /** Uppercase, spasi ganda dirapikan — konvensi nama seperti tercetak di faktur. */
    public static function normalizeName(?string $name): string
    {
        return strtoupper(trim(preg_replace('/\s+/', ' ', (string) $name)));
    }

    /**
     * Kode sekuensial OBT0001, dibuat sekali dan tidak pernah dihitung ulang.
     * Termasuk baris soft-deleted supaya nomor tidak terpakai dua kali. Transaksi +
     * lockForUpdate menyerialkan dua pembuatan obat yang bersamaan (di SQLite lock diabaikan,
     * tidak masalah untuk test).
     */
    public static function nextCode(): string
    {
        return DB::transaction(fn () => self::computeNextCode(lock: true));
    }

    /**
     * Pratinjau kode berikutnya untuk ditampilkan di form tambah — tanpa lock, jadi
     * nomornya baru pasti saat disimpan (creating memanggil nextCode()).
     */
    public static function peekNextCode(): string
    {
        return self::computeNextCode(lock: false);
    }

    private static function computeNextCode(bool $lock): string
    {
        $query = static::withTrashed()
            ->where('code', 'like', self::CODE_PREFIX.'%')
            ->orderByDesc('code');

        if ($lock) {
            $query->lockForUpdate();
        }

        $last = $query->value('code');
        $next = $last ? ((int) substr($last, strlen(self::CODE_PREFIX))) + 1 : 1;

        return sprintf('%s%04d', self::CODE_PREFIX, $next);
    }

    /** Bawaan batas waspada: Strip 20, satuan lain = isi satu kemasan (M9, B6). */
    public static function defaultMinStock(?int $unitId, int $packSize): int
    {
        $unitName = $unitId ? Unit::withTrashed()->whereKey($unitId)->value('name') : null;

        if ($unitName !== null && strtolower($unitName) === 'strip') {
            return self::DEFAULT_MIN_STOCK_STRIP;
        }

        return max(1, $packSize);
    }

    public function category()
    {
        return $this->belongsTo(MedicineCategories::class);
    }

    /** Satuan jual = satuan kartu stok (satuan dasar). */
    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    /** Kemasan pembelian, mis. Box; isinya di pack_size. */
    public function packUnit()
    {
        return $this->belongsTo(Unit::class, 'pack_unit_id');
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function receiveOrderItems()
    {
        return $this->hasMany(ReceiveOrderItem::class);
    }

    public function stockEntries()
    {
        return $this->hasMany(MedicineStock::class);
    }

    /** Label kemasan untuk tampilan: "Box (10 Strip)". */
    public function packLabel(): string
    {
        $pack = $this->packUnit?->name ?? '-';
        $unit = $this->unit?->name ?? '';

        return sprintf('%s (%d %s)', $pack, $this->pack_size, $unit);
    }

    /**
     * Harga item RO terakhir per satuan jual — hanya untuk harga perkiraan PO (P6).
     * C4 SAW memakai HPP rata-rata bergerak di kartu stok, bukan ini.
     */
    public function latestPurchasePrice(): ?float
    {
        $price = ReceiveOrderItem::query()
            ->where('receive_order_items.medicine_id', $this->id)
            ->join('receive_orders', 'receive_orders.id', '=', 'receive_order_items.receive_order_id')
            ->whereNull('receive_orders.deleted_at')
            ->orderByDesc('receive_orders.receive_date')
            ->orderByDesc('receive_order_items.id')
            ->value('receive_order_items.price');

        return $price === null ? null : (float) $price;
    }

    /**
     * Stok fisik saat ini (Σ D − Σ C). Tidak perlu menyaring dokumen induk: baris ledger
     * dihapus sungguhan saat dokumennya dihapus (B5). Sama dengan StockCardService::physicalStock().
     */
    public function currentStock(): int
    {
        $in = $this->stockEntries()->where('type_account', 'D')->sum('qty');
        $out = $this->stockEntries()->where('type_account', 'C')->sum('qty');

        return (int) ($in - $out);
    }

    /** HPP rata-rata bergerak saat ini (rencana §4.3); null bila belum ada baris kartu stok. */
    public function currentHpp(): ?int
    {
        $avg = $this->stockEntries()->orderByDesc('date')->orderByDesc('id')->value('hpp_avg');

        return $avg === null ? null : (int) $avg;
    }

    /** Stok tersedia = sisa lapisan yang belum kedaluwarsa (B4, F2). */
    public function availableStock(): int
    {
        return app(\App\Services\StockCardService::class)->availableStock($this->id);
    }

    /**
     * C3 (K3): ED **batch terjauh yang masih bersisa** dan belum kedaluwarsa. Dengan FEFO,
     * batch terjauh adalah yang dikonsumsi terakhir — itulah horizon stok yang akan tersisa.
     * Tidak ada lapisan layak (stok tersedia 0) → null; pemanggil memperlakukannya sebagai 0 hari.
     */
    public function farthestExpiryDate(): ?\Illuminate\Support\Carbon
    {
        $layer = app(\App\Services\StockCardService::class)
            ->sellableLayers($this->id)
            ->filter(fn (MedicineStock $l) => $l->expired_date !== null)
            ->sortByDesc(fn (MedicineStock $l) => $l->expired_date->timestamp)
            ->first();

        return $layer?->expired_date;
    }

    /** Sisa hari ke ED batch terjauh yang bersisa; 0 bila tidak ada stok tersedia (K3, T2). */
    public function farthestExpiryDays(): int
    {
        $ed = $this->farthestExpiryDate();

        return $ed ? max(0, (int) today()->diffInDays($ed->copy()->startOfDay(), false)) : 0;
    }

    /** @deprecated dipakai SAW lama; diganti farthestExpiryDays() pada tahap E5. */
    public function nearestExpiryDays(): ?int
    {
        $layer = app(\App\Services\StockCardService::class)
            ->sellableLayers($this->id)
            ->filter(fn (MedicineStock $l) => $l->expired_date !== null)
            ->first();

        return $layer ? (int) today()->diffInDays($layer->expired_date->copy()->startOfDay(), false) : null;
    }
}
