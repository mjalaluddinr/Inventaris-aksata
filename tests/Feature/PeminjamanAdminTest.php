<?php

use App\Exports\PeminjamanExport;
use App\Models\Barang;
use App\Models\DetailPeminjaman;
use App\Models\Kategori;
use App\Models\LogMutasiBarang;
use App\Models\LokasiBarang;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createPeminjamanAdminTestUser(string $role = 'admin'): User
{
    return User::query()->create([
        'username' => Str::lower(Str::random(12)),
        'first_name' => 'Akun',
        'last_name' => 'Uji',
        'password_hash' => 'test-password',
        'role' => $role,
        'must_change_password' => false,
        'is_active' => true,
    ]);
}

/**
 * @return array{Peminjaman, DetailPeminjaman, Barang}
 */
function createPeminjamanAdminTestLoan(string $status = 'pending', int $quantity = 2, ?string $code = null): array
{
    $borrower = createPeminjamanAdminTestUser('peminjam');
    $category = Kategori::query()->create(['nama_kategori' => 'Kategori Uji '.Str::random(6)]);
    $barang = Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => 'BRG-'.Str::upper(Str::random(6)),
        'nama_barang' => 'Barang Uji',
        'satuan' => 'pcs',
        'stok_total' => 5,
        'stok_tersedia' => 5 - $quantity,
        'kondisi' => 'baru',
        'kelayakan' => 'layak',
    ]);
    $loan = Peminjaman::query()->create([
        'akun_id' => $borrower->akun_id,
        'kode_peminjaman' => $code ?? Str::upper(Str::random(26)),
        'jenis_peminjaman' => 'praktik',
        'keperluan' => 'Praktik kelas',
        'tanggal_pengajuan' => now(),
        'tanggal_rencana_kembali' => now()->addDays(3)->toDateString(),
    ]);
    $detail = $loan->detailPeminjamans()->create([
        'barang_id' => $barang->barang_id,
        'jumlah_pinjam' => $quantity,
        'jumlah_kembali' => 0,
        'status' => $status,
    ]);

    return [$loan, $detail, $barang];
}

it('shows the designed loan tabs and scanner to users allowed to process loans', function () {
    $admin = createPeminjamanAdminTestUser();
    [$loan, , $barang] = createPeminjamanAdminTestLoan('dipinjam');

    $response = $this->actingAs($admin)->get(route('admin.peminjaman', ['tab' => 'borrowed']));

    $response
        ->assertOk()
        ->assertSee('Menunggu Konfirmasi')
        ->assertSee('Riwayat Selesai')
        ->assertSee('Scan QR')
        ->assertSee('aria-label="Buka navigasi"', false)
        ->assertSee('aria-controls="admin-sidebar"', false)
        ->assertSee('lg:hidden', false)
        ->assertSee('Laporan Peminjaman per Periode')
        ->assertSee('Arahkan Kamera Ke Kode QR')
        ->assertSee('data-endpoint="/admin/peminjaman/scan"', false)
        ->assertSee('min-w-[1100px]', false)
        ->assertSee('whitespace-nowrap', false)
        ->assertDontSee('table-fixed', false)
        ->assertSee('min-w-24', false)
        ->assertDontSee('max-w-[1600px]', false)
        ->assertDontSee('Tampilkan')
        ->assertDontSee('QR yang valid akan langsung mengonfirmasi peminjaman.')
        ->assertSee($loan->kode_peminjaman)
        ->assertSee($barang->nama_barang)
        ->assertSee('Jumlah')
        ->assertSee('name="quantities[', false)
        ->assertDontSee('(Peminjam)')
        ->assertSee('Proses Pengembalian');
});

it('shows only report years that contain loan data and defaults to the latest year', function () {
    $this->travelTo('2024-06-15 09:00:00');
    createPeminjamanAdminTestLoan();
    $this->travelTo('2026-03-20 09:00:00');
    createPeminjamanAdminTestLoan();
    $admin = createPeminjamanAdminTestUser();

    $response = $this->actingAs($admin)->get(route('admin.peminjaman'));

    $response
        ->assertOk()
        ->assertSee('value="2026" selected', false)
        ->assertSee('value="2024"', false)
        ->assertDontSee('value="2025"', false);
});

it('disables the report year selector when there are no loan years to choose', function () {
    $admin = createPeminjamanAdminTestUser();

    $response = $this->actingAs($admin)->get(route('admin.peminjaman'));

    $response
        ->assertOk()
        ->assertSee('id="loan-report-year"', false)
        ->assertSee('disabled', false)
        ->assertDontSee('<option value=', false);
});

it('returns only the loan list fragment for ajax tab navigation', function () {
    $admin = createPeminjamanAdminTestUser();
    [$loan] = createPeminjamanAdminTestLoan();

    $response = $this->actingAs($admin)->get(
        route('admin.peminjaman', ['tab' => 'pending']),
        ['X-Requested-With' => 'XMLHttpRequest'],
    );

    $response
        ->assertOk()
        ->assertSee('Menunggu Konfirmasi')
        ->assertSee($loan->kode_peminjaman)
        ->assertDontSee('<html', false);
    $response
        ->assertSee('mdi:check', false)
        ->assertSee('mdi:close', false)
        ->assertSee('bg-rose-600', false)
        ->assertSee('Alasan penolakan')
        ->assertSee('name="reason"', false)
        ->assertDontSee('onsubmit="return confirm', false);
});

it('confirms a pending loan when its QR code is scanned', function () {
    [$loan, $detail, $barang] = createPeminjamanAdminTestLoan(code: 'PJM-20261027-0001');
    $admin = createPeminjamanAdminTestUser();

    $response = $this->actingAs($admin)->postJson(route('admin.peminjaman.scan'), [
        'code' => $loan->kode_peminjaman,
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('message', 'QR berhasil dipindai. Peminjaman telah dikonfirmasi.')
        ->assertJsonPath('loan_id', $loan->peminjaman_id)
        ->assertJsonPath('redirect_url', '/admin/peminjaman?tab=borrowed')
        ->assertSessionHas('success', 'Konfirmasi berhasil.');
    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'status' => 'dipinjam',
    ]);
    $this->assertDatabaseHas('peminjaman', [
        'peminjaman_id' => $loan->peminjaman_id,
        'diproses_akun_id' => $admin->akun_id,
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 3,
    ]);
    expect($loan->fresh()->tanggal_pinjam)->not->toBeNull();
});

it('confirms a pending loan and shows the result as a toast', function () {
    [$loan, $detail] = createPeminjamanAdminTestLoan();
    $admin = createPeminjamanAdminTestUser();

    $this->actingAs($admin)
        ->post(route('admin.peminjaman.confirm', $loan))
        ->assertRedirect(route('admin.peminjaman', ['tab' => 'borrowed']))
        ->assertSessionHas('success', 'Konfirmasi berhasil.');

    $this->get(route('admin.peminjaman', ['tab' => 'borrowed']))
        ->assertOk()
        ->assertSee('data-feedback-message="Konfirmasi berhasil."', false)
        ->assertDontSee('Peminjaman berhasil dikonfirmasi.')
        ->assertDontSee('Konfirmasi berhasil.</span>');

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'status' => 'dipinjam',
    ]);
});

it('does not confirm a loan with a pending cancellation request when its QR is scanned', function () {
    [$loan, $detail] = createPeminjamanAdminTestLoan();
    $loan->catatan = Peminjaman::CANCELLATION_REQUEST_MARKER;
    $loan->save();
    $admin = createPeminjamanAdminTestUser();

    $this->actingAs($admin)
        ->postJson(route('admin.peminjaman.scan'), ['code' => $loan->kode_peminjaman])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('peminjaman');

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'status' => 'pending',
    ]);
});

it('rejects invalid and already processed QR codes without changing stock', function () {
    [$loan, $detail, $barang] = createPeminjamanAdminTestLoan('dipinjam');
    $admin = createPeminjamanAdminTestUser();

    $this->actingAs($admin)
        ->postJson(route('admin.peminjaman.scan'), ['code' => 'not-a-loan-code'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');

    $this->postJson(route('admin.peminjaman.scan'), ['code' => $loan->kode_peminjaman])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('peminjaman');

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'status' => 'dipinjam',
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 3,
    ]);
});

it('restores reserved stock when an admin rejects a pending loan', function () {
    [$loan, $detail, $barang] = createPeminjamanAdminTestLoan();
    $admin = createPeminjamanAdminTestUser();
    collect([2, 3])->each(function (int $totalStock) use ($loan, $detail, $barang): void {
        $room = Ruangan::query()->create([
            'kode_ruangan' => 'RUANG-'.Str::upper(Str::random(8)),
            'nama_ruangan' => 'Ruang Uji',
        ]);
        $location = LokasiBarang::query()->create([
            'barang_id' => $barang->barang_id,
            'ruangan_id' => $room->ruangan_id,
            'stok_total' => $totalStock,
            'stok_tersedia' => $totalStock - 1,
        ]);
        LogMutasiBarang::query()->create([
            'barang_id' => $barang->barang_id,
            'ruangan_id' => $room->ruangan_id,
            'akun_id' => $loan->akun_id,
            'oleh_akun_id' => $loan->akun_id,
            'detail_peminjaman_id' => $detail->detail_peminjaman_id,
            'tipe' => 'dipinjam',
            'jumlah' => 1,
            'stok_sebelum' => $location->stok_tersedia,
            'stok_sesudah' => $location->stok_tersedia - 1,
            'keterangan' => 'Reservasi QR peminjaman',
            'created_at' => now(),
        ]);
    });

    $this->actingAs($admin)
        ->post(route('admin.peminjaman.reject', $loan), [
            'reason' => 'Barang diperlukan untuk kegiatan lain.',
        ])
        ->assertRedirect(route('admin.peminjaman', ['tab' => 'pending']))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'status' => 'ditolak',
        'catatan_kembali' => 'Barang diperlukan untuk kegiatan lain.',
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 5,
    ]);
    expect(
        LokasiBarang::query()->orderBy('stok_total')->pluck('stok_tersedia')->all(),
    )->toBe([2, 3]);
    $this->assertDatabaseHas('log_mutasi_barang', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'tipe' => 'dikembalikan',
        'oleh_akun_id' => $admin->akun_id,
    ]);
});

it('records a good return and restores stock to availability', function () {
    [$loan, $detail, $barang] = createPeminjamanAdminTestLoan('dipinjam');
    $admin = createPeminjamanAdminTestUser();

    $this->actingAs($admin)
        ->post(route('admin.peminjaman.return', $loan), [
            'quantities' => [$detail->detail_peminjaman_id => 2],
            'conditions' => [$detail->detail_peminjaman_id => 'baik'],
            'note' => 'Diterima lengkap',
        ])
        ->assertRedirect(route('admin.peminjaman', ['tab' => 'history']))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'jumlah_kembali' => 2,
        'status' => 'dikembalikan',
        'kondisi_kembali' => 'baik',
        'catatan_kembali' => 'Diterima lengkap',
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 5,
    ]);
    $this->assertDatabaseHas('log_mutasi_barang', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'tipe' => 'dikembalikan',
        'oleh_akun_id' => $admin->akun_id,
    ]);
});

it('does not make lost items available and reduces total stock', function () {
    [$loan, $detail, $barang] = createPeminjamanAdminTestLoan('dipinjam');
    $admin = createPeminjamanAdminTestUser();

    $this->actingAs($admin)
        ->post(route('admin.peminjaman.return', $loan), [
            'quantities' => [$detail->detail_peminjaman_id => 2],
            'conditions' => [$detail->detail_peminjaman_id => 'hilang'],
        ])
        ->assertRedirect(route('admin.peminjaman', ['tab' => 'history']));

    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_total' => 3,
        'stok_tersedia' => 3,
    ]);
    $this->assertDatabaseHas('log_mutasi_barang', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'tipe' => 'ditarik',
        'oleh_akun_id' => $admin->akun_id,
    ]);
});

it('allows an admin to return borrowed items in multiple batches', function () {
    [$loan, $detail, $barang] = createPeminjamanAdminTestLoan('dipinjam');
    $admin = createPeminjamanAdminTestUser();
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'RUANG-'.Str::upper(Str::random(8)),
        'nama_ruangan' => 'Ruang Uji',
    ]);
    $location = LokasiBarang::query()->create([
        'barang_id' => $barang->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'stok_total' => 5,
        'stok_tersedia' => 3,
    ]);
    foreach ([1, 2] as $reservationNumber) {
        LogMutasiBarang::query()->create([
            'barang_id' => $barang->barang_id,
            'ruangan_id' => $room->ruangan_id,
            'akun_id' => $loan->akun_id,
            'oleh_akun_id' => $loan->akun_id,
            'detail_peminjaman_id' => $detail->detail_peminjaman_id,
            'tipe' => 'dipinjam',
            'jumlah' => 1,
            'stok_sebelum' => 6 - $reservationNumber,
            'stok_sesudah' => 5 - $reservationNumber,
            'keterangan' => 'Reservasi QR peminjaman',
            'created_at' => now()->addSeconds($reservationNumber),
        ]);
    }

    $this->actingAs($admin)
        ->post(route('admin.peminjaman.return', $loan), [
            'quantities' => [$detail->detail_peminjaman_id => 1],
            'conditions' => [$detail->detail_peminjaman_id => 'baik'],
        ])
        ->assertRedirect(route('admin.peminjaman', ['tab' => 'borrowed']));

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'jumlah_kembali' => 1,
        'status' => 'dipinjam',
        'tanggal_kembali' => null,
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 4,
    ]);
    $this->assertDatabaseHas('lokasi_barang', [
        'lokasi_barang_id' => $location->lokasi_barang_id,
        'stok_tersedia' => 4,
    ]);
    $this->assertDatabaseHas('log_mutasi_barang', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'tipe' => 'dikembalikan',
        'jumlah' => 1,
        'oleh_akun_id' => $admin->akun_id,
    ]);

    $this->post(route('admin.peminjaman.return', $loan), [
        'quantities' => [$detail->detail_peminjaman_id => 1],
        'conditions' => [$detail->detail_peminjaman_id => 'baik'],
    ])->assertRedirect(route('admin.peminjaman', ['tab' => 'history']));

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'jumlah_kembali' => 2,
        'status' => 'dikembalikan',
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 5,
    ]);
    $this->assertDatabaseHas('lokasi_barang', [
        'lokasi_barang_id' => $location->lokasi_barang_id,
        'stok_tersedia' => 5,
    ]);
    expect(
        LogMutasiBarang::query()
            ->where('detail_peminjaman_id', $detail->detail_peminjaman_id)
            ->where('tipe', 'dikembalikan')
            ->sum('jumlah'),
    )->toBe(2);
});

it('rejects a return quantity above the remaining borrowed amount', function () {
    [$loan, $detail, $barang] = createPeminjamanAdminTestLoan('dipinjam');
    $admin = createPeminjamanAdminTestUser();

    $this->actingAs($admin)
        ->post(route('admin.peminjaman.return', $loan), [
            'quantities' => [$detail->detail_peminjaman_id => 3],
            'conditions' => [$detail->detail_peminjaman_id => 'baik'],
        ])
        ->assertRedirect()
        ->assertSessionHasErrors('quantities');

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'jumlah_kembali' => 0,
        'status' => 'dipinjam',
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 3,
    ]);
});

it('requires a reason before an admin can reject a pending loan', function () {
    [$loan, $detail, $barang] = createPeminjamanAdminTestLoan();
    $admin = createPeminjamanAdminTestUser();

    $this->actingAs($admin)
        ->post(route('admin.peminjaman.reject', $loan))
        ->assertRedirect()
        ->assertSessionHasErrors('reason');

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 3,
    ]);
});

it('downloads a yearly Excel report and escapes formula-like cell values', function () {
    $admin = createPeminjamanAdminTestUser();

    $this->actingAs($admin)
        ->get(route('admin.peminjaman.export', ['year' => now()->year]))
        ->assertOk()
        ->assertDownload('laporan-peminjaman-'.now()->year.'.xlsx');

    $export = new PeminjamanExport(collect([['=HYPERLINK("https://example.test")']]));

    expect($export->collection()->first())->toBe(["'=HYPERLINK(\"https://example.test\")"]);
});

it('denies loan processing to an account without the required permission', function () {
    $laboran = createPeminjamanAdminTestUser('laboran');

    $this->actingAs($laboran)
        ->get(route('admin.peminjaman'))
        ->assertForbidden();

    $this->postJson(route('admin.peminjaman.scan'), ['code' => Str::upper(Str::random(26))])
        ->assertForbidden();
});
