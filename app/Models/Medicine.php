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
}
