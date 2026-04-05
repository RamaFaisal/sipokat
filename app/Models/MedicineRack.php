<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MedicineRack extends Model
{
    protected $table = "medicine_racks";

    protected $fillable = [
        "name",
        "description",
    ];
}
