<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicineRack extends Model
{
    use SoftDeletes;

    protected $table = "medicine_racks";

    protected $fillable = [
        "name",
        "description",
    ];
}
