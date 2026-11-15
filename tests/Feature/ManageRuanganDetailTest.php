<?php

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Lemari;
use App\Models\LokasiBarang;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('counts distinct item types in each cabinet and outside the cabinets', function () {
    $admin = User::query()->create([
        'username' => 'admin-room-detail-count',
        'first_name' => 'Admin',
        'last_name' => 'Room Detail',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Peralatan Ruangan']);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'LAB-DETAIL-COUNT',
        'nama_ruangan' => 'Laboratorium Detail',
    ]);
    $cabinet = Lemari::query()->create([
        'ruangan_id' => $room->ruangan_id,
        'kode_lemari' => 'LEM-DETAIL-COUNT',
        'nama_lemari' => 'Lemari 1',
    ]);
    $computer = Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-ROOM-COMPUTER',
        'nama_barang' => 'Komputer',
        'satuan' => 'Unit',
        'stok_total' => 30,
        'stok_tersedia' => 28,
    ]);
    $mouse = Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-ROOM-MOUSE',
        'nama_barang' => 'Mouse',
        'satuan' => 'Unit',
        'stok_total' => 5,
        'stok_tersedia' => 4,
    ]);
    $keyboard = Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-ROOM-KEYBOARD',
        'nama_barang' => 'Keyboard',
        'satuan' => 'Unit',
        'stok_total' => 4,
        'stok_tersedia' => 4,
    ]);
    LokasiBarang::query()->create([
        'barang_id' => $computer->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'lemari_id' => $cabinet->lemari_id,
        'stok_total' => 30,
        'stok_tersedia' => 28,
    ]);
    LokasiBarang::query()->create([
        'barang_id' => $mouse->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'stok_total' => 2,
        'stok_tersedia' => 2,
    ]);
    LokasiBarang::query()->create([
        'barang_id' => $mouse->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'stok_total' => 3,
        'stok_tersedia' => 2,
    ]);
    LokasiBarang::query()->create([
        'barang_id' => $keyboard->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'stok_total' => 4,
        'stok_tersedia' => 4,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.ruangan.show', $room));

    $response->assertOk()
        ->assertSeeText('1 Jenis Barang')
        ->assertSeeText('2 Jenis Barang')
        ->assertSeeText('30 Unit')
        ->assertSeeText('28 Unit');
});
