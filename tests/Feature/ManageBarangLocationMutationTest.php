<?php

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Lemari;
use App\Models\LokasiBarang;
use App\Models\Ruangan;
use App\Models\Toolbox;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('changing toolbox placement in the same cabinet is logged as an adjustment while cabinet moves remain transfers', function () {
    $admin = User::query()->create([
        'username' => 'admin-location-mutation',
        'first_name' => 'Location',
        'last_name' => 'Admin',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Peralatan']);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'GUD-LOC',
        'nama_ruangan' => 'Gudang',
    ]);
    $sourceCabinet = Lemari::query()->create([
        'ruangan_id' => $room->ruangan_id,
        'kode_lemari' => 'LEM-A',
        'nama_lemari' => 'Lemari A',
    ]);
    $destinationCabinet = Lemari::query()->create([
        'ruangan_id' => $room->ruangan_id,
        'kode_lemari' => 'LEM-B',
        'nama_lemari' => 'Lemari B',
    ]);
    $toolbox = Toolbox::query()->create([
        'kode_toolbox' => 'TB-01',
        'nama_toolbox' => 'Toolbox 01',
    ]);
    $item = Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-LOCATION-01',
        'nama_barang' => 'Kunci Pas',
        'satuan' => 'PCS',
        'stok_total' => 5,
        'stok_tersedia' => 5,
    ]);
    LokasiBarang::query()->create([
        'barang_id' => $item->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'lemari_id' => $sourceCabinet->lemari_id,
        'toolbox_id' => $toolbox->toolbox_id,
        'stok_total' => 5,
        'stok_tersedia' => 5,
    ]);

    $response = $this->actingAs($admin)->put(route('admin.barang.update', $item), [
        'barang_form' => 'edit',
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-LOCATION-01',
        'nama_barang' => 'Kunci Pas',
        'satuan' => 'PCS',
        'stok_total' => 5,
        'stok_tersedia' => 5,
        'spesifikasi' => null,
        'locations' => [
            [
                'ruangan_id' => $room->ruangan_id,
                'lemari_id' => $sourceCabinet->lemari_id,
                'toolbox_id' => null,
                'stok_total' => 3,
                'stok_tersedia' => 3,
            ],
            [
                'ruangan_id' => $room->ruangan_id,
                'lemari_id' => $destinationCabinet->lemari_id,
                'toolbox_id' => null,
                'stok_total' => 2,
                'stok_tersedia' => 2,
            ],
        ],
    ]);

    $response->assertRedirectToRoute('admin.barang');

    $this->assertDatabaseHas('log_mutasi_barang', [
        'barang_id' => $item->barang_id,
        'tipe' => 'penyesuaian',
        'jumlah' => 3,
        'stok_sebelum' => 5,
        'stok_sesudah' => 2,
        'keterangan' => 'Penempatan disesuaikan dari Gudang · Lemari A · Di dalam Toolbox 01 ke Gudang · Lemari A · Di luar toolbox.',
    ]);
    $this->assertDatabaseHas('log_mutasi_barang', [
        'barang_id' => $item->barang_id,
        'tipe' => 'dipindahkan',
        'jumlah' => 2,
        'stok_sebelum' => 2,
        'stok_sesudah' => 0,
        'keterangan' => 'Stok dipindahkan dari Gudang · Lemari A · Di dalam Toolbox 01 ke Gudang · Lemari B · Di luar toolbox.',
    ]);
    $this->assertDatabaseHas('lokasi_barang', [
        'barang_id' => $item->barang_id,
        'lemari_id' => $sourceCabinet->lemari_id,
        'toolbox_id' => null,
        'stok_total' => 3,
        'stok_tersedia' => 3,
    ]);
    $this->assertDatabaseMissing('lokasi_barang', [
        'barang_id' => $item->barang_id,
        'toolbox_id' => $toolbox->toolbox_id,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.log_mutasi'))
        ->assertOk()
        ->assertSeeText('Penempatan disesuaikan dari Gudang · Lemari A · Di dalam Toolbox 01 ke Gudang · Lemari A · Di luar toolbox.');
});
