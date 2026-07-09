<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Medicine extends Model
{
    use SoftDeletes;

    protected $table = "medicines";

    protected $fillable = [
        "code",
        "name",
        "dosage",
        "category_id",
        "unit_id",
        "rack_id",
        "photo",
        "purchase_price",
        "sale_price",
        "min_stock",
        "stock_status",
        "status",
        "description",
    ];

    public function category()
    {
        return $this->belongsTo(MedicineCategories::class);
    }

    public function unit()
    {
        return $this->belongsTo(Unit::class);
    }

    public function rack()
    {
        return $this->belongsTo(MedicineRack::class);
    }

    public function purchaseOrderItems()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function stockEntries()
    {
        return $this->hasMany(MedicineStock::class);
    }

    /**
     * Hitung stok saat ini (masuk - keluar), exclude entry dengan parent (RO/Opname/Order)
     * yang sudah soft-deleted. Konsisten dengan StockCardService::getAvailableStock().
     */
    public function currentStock(): int
    {
        $base = $this->stockEntries()
            ->where(function ($q) {
                $q->whereHas('receiveOrder')->orWhereNull('receive_order_id');
            })
            ->where(function ($q) {
                $q->whereHas('medicineStockOpname')->orWhereNull('medicine_stock_opname_id');
            })
            ->where(function ($q) {
                $q->whereHas('order')->orWhereNull('order_id');
            });

        $in = (clone $base)->where('type_account', 'D')->sum('qty');
        $out = (clone $base)->where('type_account', 'C')->sum('qty');

        return (int) ($in - $out);
    }

    /**
     * Cek apakah stok di bawah minimum
     */
    public function isLowStock(): bool
    {
        return $this->currentStock() <= ($this->min_stock ?? 0);
    }

    public function receiveOrderItems()
    {
        return $this->hasMany(ReceiveOrderItem::class);
    }

    public function nearestExpiryDate(): ?\Illuminate\Support\Carbon
    {
        if ($this->currentStock() <= 0) {
            return null;
        }

        return $this->receiveOrderItems()
            ->whereNotNull('expired_date')
            ->whereDate('expired_date', '>=', now()->toDateString())
            ->orderBy('expired_date', 'asc')
            ->value('expired_date');
    }

    public function nearestExpiryDays(): ?int
    {
        $ed = $this->nearestExpiryDate();

        return $ed ? (int) now()->startOfDay()->diffInDays($ed->startOfDay(), false) : null;
    }
}
