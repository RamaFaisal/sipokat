<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicineCategories extends Model
{
    use SoftDeletes;

    protected $table = "medicine_categories";

    protected $fillable = [
        "name",
        "description",
    ];
}
