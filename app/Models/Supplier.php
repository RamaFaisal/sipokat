<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use SoftDeletes;

    protected $table = "suppliers";

    protected $fillable = [
        "code",
        "name",
        "address",
        "phone",
        "fax",
        "status",
    ];

    /** Kata badan usaha yang tidak ikut membentuk kode. */
    private const LEGAL_WORDS = ['PT', 'CV', 'UD', 'PD', 'FIRMA', 'KOPERASI', 'YAYASAN', 'TBK'];

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier) {
            if (blank($supplier->code)) {
                $supplier->code = self::nextCode($supplier->name);
            }
        });
    }

    /**
     * Kode dari inisial nama: "PT. Nisa Permata Mulia" → NPM, "Enseval" → ENS.
     * Kata badan usaha (PT, CV, ...) dibuang; maksimal 3 huruf.
     */
    public static function codeFromName(?string $name): string
    {
        $words = preg_split('/\s+/', trim(preg_replace('/[^A-Za-z0-9\s]/', ' ', (string) $name))) ?: [];
        $words = array_values(array_filter($words, fn ($w) => $w !== '' && ! in_array(strtoupper($w), self::LEGAL_WORDS, true)));

        if ($words === []) {
            $words = array_values(array_filter(preg_split('/\s+/', trim((string) $name)) ?: []));
        }

        if ($words === []) {
            return 'PBF';
        }

        $code = count($words) === 1
            ? substr($words[0], 0, 3)
            : implode('', array_map(fn ($w) => substr($w, 0, 1), array_slice($words, 0, 3)));

        return strtoupper($code);
    }

    /**
     * Kode unik dari nama: inisial, lalu ditambah angka bila sudah dipakai
     * (NPM, NPM2, NPM3, ...). Termasuk baris soft-deleted supaya kode tidak terpakai dua kali.
     */
    public static function nextCode(?string $name): string
    {
        $base = self::codeFromName($name);
        $taken = static::withTrashed()
            ->where('code', 'like', $base.'%')
            ->pluck('code')
            ->map(fn ($c) => strtoupper($c))
            ->all();

        if (! in_array($base, $taken, true)) {
            return $base;
        }

        for ($i = 2; ; $i++) {
            if (! in_array($base.$i, $taken, true)) {
                return $base.$i;
            }
        }
    }

    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }
}
