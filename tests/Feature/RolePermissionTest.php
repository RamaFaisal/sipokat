<?php

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Hak akses per peran sesuai Tabel 3.2 naskah (rencana-sidang-2026-10 §3 B1):
 * Admin akses penuh, Petugas Apotek operasional, Pemilik/Manajer baca-saja.
 * Berbeda dari PanelPagesRenderTest yang sengaja melewati otorisasi, tes ini justru
 * menguji otorisasinya jadi tidak ada `Gate::before`.
 */
beforeEach(function () {
    seedMasterFixtures();
    $this->seed(RoleSeeder::class);
});

function userWithRole(string $role): User
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    return $user;
}

it('membuat tiga peran sesuai Tabel 3.2', function () {
    expect(Role::pluck('name'))
        ->toContain(RoleSeeder::ADMIN, RoleSeeder::PETUGAS, RoleSeeder::PEMILIK);
});

it('memberi Admin seluruh permission', function () {
    $admin = Role::findByName(RoleSeeder::ADMIN);

    expect($admin->permissions()->count())->toBe(Permission::count())
        ->and(Permission::count())->toBeGreaterThan(100);
});

it('memberi Petugas izin transaksi tetapi master obat hanya baca', function () {
    $petugas = userWithRole(RoleSeeder::PETUGAS);

    expect($petugas->can('Create:Order'))->toBeTrue()
        ->and($petugas->can('Delete:Order'))->toBeTrue()
        ->and($petugas->can('Create:ReceiveOrder'))->toBeTrue()
        ->and($petugas->can('Create:PurchaseOrder'))->toBeTrue()
        ->and($petugas->can('Create:MedicineStockOpname'))->toBeTrue()
        ->and($petugas->can('ViewAny:Medicine'))->toBeTrue()
        ->and($petugas->can('Create:Medicine'))->toBeFalse()
        ->and($petugas->can('Update:Medicine'))->toBeFalse()
        ->and($petugas->can('Delete:Medicine'))->toBeFalse();
});

it('tidak memberi Petugas akses pengguna, peran, bobot SAW, dan pengaturan', function () {
    $petugas = userWithRole(RoleSeeder::PETUGAS);

    expect($petugas->can('ViewAny:User'))->toBeFalse()
        ->and($petugas->can('ViewAny:Role'))->toBeFalse()
        ->and($petugas->can('Update:SawCriteria'))->toBeFalse()
        ->and($petugas->can('View:ManageGeneralSettings'))->toBeFalse();
});

it('memberi Petugas izin menjalankan perhitungan SPK', function () {
    $petugas = userWithRole(RoleSeeder::PETUGAS);

    // Sejak snapshot SAW dihapus, membuka & memuat ulang peringkat tidak menulis apa pun,
    // jadi cukup izin baca halaman.
    expect($petugas->can('View:SawCalculation'))->toBeTrue();
});

it('memberi Pemilik hanya izin baca, tanpa satu pun izin tulis', function () {
    $pemilik = Role::findByName(RoleSeeder::PEMILIK);

    $menulis = $pemilik->permissions
        ->pluck('name')
        ->reject(fn (string $name) => str_starts_with($name, 'View:') || str_starts_with($name, 'ViewAny:'));

    expect($menulis)->toBeEmpty();
});

it('memberi Pemilik dashboard, hasil SAW, dan laporan', function () {
    $pemilik = userWithRole(RoleSeeder::PEMILIK);

    expect($pemilik->can('View:Dashboard'))->toBeTrue()
        ->and($pemilik->can('View:SawCalculation'))->toBeTrue()
        ->and($pemilik->can('View:LaporanRekap'))->toBeTrue()
        ->and($pemilik->can('View:LaporanMoving'))->toBeTrue()
        ->and($pemilik->can('View:SawTop10RestockWidget'))->toBeTrue()
        ->and($pemilik->can('Create:PurchaseOrder'))->toBeFalse();
});

it('menolak Petugas membuka halaman yang bukan haknya', function (string $path) {
    $this->actingAs(userWithRole(RoleSeeder::PETUGAS));

    $this->get('/admin/'.$path)->assertForbidden();
})->with([
    'tambah obat' => 'medicines/create',
    'daftar pengguna' => 'users',
    'pengaturan umum' => 'manage-general-settings',
]);

it('mengizinkan Petugas membuka halaman operasionalnya', function (string $path) {
    makeMedicine();
    $this->actingAs(userWithRole(RoleSeeder::PETUGAS));

    $this->get('/admin/'.$path)->assertOk();
})->with([
    'dashboard' => '',
    'penjualan baru' => 'orders/create',
    'penerimaan baru' => 'receive-orders/create',
    'daftar obat' => 'medicines',
]);

it('menolak Pemilik membuka halaman yang mengubah data', function (string $path) {
    $this->actingAs(userWithRole(RoleSeeder::PEMILIK));

    $this->get('/admin/'.$path)->assertForbidden();
})->with([
    'penjualan baru' => 'orders/create',
    'penerimaan baru' => 'receive-orders/create',
    'tambah obat' => 'medicines/create',
]);

it('mengizinkan Pemilik melihat bobot SAW tetapi bukan mengubahnya', function () {
    $this->seed(\Database\Seeders\SawCriteriaSeeder::class);
    $kriteria = App\Models\SawCriteria::query()->firstOrFail();
    $this->actingAs(userWithRole(RoleSeeder::PEMILIK));

    $this->get('/admin/saw-criterias')->assertOk();
    $this->get("/admin/saw-criterias/{$kriteria->id}/edit")->assertForbidden();
});

it('mengizinkan Pemilik memantau tanpa mengubah', function (string $path) {
    $this->seed(\Database\Seeders\SawCriteriaSeeder::class);
    makeMedicine();
    $this->actingAs(userWithRole(RoleSeeder::PEMILIK));

    $this->get('/admin/'.$path)->assertOk();
})->with([
    'dashboard' => '',
    'hitung prioritas' => 'saw-calculation',
    'daftar obat' => 'medicines',
    // Boleh melihat bobot yang dipakai, tidak boleh mengubahnya (lihat dataset di atas).
    'bobot SAW' => 'saw-criterias',
]);

it('mengizinkan Pemilik memuat ulang peringkat karena perhitungan tidak menulis apa pun', function () {
    $this->seed(\Database\Seeders\SawCriteriaSeeder::class);
    $m = makeMedicine();
    receiveInto($m, 10);
    $this->actingAs(userWithRole(RoleSeeder::PEMILIK));

    Livewire\Livewire::test(App\Filament\Pages\SawCalculation::class)
        ->call('recalculate')
        ->assertHasNoErrors();
});

it('mengganti nama peran lama menjadi istilah Tabel 3.2', function () {
    Role::query()->delete();
    DB::table('roles')->insert([
        ['name' => 'super_admin', 'guard_name' => 'web'],
        ['name' => 'staff', 'guard_name' => 'web'],
        ['name' => 'manajer', 'guard_name' => 'web'],
    ]);

    (require database_path('migrations/2026_09_24_000001_rename_roles_to_thesis_terms.php'))->up();

    expect(DB::table('roles')->pluck('name')->sort()->values()->all())
        ->toBe([RoleSeeder::ADMIN, RoleSeeder::PEMILIK, RoleSeeder::PETUGAS]);
});

it('idempoten: seeder peran dijalankan dua kali tidak menggandakan izin', function () {
    $sebelum = Role::findByName(RoleSeeder::PETUGAS)->permissions()->count();

    $this->seed(RoleSeeder::class);

    expect(Role::findByName(RoleSeeder::PETUGAS)->permissions()->count())->toBe($sebelum)
        ->and(Role::where('name', RoleSeeder::PETUGAS)->count())->toBe(1);
});
