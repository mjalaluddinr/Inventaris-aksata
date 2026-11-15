<?php

use App\Models\Barang;
use App\Models\DetailPeminjaman;
use App\Models\Kategori;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('history keeps the item name after its inventory record is soft deleted', function () {
    $borrower = User::query()->create([
        'username' => 'history-deleted-item',
        'first_name' => 'History',
        'last_name' => 'Borrower',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Perangkat Riwayat']);
    $item = Barang::query()->create([
        'kategori_id' => $category->getKey(),
        'kode_barang' => 'HISTORY-DELETED',
        'nama_barang' => 'Kabel Riwayat Tersimpan',
        'stok_total' => 1,
        'stok_tersedia' => 0,
    ]);
    $loan = Peminjaman::query()->create([
        'akun_id' => $borrower->getKey(),
        'kode_peminjaman' => 'PJM-20261112-HISTORY',
        'tanggal_pengajuan' => now(),
    ]);
    DetailPeminjaman::query()->create([
        'peminjaman_id' => $loan->getKey(),
        'barang_id' => $item->getKey(),
        'jumlah_pinjam' => 1,
        'jumlah_kembali' => 1,
        'status' => 'dikembalikan',
    ]);
    $item->delete();

    $this->actingAs($borrower)
        ->get(route('user.history', ['status' => 'finished']))
        ->assertOk()
        ->assertSeeText('Kabel Riwayat Tersimpan')
        ->assertDontSeeText('Barang tidak tersedia');
});

test('history shows the return date instead of the planned date for returned loans', function () {
    $borrower = User::query()->create([
        'username' => 'history-return-date',
        'first_name' => 'History',
        'last_name' => 'Return',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Perangkat Tanggal Kembali']);
    $item = Barang::query()->create([
        'kategori_id' => $category->getKey(),
        'kode_barang' => 'HISTORY-RETURN-DATE',
        'nama_barang' => 'Kabel Tanggal Kembali',
        'stok_total' => 1,
        'stok_tersedia' => 1,
    ]);
    $loan = Peminjaman::query()->create([
        'akun_id' => $borrower->getKey(),
        'kode_peminjaman' => 'PJM-20261112-RETURN-DATE',
        'tanggal_pengajuan' => now(),
        'tanggal_rencana_kembali' => '2026-10-25',
    ]);
    DetailPeminjaman::query()->create([
        'peminjaman_id' => $loan->getKey(),
        'barang_id' => $item->getKey(),
        'jumlah_pinjam' => 1,
        'jumlah_kembali' => 1,
        'status' => 'dikembalikan',
        'tanggal_kembali' => '2026-10-18 12:00:00',
        'updated_at' => '2026-10-18 12:00:00',
    ]);

    $this->actingAs($borrower)
        ->get(route('user.history', ['status' => 'finished']))
        ->assertOk()
        ->assertSeeText('Tanggal dikembalikan: 18 Oct 2026')
        ->assertSeeText('Selesai: Diajukan')
        ->assertSeeText('Selesai: Dipinjam')
        ->assertSeeText('Selesai: Dikembalikan')
        ->assertDontSeeText('Tahap saat ini: Dikembalikan')
        ->assertSee('mx-auto grid max-w-lg grid-cols-3', false)
        ->assertDontSeeText('Tanggal rencana kembali: 25 Oct 2026');
});

test('history shows the cancellation timestamp instead of the planned date for cancelled loans', function () {
    $this->travelTo('2026-11-02 12:00:00');

    $borrower = User::query()->create([
        'username' => 'history-cancel-date',
        'first_name' => 'History',
        'last_name' => 'Cancel',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Perangkat Tanggal Batal']);
    $item = Barang::query()->create([
        'kategori_id' => $category->getKey(),
        'kode_barang' => 'HISTORY-CANCEL-DATE',
        'nama_barang' => 'Kabel Tanggal Batal',
        'stok_total' => 1,
        'stok_tersedia' => 1,
    ]);
    $loan = Peminjaman::query()->create([
        'akun_id' => $borrower->getKey(),
        'kode_peminjaman' => 'PJM-20261112-CANCEL-DATE',
        'tanggal_pengajuan' => now(),
        'tanggal_rencana_kembali' => '2026-11-20',
    ]);
    DetailPeminjaman::query()->create([
        'peminjaman_id' => $loan->getKey(),
        'barang_id' => $item->getKey(),
        'jumlah_pinjam' => 1,
        'jumlah_kembali' => 0,
        'status' => 'dibatalkan',
    ]);

    $this->actingAs($borrower)
        ->get(route('user.history', ['status' => 'finished']))
        ->assertOk()
        ->assertSeeText('Tanggal dibatalkan: 02 Nov 2026')
        ->assertDontSeeText('Tanggal rencana kembali: 20 Nov 2026');
});

test('history labels a rejection date as the date the loan was rejected', function () {
    $this->travelTo('2026-11-03 12:00:00');

    $borrower = User::query()->create([
        'username' => 'history-reject-date',
        'first_name' => 'History',
        'last_name' => 'Reject',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);
    $category = Kategori::query()->create(['nama_kategori' => 'Perangkat Tanggal Tolak']);
    $item = Barang::query()->create([
        'kategori_id' => $category->getKey(),
        'kode_barang' => 'HISTORY-REJECT-DATE',
        'nama_barang' => 'Kabel Tanggal Tolak',
        'stok_total' => 1,
        'stok_tersedia' => 1,
    ]);
    $loan = Peminjaman::query()->create([
        'akun_id' => $borrower->getKey(),
        'kode_peminjaman' => 'PJM-20261112-REJECT-DATE',
        'tanggal_pengajuan' => now(),
        'tanggal_rencana_kembali' => '2026-11-20',
    ]);
    DetailPeminjaman::query()->create([
        'peminjaman_id' => $loan->getKey(),
        'barang_id' => $item->getKey(),
        'jumlah_pinjam' => 1,
        'jumlah_kembali' => 0,
        'status' => 'ditolak',
    ]);

    $this->actingAs($borrower)
        ->get(route('user.history', ['status' => 'finished']))
        ->assertOk()
        ->assertSeeText('Tanggal ditolak: 03 Nov 2026')
        ->assertDontSeeText('Tanggal rencana kembali: 20 Nov 2026');
});

test('history search matches loan purpose and current or deleted item names within the borrowers own history', function () {
    $createBorrower = static function (string $username): User {
        return User::query()->create([
            'username' => $username,
            'first_name' => 'History',
            'last_name' => 'Search',
            'password_hash' => 'test-password',
            'role' => 'peminjam',
            'is_active' => true,
        ]);
    };
    $borrower = $createBorrower('history-search-owner');
    $otherBorrower = $createBorrower('history-search-other');
    $category = Kategori::query()->create(['nama_kategori' => 'Perangkat Pencarian']);
    $createItem = static function (string $code, string $name) use ($category): Barang {
        return Barang::query()->create([
            'kategori_id' => $category->getKey(),
            'kode_barang' => $code,
            'nama_barang' => $name,
            'stok_total' => 1,
            'stok_tersedia' => 0,
        ]);
    };
    $createLoan = static function (User $owner, Barang $item, string $code, string $purpose): Peminjaman {
        $loan = Peminjaman::query()->create([
            'akun_id' => $owner->getKey(),
            'kode_peminjaman' => $code,
            'tanggal_pengajuan' => now(),
            'keperluan' => $purpose,
        ]);
        DetailPeminjaman::query()->create([
            'peminjaman_id' => $loan->getKey(),
            'barang_id' => $item->getKey(),
            'jumlah_pinjam' => 1,
            'jumlah_kembali' => 1,
            'status' => 'dikembalikan',
        ]);

        return $loan;
    };

    $deletedItem = $createItem('HISTORY-SEARCH-DELETED', 'Kamera Arsip');
    $createLoan($borrower, $deletedItem, 'PJM-HISTORY-SEARCH-ITEM', 'Praktikum');
    $deletedItem->delete();

    $createLoan(
        $borrower,
        $createItem('HISTORY-SEARCH-PURPOSE', 'Tripod Praktik'),
        'PJM-HISTORY-SEARCH-PURPOSE',
        'Dokumentasi Kamera',
    );
    $createLoan(
        $borrower,
        $createItem('HISTORY-SEARCH-OTHER', 'Mikroskop'),
        'PJM-HISTORY-SEARCH-OTHER',
        'Praktikum biologi',
    );
    $createLoan(
        $otherBorrower,
        $createItem('HISTORY-SEARCH-FOREIGN', 'Kamera Unit Lain'),
        'PJM-HISTORY-SEARCH-FOREIGN',
        'Kegiatan',
    );

    $this->actingAs($borrower)
        ->get(route('user.history', ['status' => 'finished', 'q' => 'Kamera']))
        ->assertOk()
        ->assertSeeText('Kamera Arsip')
        ->assertSeeText('Tripod Praktik')
        ->assertDontSeeText('Mikroskop')
        ->assertDontSeeText('Kamera Unit Lain')
        ->assertDontSeeText('Dikonfirmasi')
        ->assertSee('value="Kamera"', false)
        ->assertSee('href="'.e(route('user.history', ['status' => 'active', 'q' => 'Kamera'])).'"', false);
});

test('history search escapes search text before rendering it', function () {
    $borrower = User::query()->create([
        'username' => 'history-search-escaped',
        'first_name' => 'History',
        'last_name' => 'Escaped',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);
    $searchTerm = '<img src=x onerror=alert(1)>';

    $this->actingAs($borrower)
        ->get(route('user.history', ['q' => $searchTerm]))
        ->assertOk()
        ->assertDontSee($searchTerm, false)
        ->assertSee(e($searchTerm), false);
});
