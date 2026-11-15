<?php

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

test('home product cards link to the dedicated detail page without showing inventory codes', function () {
    $borrower = User::query()->create([
        'username' => 'home-product-details',
        'first_name' => 'Home',
        'last_name' => 'Borrower',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Perangkat Praktik']);
    $detailItem = Barang::query()->create([
        'kategori_id' => $category->getKey(),
        'kode_barang' => 'HOME-DETAILS-SECRET-CODE',
        'nama_barang' => 'Kamera Praktik',
        'stok_total' => 5,
        'stok_tersedia' => 1,
        'spesifikasi' => 'Kamera untuk dokumentasi praktikum.',
    ]);
    foreach (['Tripod Praktik', 'Mikroskop Praktik', 'Lampu Praktik', 'Speaker Praktik'] as $index => $name) {
        Barang::query()->create([
            'kategori_id' => $category->getKey(),
            'kode_barang' => 'HOME-DETAILS-SECRET-'.$index,
            'nama_barang' => $name,
            'stok_total' => 4 + $index,
            'stok_tersedia' => 2 + $index,
        ]);
    }

    $detailUrl = route('user.barang.detail', ['barang' => $detailItem->getKey()]);

    $this->actingAs($borrower)
        ->get(route('user.beranda'))
        ->assertOk()
        ->assertSee('href="'.e($detailUrl).'"', false)
        ->assertSee('data-user-navigation-link', false)
        ->assertSee(parse_url(route('user.barang.detail'), PHP_URL_PATH), false)
        ->assertDontSee('HOME-DETAILS-SECRET', false);
});

test('product detail shows database information and three available recommendations without item codes', function () {
    $borrower = User::query()->create([
        'username' => 'product-detail-page',
        'first_name' => 'Detail',
        'last_name' => 'Borrower',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Perangkat Praktik']);
    $detailItem = Barang::query()->create([
        'kategori_id' => $category->getKey(),
        'kode_barang' => 'DETAIL-PAGE-SECRET-CODE',
        'nama_barang' => 'Kamera Laboratorium',
        'satuan' => 'unit',
        'stok_total' => 5,
        'stok_tersedia' => 2,
        'kondisi' => 'baru',
        'spesifikasi' => 'Kamera untuk dokumentasi praktikum.',
    ]);
    foreach (['Tripod Laboratorium', 'Mikroskop Laboratorium', 'Lampu Laboratorium', 'Speaker Laboratorium'] as $index => $name) {
        Barang::query()->create([
            'kategori_id' => $category->getKey(),
            'kode_barang' => 'DETAIL-RELATED-SECRET-'.$index,
            'nama_barang' => $name,
            'stok_total' => 4 + $index,
            'stok_tersedia' => 3 + $index,
        ]);
    }

    $this->actingAs($borrower)
        ->get(route('user.barang.detail', ['barang' => $detailItem->getKey()]))
        ->assertOk()
        ->assertSee('Kamera Laboratorium')
        ->assertSee('Perangkat Praktik')
        ->assertSee('Kamera untuk dokumentasi praktikum.')
        ->assertSee('Baru')
        ->assertSee('Produk lainnya')
        ->assertSee('Lihat semua')
        ->assertDontSee('DETAIL-PAGE-SECRET-CODE', false)
        ->assertDontSee('DETAIL-RELATED-SECRET', false)
        ->assertViewHas('relatedProducts', fn ($items): bool => $items->count() === 3
            && ! $items->contains('barang_id', $detailItem->getKey()));
});

test('product detail returns 404 for an invalid inventory identifier', function () {
    $borrower = User::query()->create([
        'username' => 'invalid-product-detail',
        'first_name' => 'Invalid',
        'last_name' => 'Borrower',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);

    $this->actingAs($borrower)
        ->get(route('user.barang.detail', ['barang' => 'not-a-uuid']))
        ->assertNotFound();
});

test('product detail requires borrower portal access', function () {
    $detailUrl = route('user.barang.detail', ['barang' => Str::uuid()->toString()]);

    $this->get($detailUrl)->assertRedirect(route('login.index'));

    $admin = User::query()->create([
        'username' => 'product-detail-admin',
        'first_name' => 'Detail',
        'last_name' => 'Admin',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get($detailUrl)
        ->assertForbidden();
});

test('catalog product cards link to their dedicated detail pages without showing inventory codes', function () {
    $borrower = User::query()->create([
        'username' => 'catalog-product-details',
        'first_name' => 'Catalog',
        'last_name' => 'Borrower',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Perangkat Katalog']);
    $items = collect();
    foreach (['Tripod Katalog', 'Mikroskop Katalog', 'Lampu Katalog', 'Kamera Katalog'] as $index => $name) {
        $items->push(Barang::query()->create([
            'kategori_id' => $category->getKey(),
            'kode_barang' => 'CATALOG-DETAILS-SECRET-'.$index,
            'nama_barang' => $name,
            'stok_total' => 4 + $index,
            'stok_tersedia' => 3 + $index,
        ]));
    }

    $response = $this->actingAs($borrower)->get(route('user.catalog'));

    $response->assertOk()->assertDontSee('CATALOG-DETAILS-SECRET', false);
    foreach ($items as $item) {
        $response->assertSee(
            'href="'.e(route('user.barang.detail', ['barang' => $item->getKey()])).'"',
            false,
        );
    }
});
