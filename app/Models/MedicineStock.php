<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu baris kartu stok, selalu dalam satuan jual obat.
 *
 * Baris D adalah lapisan batch (batch_number, expired_date, jejak asal RO). Baris C menunjuk
 * lapisan yang dikuranginya lewat layer_stock_id. Kolom hpp = harga pokok baris itu
 * (harga beli untuk D, HPP rata-rata saat keluar untuk C); hpp_avg = HPP rata-rata
 * bergerak sesudah baris ini (rencana-revisi-2026-09 §4.3).
 *
 * Tidak ada soft delete: baris dihapus sungguhan saat dokumennya dihapus (B5).
 */
class MedicineStock extends Model
{
    protected $fillable = [
        'medicine_id',
        'qty',
        'type_account',
        'batch_number',
        'expired_date',
        'date',
        'receive_order_id',
        'receive_order_item_id',
        'layer_stock_id',
        'medicine_stock_opname_id',
        'order_id',
        'hpp',
        'hpp_avg',
        'description',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'expired_date' => 'date',
        'hpp' => 'decimal:2',
        'hpp_avg' => 'integer',
    ];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function receiveOrder(): BelongsTo
    {
        return $this->belongsTo(ReceiveOrder::class);
    }

    public function receiveOrderItem(): BelongsTo
    {
        return $this->belongsTo(ReceiveOrderItem::class);
    }

    public function medicineStockOpname(): BelongsTo
    {
        return $this->belongsTo(MedicineStockOpname::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Lapisan (baris D) yang dikurangi baris C ini. */
    public function layer(): BelongsTo
    {
        return $this->belongsTo(self::class, 'layer_stock_id');
    }

    /** Baris C yang mengurangi lapisan (baris D) ini. */
    public function consumptions(): HasMany
    {
        return $this->hasMany(self::class, 'layer_stock_id');
    }

    /** Hanya baris D — setiap baris D adalah satu lapisan batch. */
    public function scopeLayers(Builder $query): Builder
    {
        return $query->where('type_account', 'D');
    }

    /** Sisa lapisan = qty D − Σ qty C yang menunjuknya. Hanya bermakna untuk baris D. */
    public function getRemainingAttribute(): int
    {
        if ($this->type_account !== 'D') {
            return 0;
        }

        $consumed = $this->relationLoaded('consumptions')
            ? $this->consumptions->sum('qty')
            : $this->consumptions()->sum('qty');

        return (int) $this->qty - (int) $consumed;
    }

    public function isExpired(): bool
    {
        // Kedaluwarsa sejak tanggal ED itu sendiri (ED disimpan tanggal 1 bulan, R3/B1).
        return $this->expired_date !== null && $this->expired_date->lte(today());
    }
}
