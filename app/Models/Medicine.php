<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Medicine extends Model
{
    protected $table = "medicines";

    protected $fillable = [
        "code",
        "name",
        "category_id",
        "unit_id",
        "rack_id",
        "supplier_id",
        "photo",
        "purchase_price",
        "sale_price",
        "min_stock",
        "description",
    ];
}
