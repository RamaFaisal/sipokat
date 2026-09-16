<?php

use App\Filament\Forms\PackLine;

/** Harga per kemasan bertopeng rupiah (41.000) harus terbaca sebagai angka di semua jalur PackLine. */
it('membaca harga bertopeng, desimal koma, dan angka polos', function ($input, $expected) {
    expect(PackLine::toNumber($input))->toBe($expected);
})->with([
    ['41.000', 41000.0],
    ['1.250.000', 1250000.0],
    ['7.053,57', 7053.57],
    ['41000', 41000.0],
    [41000, 41000.0],
    [7053.57, 7053.57],
    ['', null],
    [null, null],
]);

it('menghitung konversi dan subtotal dari harga bertopeng', function () {
    [$qty, $price] = PackLine::convert(2, 10, '40.000');

    expect($qty)->toBe(20)
        ->and($price)->toBe(4000.0)
        ->and(PackLine::subtotal(2, '40.000'))->toBe('80.000')
        ->and(PackLine::subtotal(3, 41000))->toBe('123.000')
        ->and(PackLine::subtotal(0, '40.000'))->toBeNull();
});
