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

test('barang search finds visible item data across category, stock, and nested locations', function () {
    $admin = User::query()->create([
        'username' => 'admin-barang-universal-search',
        'first_name' => 'Search',
        'last_name' => 'Admin',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Optik Universal']);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'LAB-SEARCH-01',
        'nama_ruangan' => 'Ruang Mikroskop',
    ]);
    $cabinet = Lemari::query()->create([
        'ruangan_id' => $room->ruangan_id,
        'kode_lemari' => 'LEM-SEARCH-01',
        'nama_lemari' => 'Lemari Biru',
    ]);
    $toolbox = Toolbox::query()->create([
        'kode_toolbox' => 'TB-SEARCH-01',
        'nama_toolbox' => 'Kotak Kalibrasi',
    ]);
    $item = Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-SEARCH-01',
        'nama_barang' => 'Kabel Universal',
        'satuan' => 'PCS',
        'stok_total' => 23,
        'stok_tersedia' => 17,
        'kondisi' => 'lama',
        'kelayakan' => 'layak',
        'spesifikasi' => 'Konektor optik presisi',
    ]);
    LokasiBarang::query()->create([
        'barang_id' => $item->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'lemari_id' => $cabinet->lemari_id,
        'toolbox_id' => $toolbox->toolbox_id,
        'stok_total' => 23,
        'stok_tersedia' => 17,
    ]);
    $decoyCategory = Kategori::query()->create(['nama_kategori' => 'Peralatan Lain']);
    Barang::query()->create([
        'kategori_id' => $decoyCategory->kategori_id,
        'kode_barang' => 'BRG-SEARCH-DECOY',
        'nama_barang' => 'Barang Lain',
        'satuan' => 'PCS',
        'stok_total' => 2,
        'stok_tersedia' => 2,
        'kondisi' => 'baru',
        'kelayakan' => 'layak',
    ]);

    foreach (['BRG-SEARCH-01', 'Optik Universal', 'Konektor optik', 'lama', 'Ruang Mikroskop', 'LEM-SEARCH-01', 'TB-SEARCH-01', '17/23'] as $term) {
        $this->actingAs($admin)
            ->get(route('admin.barang', ['search' => $term]))
            ->assertOk()
            ->assertSeeText('Kabel Universal')
            ->assertDontSeeText('Barang Lain');
    }
});

test('barang search and filters return an AJAX results fragment', function () {
    $admin = User::query()->create([
        'username' => 'admin-barang-ajax-search',
        'first_name' => 'AJAX',
        'last_name' => 'Search',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Kategori AJAX']);
    $item = Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-AJAX-SEARCH',
        'nama_barang' => 'Barang AJAX',
        'satuan' => 'PCS',
        'stok_total' => 3,
        'stok_tersedia' => 3,
        'kondisi' => 'baru',
        'kelayakan' => 'layak',
    ]);

    $this->actingAs($admin)
        ->withHeaders([
            'Accept' => 'text/html',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('admin.barang', [
            'search' => 'Barang AJAX',
            'kategori_id' => $category->kategori_id,
        ]))
        ->assertOk()
        ->assertDontSee('<!DOCTYPE html>', false)
        ->assertSeeText($item->nama_barang);
});
