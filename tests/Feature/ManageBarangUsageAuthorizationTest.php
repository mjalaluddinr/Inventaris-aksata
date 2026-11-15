<?php

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LokasiBarang;
use App\Models\Privilege;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('lets laboran with management permission record and withdraw item usage', function () {
    $laboran = User::query()->create([
        'username' => 'laboran-manajemen',
        'first_name' => 'Laboran',
        'last_name' => 'Manajemen',
        'password_hash' => 'test-password',
        'role' => 'laboran',
        'is_active' => true,
    ]);
    $laboran->privileges()->create(['hak_akses' => 'manajemen']);

    $admin = User::query()->create([
        'username' => 'admin-pemakai',
        'first_name' => 'Admin',
        'last_name' => 'Pemakai',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Peralatan']);
    $storageRoom = Ruangan::query()->create([
        'kode_ruangan' => 'GUD-01',
        'nama_ruangan' => 'Gudang',
    ]);
    $usageRoom = Ruangan::query()->create([
        'kode_ruangan' => 'LAB-01',
        'nama_ruangan' => 'Laboratorium',
    ]);
    $item = Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-001',
        'nama_barang' => 'Kabel',
        'satuan' => 'buah',
        'stok_total' => 5,
        'stok_tersedia' => 5,
    ]);
    LokasiBarang::query()->create([
        'barang_id' => $item->barang_id,
        'ruangan_id' => $storageRoom->ruangan_id,
        'stok_total' => 5,
        'stok_tersedia' => 5,
    ]);

    $this->actingAs($laboran)
        ->get(route('admin.barang'))
        ->assertOk()
        ->assertSee('Catat Pemakaian')
        ->assertSee('Pemakaian aktif')
        ->assertSeeText('Laboran Manajemen')
        ->assertDontSee('Riwayat Mutasi');

    $this->post(route('admin.barang.pemakaian.store', $item), [
        'jumlah' => 2,
        'ruangan_id' => $usageRoom->ruangan_id,
        'akun_id' => $admin->akun_id,
        'tanggal_pasang' => today('Asia/Jakarta')->toDateString(),
        'keterangan' => 'Dipakai laboratorium',
    ])->assertRedirect();

    $usage = $item->barangDipakais()->sole();

    $this->assertDatabaseHas('barang', [
        'barang_id' => $item->barang_id,
        'stok_tersedia' => 3,
    ]);
    $this->assertDatabaseHas('lokasi_barang', [
        'barang_id' => $item->barang_id,
        'stok_tersedia' => 3,
    ]);
    $this->assertDatabaseHas('barang_dipakai', [
        'barang_dipakai_id' => $usage->barang_dipakai_id,
        'akun_id' => $admin->akun_id,
        'status' => 'aktif',
    ]);

    $this->post(route('admin.barang.pemakaian.tarik', [$item, $usage]))
        ->assertRedirect();

    $this->assertDatabaseHas('barang', [
        'barang_id' => $item->barang_id,
        'stok_tersedia' => 5,
    ]);
    $this->assertDatabaseHas('barang_dipakai', [
        'barang_dipakai_id' => $usage->barang_dipakai_id,
        'status' => 'ditarik',
    ]);

    $this->post(route('admin.barang.pemakaian.store', $item), [
        'jumlah' => 1,
        'ruangan_id' => $usageRoom->ruangan_id,
        'akun_id' => $laboran->akun_id,
        'tanggal_pasang' => today('Asia/Jakarta')->toDateString(),
    ])->assertRedirect();

    $this->assertDatabaseHas('barang_dipakai', [
        'barang_id' => $item->barang_id,
        'akun_id' => $laboran->akun_id,
        'status' => 'aktif',
    ]);
});

it('rejects usage dates after today in Jakarta', function () {
    $admin = User::query()->create([
        'username' => 'admin-usage-future-date',
        'first_name' => 'Admin',
        'last_name' => 'Tanggal',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Peralatan Tanggal']);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'GUD-DATE',
        'nama_ruangan' => 'Gudang',
    ]);
    $item = Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-FUTURE-DATE',
        'nama_barang' => 'Kabel Tanggal',
        'stok_total' => 2,
        'stok_tersedia' => 2,
    ]);
    LokasiBarang::query()->create([
        'barang_id' => $item->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'stok_total' => 2,
        'stok_tersedia' => 2,
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.barang.pemakaian.store', $item), [
            'jumlah' => 1,
            'ruangan_id' => $room->ruangan_id,
            'akun_id' => $admin->akun_id,
            'tanggal_pasang' => today('Asia/Jakarta')->addDay()->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['tanggal_pasang']);

    $this->assertDatabaseMissing('barang_dipakai', ['barang_id' => $item->barang_id]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $item->barang_id,
        'stok_tersedia' => 2,
    ]);
});

it('rejects a laboran without management permission as the usage account', function () {
    $admin = User::query()->create([
        'username' => 'admin-usage-target',
        'first_name' => 'Admin',
        'last_name' => 'Target',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $laboran = User::query()->create([
        'username' => 'laboran-usage-target',
        'first_name' => 'Laboran',
        'last_name' => 'Tanpa Manajemen',
        'password_hash' => 'test-password',
        'role' => 'laboran',
        'is_active' => true,
    ]);
    $laboran->privileges()->create(['hak_akses' => 'melihat']);
    $category = Kategori::query()->create(['nama_kategori' => 'Peralatan Pemakai']);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'GUD-TARGET',
        'nama_ruangan' => 'Gudang',
    ]);
    $item = Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-INVALID-TARGET',
        'nama_barang' => 'Kabel Pemakai',
        'stok_total' => 1,
        'stok_tersedia' => 1,
    ]);
    LokasiBarang::query()->create([
        'barang_id' => $item->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'stok_total' => 1,
        'stok_tersedia' => 1,
    ]);

    $this->actingAs($admin)
        ->postJson(route('admin.barang.pemakaian.store', $item), [
            'jumlah' => 1,
            'ruangan_id' => $room->ruangan_id,
            'akun_id' => $laboran->akun_id,
            'tanggal_pasang' => today('Asia/Jakarta')->toDateString(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['akun_id']);

    $this->assertDatabaseMissing('barang_dipakai', ['barang_id' => $item->barang_id]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $item->barang_id,
        'stok_tersedia' => 1,
    ]);
});

it('forbids laboran without management permission from recording item usage', function () {
    $laboran = User::query()->create([
        'username' => 'laboran-lihat',
        'first_name' => 'Laboran',
        'last_name' => 'Lihat',
        'password_hash' => 'test-password',
        'role' => 'laboran',
        'is_active' => true,
    ]);
    Privilege::query()->create([
        'akun_id' => $laboran->akun_id,
        'hak_akses' => 'melihat',
    ]);

    $category = Kategori::query()->create(['nama_kategori' => 'Peralatan']);
    $item = Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-002',
        'nama_barang' => 'Kabel',
        'stok_total' => 1,
        'stok_tersedia' => 1,
    ]);

    $this->actingAs($laboran)
        ->post(route('admin.barang.pemakaian.store', $item))
        ->assertForbidden();
});
