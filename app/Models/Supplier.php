<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $table = "suppliers";

    protected $fillable = [
        "code",
        "name",
        "address",
        "phone",
        "email",
        "pic",
        "status",
    ];

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
