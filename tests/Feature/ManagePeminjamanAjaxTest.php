<?php

use App\Models\Barang;
use App\Models\DetailPeminjaman;
use App\Models\Kategori;
use App\Models\Peminjaman;
use App\Models\User;
use App\Services\RedisNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;

uses(LazilyRefreshDatabase::class);

function createLoanActionUsers(): array
{
    $admin = User::query()->create([
        'username' => 'admin-loan-actions',
        'first_name' => 'Admin',
        'last_name' => 'Loan',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);

    $borrower = User::query()->create([
        'username' => 'borrower-loan-actions',
        'first_name' => 'Borrower',
        'last_name' => 'Loan',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);

    return [$admin, $borrower];
}

function createLoanActionItem(): Barang
{
    $category = Kategori::query()->create(['nama_kategori' => 'Peralatan Loan']);

    return Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-LOAN-ACTION',
        'nama_barang' => 'Kabel Loan',
        'stok_total' => 2,
        'stok_tersedia' => 0,
    ]);
}

function createLoanAction(Peminjaman $loan, Barang $item, string $status): DetailPeminjaman
{
    return DetailPeminjaman::query()->create([
        'peminjaman_id' => $loan->peminjaman_id,
        'barang_id' => $item->barang_id,
        'jumlah_pinjam' => 2,
        'jumlah_kembali' => 0,
        'status' => $status,
    ]);
}

it('confirms a loan through JSON and keeps the pending tab selected', function () {
    [$admin, $borrower] = createLoanActionUsers();
    $item = createLoanActionItem();
    $loan = Peminjaman::query()->create([
        'akun_id' => $borrower->akun_id,
        'kode_peminjaman' => 'PJM-20261112-CONFIRM',
        'tanggal_pengajuan' => now(),
    ]);
    $detail = createLoanAction($loan, $item, 'pending');

    $this->mock(RedisNotificationService::class, function (MockInterface $mock) use ($borrower): void {
        $mock->shouldReceive('notifyUser')
            ->once()
            ->withArgs(fn (User $recipient, string $message): bool => $recipient->is($borrower)
                && $message === 'Pengajuan peminjaman Anda telah disetujui admin dan kini berstatus dipinjam.');
    });

    $this->actingAs($admin)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('admin.peminjaman.confirm', $loan), ['tab' => 'pending'])
        ->assertOk()
        ->assertJsonPath('tab', 'pending');

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'status' => 'dipinjam',
    ]);
});

it('rejects a loan through JSON and keeps the pending tab selected', function () {
    [$admin, $borrower] = createLoanActionUsers();
    $item = createLoanActionItem();
    $loan = Peminjaman::query()->create([
        'akun_id' => $borrower->akun_id,
        'kode_peminjaman' => 'PJM-20261112-REJECT',
        'tanggal_pengajuan' => now(),
    ]);
    $detail = createLoanAction($loan, $item, 'pending');

    $this->mock(RedisNotificationService::class, function (MockInterface $mock): void {
        $mock->shouldReceive('notifyUser')->once();
    });

    $this->actingAs($admin)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('admin.peminjaman.reject', $loan), [
            'reason' => 'Stok perlu diperiksa.',
            'tab' => 'pending',
        ])
        ->assertOk()
        ->assertJsonPath('tab', 'pending');

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'status' => 'ditolak',
    ]);
});

it('records a return through JSON and keeps the borrowed tab selected when complete', function () {
    [$admin, $borrower] = createLoanActionUsers();
    $item = createLoanActionItem();
    $loan = Peminjaman::query()->create([
        'akun_id' => $borrower->akun_id,
        'kode_peminjaman' => 'PJM-20261112-RETURN',
        'tanggal_pengajuan' => now(),
    ]);
    $detail = createLoanAction($loan, $item, 'dipinjam');

    $this->mock(RedisNotificationService::class, function (MockInterface $mock) use ($borrower): void {
        $mock->shouldReceive('notifyLoanProcessors')
            ->once()
            ->with('Seluruh barang peminjaman dari Borrower Loan telah dikembalikan dan dicatat.');
        $mock->shouldReceive('clearLoanReminderMarkers')->once();
        $mock->shouldReceive('notifyUser')
            ->once()
            ->withArgs(fn (User $recipient, string $message): bool => $recipient->is($borrower)
                && $message === 'Seluruh barang peminjaman Anda telah dikembalikan dan dicatat.');
    });

    $this->actingAs($admin)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('admin.peminjaman.return', $loan), [
            'quantities' => [$detail->detail_peminjaman_id => 2],
            'conditions' => [$detail->detail_peminjaman_id => 'baik'],
            'tab' => 'borrowed',
        ])
        ->assertOk()
        ->assertJsonPath('tab', 'borrowed');

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'status' => 'dikembalikan',
        'jumlah_kembali' => 2,
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $item->barang_id,
        'stok_tersedia' => 2,
    ]);
});

it('explains that a partial return still has items outstanding in notifications', function () {
    [$admin, $borrower] = createLoanActionUsers();
    $item = createLoanActionItem();
    $loan = Peminjaman::query()->create([
        'akun_id' => $borrower->akun_id,
        'kode_peminjaman' => 'PJM-20261112-PARTIAL-RETURN',
        'tanggal_pengajuan' => now(),
    ]);
    $detail = createLoanAction($loan, $item, 'dipinjam');

    $this->mock(RedisNotificationService::class, function (MockInterface $mock) use ($borrower): void {
        $mock->shouldReceive('notifyLoanProcessors')
            ->once()
            ->with('Pengembalian barang dari Borrower Loan telah dicatat sebagian. Masih ada barang yang belum dikembalikan.');
        $mock->shouldReceive('notifyUser')
            ->once()
            ->withArgs(fn (User $recipient, string $message): bool => $recipient->is($borrower)
                && $message === 'Pengembalian sebagian barang pinjaman Anda telah dicatat. Masih ada barang yang harus dikembalikan.');
    });

    $this->actingAs($admin)
        ->withHeaders([
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->post(route('admin.peminjaman.return', $loan), [
            'quantities' => [$detail->detail_peminjaman_id => 1],
            'conditions' => [$detail->detail_peminjaman_id => 'baik'],
            'tab' => 'borrowed',
        ])
        ->assertOk()
        ->assertJsonPath('tab', 'borrowed');

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'status' => 'dipinjam',
        'jumlah_kembali' => 1,
    ]);
});
