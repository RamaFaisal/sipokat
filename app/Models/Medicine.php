<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
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
     * Hitung stok saat ini (masuk - keluar)
     */
    public function currentStock(): int
    {
        $in = $this->stockEntries()->where('type_account', 'D')->sum('qty');
        $out = $this->stockEntries()->where('type_account', 'C')->sum('qty');

        return $in - $out;
    }

    /**
     * Cek apakah stok di bawah minimum
     */
    public function isLowStock(): bool
    {
        return $this->currentStock() <= ($this->min_stock ?? 0);
    }
}
