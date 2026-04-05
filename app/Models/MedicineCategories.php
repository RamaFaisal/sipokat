<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineCategories extends Model
{
    protected $table = "medicine_categories";

    protected $fillable = [
        "name",
        "description",
    ];
}
