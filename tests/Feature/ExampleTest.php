<?php

use App\Models\Barang;
use App\Models\Kategori;
use App\Models\LokasiBarang;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\User;
use App\Services\RedisNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function createLoanFlowUser(string $role = 'peminjam'): User
{
    return new User([
        'username' => 'loan-flow-test',
        'first_name' => 'Loan',
        'last_name' => 'Test',
        'password_hash' => 'password',
        'role' => $role,
        'is_active' => true,
    ]);
}

function createLoanFlowItem(string $code = 'LOAN-001'): Barang
{
    $category = Kategori::query()->create([
        'nama_kategori' => 'Kategori '.$code,
    ]);

    return Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => $code,
        'nama_barang' => 'Barang '.$code,
        'satuan' => 'unit',
        'stok_total' => 3,
        'stok_tersedia' => 3,
        'kondisi' => 'baru',
        'kelayakan' => 'layak',
    ]);
}

test('the application returns a successful response', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});

it('offers the existing borrowing types without the other option', function () {
    $response = $this->actingAs(createLoanFlowUser())
        ->get(route('user.beranda', ['page' => 'apply']));

    $response->assertOk()
        ->assertSee('<option value="harian"', false)
        ->assertSee('<option value="praktik"', false)
        ->assertSee('<option value="mingguan"', false)
        ->assertSee('<option value="bulanan"', false)
        ->assertSee('<option value="semesteran"', false)
        ->assertSee('<option value="tahunan"', false)
        ->assertSee('@submit.prevent="submitLoanForm($event)"', false)
        ->assertDontSee('Pengajuan belum dapat diproses:', false)
        ->assertDontSee('<option value="lainnya"', false);
});

it('creates a loan with each existing borrowing type', function (string $type) {
    $borrower = createLoanFlowUser();
    $borrower->save();
    $item = createLoanFlowItem();
    $notifications = Mockery::mock(RedisNotificationService::class);
    $notifications->shouldReceive('notifyUser')->once();
    $notifications->shouldReceive('notifyLoanProcessors')->once();
    $this->app->instance(RedisNotificationService::class, $notifications);

    $response = $this->actingAs($borrower)
        ->post(route('user.peminjaman.store'), [
            'items' => [
                ['barang_id' => $item->barang_id, 'quantity' => 1],
            ],
            'jenis_peminjaman' => $type,
            'keperluan' => 'Kegiatan praktik',
            'tanggal_rencana_kembali' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('user.beranda', ['page' => 'history']));

    $loan = Peminjaman::query()->where('akun_id', $borrower->akun_id)->firstOrFail();
    expect($loan->jenis_peminjaman)->toBe($type);
    $this->assertDatabaseHas('detail_peminjaman', [
        'peminjaman_id' => $loan->peminjaman_id,
        'barang_id' => $item->barang_id,
        'jumlah_pinjam' => 1,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('log_mutasi_barang', [
        'barang_id' => $item->barang_id,
        'jumlah' => 1,
        'tipe' => 'dipinjam',
    ]);
    expect($item->fresh()->stok_tersedia)->toBe(2);
})->with([
    'daily' => 'harian',
    'practice' => 'praktik',
    'weekly' => 'mingguan',
    'monthly' => 'bulanan',
    'semesterly' => 'semesteran',
    'yearly' => 'tahunan',
]);

it('returns a JSON success response after persisting an AJAX loan', function () {
    $borrower = createLoanFlowUser();
    $borrower->save();
    $item = createLoanFlowItem('AJAX-001');
    $notifications = Mockery::mock(RedisNotificationService::class);
    $notifications->shouldReceive('notifyUser')->once();
    $notifications->shouldReceive('notifyLoanProcessors')->once();
    $this->app->instance(RedisNotificationService::class, $notifications);

    $response = $this->actingAs($borrower)->postJson(route('user.peminjaman.store'), [
        'items' => [
            ['barang_id' => $item->barang_id, 'quantity' => 1],
        ],
        'jenis_peminjaman' => 'harian',
        'keperluan' => 'Kegiatan praktik',
        'tanggal_rencana_kembali' => now()->toDateString(),
        'clear_submitted_items' => true,
    ]);

    $response->assertCreated()
        ->assertJsonPath('message', 'Pengajuan peminjaman berhasil dikirim.')
        ->assertJsonPath('redirect_url', route('user.beranda', ['page' => 'history'], false))
        ->assertJsonPath('submitted_item_ids.0', $item->barang_id);
    $this->assertDatabaseHas('peminjaman', [
        'akun_id' => $borrower->akun_id,
        'jenis_peminjaman' => 'harian',
    ]);
    $this->assertDatabaseHas('detail_peminjaman', [
        'barang_id' => $item->barang_id,
        'jumlah_pinjam' => 1,
        'status' => 'pending',
    ]);
    expect($item->fresh()->stok_tersedia)->toBe(2);
});

it('returns updated location stock without creating a partial AJAX loan', function () {
    $borrower = createLoanFlowUser();
    $borrower->save();
    $item = createLoanFlowItem('STALE-001');
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'RGN-STALE-001',
        'nama_ruangan' => 'Ruang Stok Terbatas',
    ]);
    LokasiBarang::query()->create([
        'barang_id' => $item->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'stok_total' => 1,
        'stok_tersedia' => 1,
    ]);
    $notifications = Mockery::mock(RedisNotificationService::class);
    $notifications->shouldNotReceive('notifyUser');
    $notifications->shouldNotReceive('notifyLoanProcessors');
    $this->app->instance(RedisNotificationService::class, $notifications);

    $response = $this->actingAs($borrower)->postJson(route('user.peminjaman.store'), [
        'items' => [
            ['barang_id' => $item->barang_id, 'quantity' => 2],
        ],
        'jenis_peminjaman' => 'harian',
        'keperluan' => 'Kegiatan praktik',
        'tanggal_rencana_kembali' => now()->toDateString(),
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('message', 'Stok barang berubah. Periksa jumlah dan kirim ulang pengajuan.')
        ->assertJsonPath('stock.'.$item->barang_id, 1);
    $this->assertDatabaseMissing('peminjaman', [
        'akun_id' => $borrower->akun_id,
    ]);
    $this->assertDatabaseMissing('detail_peminjaman', [
        'barang_id' => $item->barang_id,
    ]);
    expect($item->fresh()->stok_tersedia)->toBe(3);
});

it('shows the return reminder only for active loans and exposes AJAX history filters', function () {
    $borrower = createLoanFlowUser();
    $borrower->save();
    $activeItem = createLoanFlowItem('HISTORY-ACTIVE-001');
    $finishedItem = createLoanFlowItem('HISTORY-FINISHED-001');
    $activeLoan = Peminjaman::query()->create([
        'akun_id' => $borrower->akun_id,
        'kode_peminjaman' => 'PJM-HISTORY-ACTIVE',
        'jenis_peminjaman' => 'harian',
        'keperluan' => 'Peminjaman aktif',
        'tanggal_pengajuan' => now(),
        'tanggal_rencana_kembali' => now()->addDay()->toDateString(),
    ]);
    $activeLoan->detailPeminjamans()->create([
        'barang_id' => $activeItem->barang_id,
        'jumlah_pinjam' => 1,
        'jumlah_kembali' => 0,
        'status' => 'dipinjam',
    ]);
    $finishedLoan = Peminjaman::query()->create([
        'akun_id' => $borrower->akun_id,
        'kode_peminjaman' => 'PJM-HISTORY-FINISHED',
        'jenis_peminjaman' => 'harian',
        'keperluan' => 'Peminjaman selesai',
        'tanggal_pengajuan' => now()->subDay(),
        'tanggal_rencana_kembali' => now()->toDateString(),
    ]);
    $finishedLoan->detailPeminjamans()->create([
        'barang_id' => $finishedItem->barang_id,
        'jumlah_pinjam' => 1,
        'jumlah_kembali' => 1,
        'status' => 'dikembalikan',
    ]);

    $activeResponse = $this->actingAs($borrower)
        ->get(route('user.beranda', ['page' => 'history', 'status' => 'active']));
    $finishedResponse = $this->actingAs($borrower)
        ->get(route('user.beranda', ['page' => 'history', 'status' => 'finished']));

    $activeResponse->assertOk()
        ->assertSee('Pengembalian dilakukan sekaligus, bukan sebagian.', false)
        ->assertSee('data-history-ajax', false)
        ->assertSee('Barang HISTORY-ACTIVE-001');
    $finishedResponse->assertOk()
        ->assertDontSee('Pengembalian dilakukan sekaligus, bukan sebagian.', false)
        ->assertSee('Barang HISTORY-FINISHED-001');
});

it('rejects the removed other borrowing type', function () {
    $response = $this->actingAs(createLoanFlowUser())
        ->post(route('user.peminjaman.store'), [
            'jenis_peminjaman' => 'lainnya',
            'keperluan' => 'Kegiatan praktik',
            'tanggal_rencana_kembali' => now()->toDateString(),
        ]);

    $response->assertSessionHasErrors([
        'jenis_peminjaman' => 'Pilih jenis peminjaman yang tersedia.',
    ]);
});

it('keeps the successful loan response when Redis is unavailable', function () {
    $borrower = createLoanFlowUser();
    $borrower->save();
    $item = createLoanFlowItem('REDIS-001');
    config([
        'database.redis.client' => 'predis',
        'database.redis.cache.host' => '127.0.0.1',
        'database.redis.cache.port' => 1,
        'database.redis.default.host' => '127.0.0.1',
        'database.redis.default.port' => 1,
    ]);

    $response = $this->actingAs($borrower)
        ->post(route('user.peminjaman.store'), [
            'items' => [
                ['barang_id' => $item->barang_id, 'quantity' => 1],
            ],
            'jenis_peminjaman' => 'harian',
            'keperluan' => 'Kegiatan praktik',
            'tanggal_rencana_kembali' => now()->toDateString(),
        ]);

    $response->assertRedirect(route('user.beranda', ['page' => 'history']));
    $this->assertDatabaseHas('peminjaman', [
        'akun_id' => $borrower->akun_id,
        'jenis_peminjaman' => 'harian',
    ]);
});

it('does not expose item units on the home or catalog cards', function () {
    $borrower = createLoanFlowUser();
    $category = Kategori::query()->create([
        'nama_kategori' => 'Kategori Pengujian',
    ]);
    Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'TEST-001',
        'nama_barang' => 'Barang Pengujian',
        'satuan' => 'satuan-rahasia-u1',
        'stok_total' => 3,
        'stok_tersedia' => 3,
        'kondisi' => 'baru',
        'kelayakan' => 'layak',
    ]);

    $homeResponse = $this->actingAs($borrower)
        ->get(route('user.beranda'));
    $catalogResponse = $this->actingAs($borrower)
        ->get(route('user.beranda', ['page' => 'catalog']));

    $homeResponse->assertOk()
        ->assertDontSee('satuan-rahasia-u1');
    $catalogResponse->assertOk()
        ->assertDontSee('satuan-rahasia-u1');
});

it('does not offer or accept the other inventory unit', function () {
    $admin = createLoanFlowUser('admin');
    $page = $this->actingAs($admin)->get(route('admin.barang'));

    $page->assertOk()
        ->assertDontSee('<option value="LAINNYA">', false);

    $response = $this->actingAs($admin)->post(route('admin.barang.store'), [
        'locations' => [
            [
                'ruangan_id' => '00000000-0000-4000-8000-000000000001',
                'stok_total' => 0,
                'stok_tersedia' => 0,
            ],
        ],
        'kategori_id' => '00000000-0000-4000-8000-000000000002',
        'nama_barang' => 'Barang Pengujian',
        'satuan' => 'LAINNYA',
        'stok_total' => 0,
        'stok_tersedia' => 0,
        'kondisi' => 'baru',
        'kelayakan' => 'layak',
    ]);

    $response->assertSessionHasErrors('satuan');
});
