<?php

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\User;
use App\Services\RedisNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Mockery\MockInterface;

uses(LazilyRefreshDatabase::class);

test('cart sync returns current active inventory and excludes soft deleted items', function () {
    $borrower = User::query()->create([
        'username' => 'cart-sync-borrower',
        'first_name' => 'Cart',
        'last_name' => 'Borrower',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Perangkat Cart']);
    $activeItem = Barang::query()->create([
        'kategori_id' => $category->getKey(),
        'kode_barang' => 'CART-ACTIVE',
        'nama_barang' => 'Nama Barang Lama',
        'stok_total' => 8,
        'stok_tersedia' => 7,
    ]);
    $activeItem->update([
        'nama_barang' => 'Nama Barang Terbaru',
        'stok_tersedia' => 5,
    ]);
    $deletedItem = Barang::query()->create([
        'kategori_id' => $category->getKey(),
        'kode_barang' => 'CART-DELETED',
        'nama_barang' => 'Barang Dihapus',
        'stok_total' => 3,
        'stok_tersedia' => 3,
    ]);
    $deletedItem->delete();

    $response = $this->actingAs($borrower)->getJson(route('user.cart.items', [
        'ids' => [$activeItem->getKey(), $deletedItem->getKey()],
    ]));

    $response
        ->assertOk()
        ->assertJsonPath('items.0.id', $activeItem->getKey())
        ->assertJsonPath('items.0.name', 'Nama Barang Terbaru')
        ->assertJsonPath('items.0.category', 'Perangkat Cart')
        ->assertJsonPath('items.0.stock', 5);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    expect($response->json('items'))->toHaveCount(1);
});

test('peminjaman rejects a soft deleted item id', function () {
    $borrower = User::query()->create([
        'username' => 'cart-deleted-borrower',
        'first_name' => 'Deleted',
        'last_name' => 'Borrower',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Kategori Peminjaman']);
    $deletedItem = Barang::query()->create([
        'kategori_id' => $category->getKey(),
        'kode_barang' => 'LOAN-DELETED',
        'nama_barang' => 'Barang Dihapus',
        'stok_total' => 2,
        'stok_tersedia' => 2,
    ]);
    $deletedItem->delete();

    $response = $this->actingAs($borrower)->postJson(route('user.peminjaman.store'), [
        'items' => [
            ['barang_id' => $deletedItem->getKey(), 'quantity' => 1],
        ],
        'keperluan' => 'Pengujian barang dihapus',
        'jenis_peminjaman' => 'harian',
        'tanggal_rencana_kembali' => now()->toDateString(),
    ]);

    $response->assertInvalid([
        'items.0.barang_id' => 'Barang yang dipilih sudah tidak tersedia di katalog. Hapus lalu pilih kembali dari katalog.',
    ]);
});

test('successful loan submission returns toast data without a redirect or session flash', function () {
    $borrower = User::query()->create([
        'username' => 'loan-success-borrower',
        'first_name' => 'Loan',
        'last_name' => 'Borrower',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Kategori Peminjaman']);
    $item = Barang::query()->create([
        'kategori_id' => $category->getKey(),
        'kode_barang' => 'LOAN-SUCCESS-ITEM',
        'nama_barang' => 'Tripod Peminjaman',
        'stok_total' => 3,
        'stok_tersedia' => 3,
    ]);

    $this->mock(RedisNotificationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('notifyUser')->once();
        $mock->shouldReceive('notifyLoanProcessors')->once();
    });

    $response = $this->actingAs($borrower)->postJson(route('user.peminjaman.store'), [
        'items' => [
            ['barang_id' => $item->getKey(), 'quantity' => 1],
        ],
        'keperluan' => 'Pengujian pengajuan berhasil',
        'jenis_peminjaman' => 'harian',
        'tanggal_rencana_kembali' => now()->addDay()->toDateString(),
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('message', 'Pengajuan peminjaman berhasil dikirim.')
        ->assertJsonPath('submitted_item_ids.0', $item->getKey())
        ->assertJsonMissingPath('redirect_url')
        ->assertSessionMissing('success');

    $this->assertDatabaseHas('detail_peminjaman', [
        'barang_id' => $item->getKey(),
        'jumlah_pinjam' => 1,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $item->getKey(),
        'stok_tersedia' => 2,
    ]);
});

test('cart sync denies users without active borrower access', function () {
    $this->getJson(route('user.cart.items', ['ids' => [Str::uuid()->toString()]]))
        ->assertUnauthorized();

    $admin = User::query()->create([
        'username' => 'cart-sync-admin',
        'first_name' => 'Cart',
        'last_name' => 'Admin',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->getJson(route('user.cart.items', ['ids' => [Str::uuid()->toString()]]))
        ->assertForbidden();
});
