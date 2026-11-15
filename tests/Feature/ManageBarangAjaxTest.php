<?php

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('creates an item with a JSON response when saving through AJAX', function () {
    $admin = User::query()->create([
        'username' => 'admin-barang-ajax',
        'first_name' => 'Admin',
        'last_name' => 'Barang',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Peralatan']);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'LAB-AJAX',
        'nama_ruangan' => 'Laboratorium',
    ]);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('admin.barang.store'), [
            'kategori_id' => $category->kategori_id,
            'nama_barang' => 'Kabel HDMI',
            'satuan' => 'PCS',
            'stok_total' => 4,
            'stok_tersedia' => 4,
            'kondisi' => 'baru',
            'kelayakan' => 'layak',
            'locations' => [
                [
                    'ruangan_id' => $room->ruangan_id,
                    'lemari_id' => '',
                    'toolbox_id' => '',
                    'stok_total' => 4,
                    'stok_tersedia' => 4,
                ],
            ],
        ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Barang Kabel HDMI berhasil ditambahkan.');

    $barang = Barang::query()->where('nama_barang', 'Kabel HDMI')->firstOrFail();

    $this->assertDatabaseHas('lokasi_barang', [
        'barang_id' => $barang->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'stok_total' => 4,
        'stok_tersedia' => 4,
    ]);
});

it('returns JSON validation errors without creating an item when its room is missing', function () {
    $admin = User::query()->create([
        'username' => 'admin-barang-invalid-ajax',
        'first_name' => 'Admin',
        'last_name' => 'Barang',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Peralatan']);

    $response = $this->actingAs($admin)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('admin.barang.store'), [
            'kategori_id' => $category->kategori_id,
            'nama_barang' => 'Kabel HDMI',
            'satuan' => 'PCS',
            'stok_total' => 4,
            'stok_tersedia' => 4,
            'kondisi' => 'baru',
            'kelayakan' => 'layak',
            'locations' => [
                [
                    'ruangan_id' => '',
                    'lemari_id' => '',
                    'toolbox_id' => '',
                    'stok_total' => 4,
                    'stok_tersedia' => 4,
                ],
            ],
        ]);

    $response->assertUnprocessable()
        ->assertInvalid([
            'locations.0.ruangan_id' => 'Ruangan wajib dipilih untuk setiap lokasi.',
        ]);

    $this->assertDatabaseMissing('barang', ['nama_barang' => 'Kabel HDMI']);
});

it('keeps the admin session active after multiple AJAX item deletions', function () {
    $admin = User::query()->create([
        'username' => 'admin-delete-session',
        'first_name' => 'Admin',
        'last_name' => 'Delete Session',
        'password_hash' => 'delete-session-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Peralatan']);
    $items = collect(['Kabel HDMI', 'Mouse', 'Keyboard'])->map(
        fn (string $name, int $index): Barang => Barang::query()->create([
            'kategori_id' => $category->kategori_id,
            'kode_barang' => sprintf('BRG-SESSION-%02d', $index + 1),
            'nama_barang' => $name,
            'satuan' => 'PCS',
            'stok_total' => 1,
            'stok_tersedia' => 1,
        ]),
    );

    $this->post(route('login'), [
        'username' => 'admin-delete-session',
        'password' => 'delete-session-password',
    ])->assertRedirectToRoute('admin.dashboard');
    $this->assertAuthenticatedAs($admin);

    foreach ($items as $item) {
        $this->deleteJson(route('admin.barang.destroy', $item))
            ->assertOk();
        $this->assertAuthenticatedAs($admin);
    }

    $this->get(route('admin.barang'))->assertOk();
    $this->assertAuthenticatedAs($admin);
});
