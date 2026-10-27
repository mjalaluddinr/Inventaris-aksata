<?php

use App\Models\Barang;
use App\Models\DetailPeminjaman;
use App\Models\Kategori;
use App\Models\LokasiBarang;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function createLoanTestUser(string $role = 'peminjam'): User
{
    return User::query()->create([
        'username' => Str::lower(Str::random(12)),
        'first_name' => 'Pengguna',
        'last_name' => 'Uji',
        'password_hash' => 'test-password',
        'role' => $role,
        'must_change_password' => false,
        'is_active' => true,
    ]);
}

/**
 * @param  array<int, int>  $locationStocks
 */
function createLoanTestItem(int $totalStock, array $locationStocks = []): Barang
{
    $category = Kategori::query()->create([
        'nama_kategori' => 'Kategori '.Str::random(8),
    ]);

    $barang = Barang::query()->create([
        'kategori_id' => $category->kategori_id,
        'kode_barang' => Str::upper(Str::random(12)),
        'nama_barang' => 'Barang uji',
        'satuan' => 'UNIT',
        'stok_total' => $totalStock,
        'stok_tersedia' => $totalStock,
        'kondisi' => 'baru',
        'kelayakan' => 'layak',
    ]);

    foreach ($locationStocks as $stock) {
        $room = Ruangan::query()->create([
            'kode_ruangan' => Str::upper(Str::random(8)),
            'nama_ruangan' => 'Ruang '.Str::random(6),
        ]);

        LokasiBarang::query()->create([
            'barang_id' => $barang->barang_id,
            'ruangan_id' => $room->ruangan_id,
            'stok_total' => $stock,
            'stok_tersedia' => $stock,
        ]);
    }

    return $barang;
}

function loanRequestData(Barang $barang, int $quantity = 1): array
{
    return [
        'items' => [[
            'barang_id' => $barang->barang_id,
            'quantity' => $quantity,
        ]],
        'keperluan' => 'Praktik kelas',
        'jenis_peminjaman' => 'praktik',
        'tanggal_rencana_kembali' => now()->addDay()->toDateString(),
    ];
}

it('redirects guests away from the borrower portal', function () {
    $this->get(route('user.beranda'))
        ->assertRedirect('/login');
});

it('redirects guests away from cancellation requests', function () {
    $this->post(route('user.peminjaman.cancellation.store', (string) Str::uuid()))
        ->assertRedirect('/login');
});

it('forbids accounts that are not borrowers from the borrower portal', function () {
    $admin = createLoanTestUser('admin');

    $this->actingAs($admin)
        ->get(route('user.beranda'))
        ->assertForbidden();
});

it('creates a pending loan and reserves stock across its locations', function () {
    $this->travelTo('2026-02-10 09:00:00');
    $borrower = createLoanTestUser();
    $barang = createLoanTestItem(5, [2, 3]);

    $response = $this->actingAs($borrower)
        ->post(route('user.peminjaman.store'), loanRequestData($barang, 4));

    $response
        ->assertRedirect(route('user.beranda', ['page' => 'history']))
        ->assertSessionMissing('success')
        ->assertSessionHas('cart_cleared', true);

    $loan = Peminjaman::query()->firstOrFail();
    $detail = DetailPeminjaman::query()->firstOrFail();

    expect($loan->kode_peminjaman)->toBe('PJM-20260210-0001');

    $this->assertDatabaseHas('detail_peminjaman', [
        'detail_peminjaman_id' => $detail->detail_peminjaman_id,
        'peminjaman_id' => $loan->peminjaman_id,
        'barang_id' => $barang->barang_id,
        'jumlah_pinjam' => 4,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 1,
    ]);
    $this->assertDatabaseCount('lokasi_barang', 2);
    $this->assertDatabaseCount('log_mutasi_barang', 2);

    expect(LokasiBarang::query()->orderBy('stok_total')->pluck('stok_tersedia')->all())->toBe([0, 1]);

    $historyResponse = $this->get(route('user.beranda', ['page' => 'history']));

    $historyResponse
        ->assertOk()
        ->assertSee('Pesanan peminjaman')
        ->assertSee('data-loan-qr', false)
        ->assertSee('data-loan-qr="'.$loan->kode_peminjaman.'"', false)
        ->assertDontSee(route('admin.peminjaman'), false)
        ->assertSee('init() {', false)
        ->assertSee('this.isScrolled = window.scrollY > 10;', false)
        ->assertSee("isScrolled ? 'background: linear-gradient(90deg, #008b7a, #00d5a0)' : 'background: transparent'", false)
        ->assertSee("isScrolled ? 'h-20' : 'h-[88px]'", false)
        ->assertSee('pt-[calc(88px+1.5rem)]', false)
        ->assertSee('sm:pt-[calc(88px+1.75rem)]', false)
        ->assertSee('@scroll.window.passive="handleScroll()"', false)
        ->assertDontSee('@scroll.window.throttle.100ms', false)
        ->assertSee('QR Peminjaman')
        ->assertSee('Tunjukkan QR ini ke Admin/Laboran untuk dipindai.')
        ->assertSee('Jika barang kembali setengah akan ada konsekuensi dari SARPRAS.')
        ->assertSee('text-center text-[10px] leading-relaxed text-amber-950', false)
        ->assertSee('role="dialog"', false)
        ->assertSee('x-teleport="body"', false)
        ->assertSee('@click.self="qrOpen = false"', false)
        ->assertSee('@keydown.escape.window="qrOpen = false; cancellationOpen = false"', false)
        ->assertSee('Ajukan pembatalan?', false)
        ->assertSee('role="alertdialog"', false)
        ->assertSee('Ya, ajukan pembatalan')
        ->assertDontSee('onsubmit="return confirm(');

    $historyResponse->assertSee($loan->kode_peminjaman);
});

it('increments loan codes during the day and resets the sequence on a new day', function () {
    $this->travelTo('2026-02-10 09:00:00');
    $barang = createLoanTestItem(3);
    $firstBorrower = createLoanTestUser();
    $secondBorrower = createLoanTestUser();

    $this->actingAs($firstBorrower)
        ->post(route('user.peminjaman.store'), loanRequestData($barang))
        ->assertRedirect(route('user.beranda', ['page' => 'history']));

    $this->actingAs($secondBorrower)
        ->post(route('user.peminjaman.store'), loanRequestData($barang))
        ->assertRedirect(route('user.beranda', ['page' => 'history']));

    expect(Peminjaman::query()->where('akun_id', $firstBorrower->akun_id)->value('kode_peminjaman'))
        ->toBe('PJM-20260210-0001');
    expect(Peminjaman::query()->where('akun_id', $secondBorrower->akun_id)->value('kode_peminjaman'))
        ->toBe('PJM-20260210-0002');

    $this->travelTo('2026-02-11 09:00:00');
    $thirdBorrower = createLoanTestUser();
    $this->actingAs($thirdBorrower)
        ->post(route('user.peminjaman.store'), loanRequestData($barang))
        ->assertRedirect(route('user.beranda', ['page' => 'history']));

    expect(Peminjaman::query()->where('akun_id', $thirdBorrower->akun_id)->value('kode_peminjaman'))
        ->toBe('PJM-20260211-0001');
});

it('shows catalog quick-loan actions and a protected single-item modal form', function () {
    $borrower = createLoanTestUser();
    $barang = createLoanTestItem(5);

    $response = $this->actingAs($borrower)
        ->get(route('user.beranda', ['page' => 'catalog']));

    $response
        ->assertOk()
        ->assertSee('Pinjam Sekarang')
        ->assertSee('openLoanModal', false)
        ->assertSee(route('user.peminjaman.store'), false)
        ->assertSee('name="items[0][barang_id]"', false)
        ->assertSee('name="preserve_cart" value="1"', false)
        ->assertSee('initCatalogAjax()', false)
        ->assertSee('data-catalog-ajax', false)
        ->assertSee('name="jenis_peminjaman"', false)
        ->assertSee('Jelaskan keperluan peminjaman')
        ->assertDontSee('Pengajuan akan diproses hanya jika stok masih tersedia.')
        ->assertSee($barang->nama_barang);
});

it('provides ajax pagination controls for catalog results', function () {
    $borrower = createLoanTestUser();

    for ($index = 0; $index < 13; $index++) {
        createLoanTestItem(3);
    }

    $response = $this->actingAs($borrower)
        ->get(route('user.beranda', ['page' => 'catalog']));

    $response
        ->assertOk()
        ->assertSee('Navigasi halaman katalog')
        ->assertSee('data-catalog-ajax', false)
        ->assertSee('catalog_page=2', false);
});

it('shows borrowing instructions first and limits ranked featured items to four', function () {
    $borrower = createLoanTestUser();
    $otherBorrower = createLoanTestUser();
    $popularItem = createLoanTestItem(5);
    $popularItem->update(['nama_barang' => 'Barang paling populer']);
    $lessPopularItem = createLoanTestItem(5);
    $lessPopularItem->update(['nama_barang' => 'Barang kurang populer']);
    $thirdItem = createLoanTestItem(1);
    $thirdItem->update(['nama_barang' => 'Barang unggulan ketiga']);
    $fourthItem = createLoanTestItem(2);
    $fourthItem->update(['nama_barang' => 'Barang unggulan keempat']);
    $fifthItem = createLoanTestItem(3);
    $fifthItem->update(['nama_barang' => 'Barang di luar empat besar']);

    foreach ([[$borrower, $popularItem], [$otherBorrower, $popularItem], [$borrower, $lessPopularItem]] as [$user, $item]) {
        $this->actingAs($user)
            ->post(route('user.peminjaman.store'), loanRequestData($item))
            ->assertRedirect(route('user.beranda', ['page' => 'history']));
    }

    $response = $this->actingAs($borrower)->get(route('user.beranda'));
    $content = $response->getContent();
    $stepsPosition = strpos($content, 'Cara meminjam');
    $summaryPosition = strpos($content, 'Ringkasan peminjaman');
    $popularPosition = strpos($content, 'Barang paling populer');
    $lessPopularPosition = strpos($content, 'Barang kurang populer');
    $thirdPosition = strpos($content, 'Barang unggulan ketiga');
    $fourthPosition = strpos($content, 'Barang unggulan keempat');

    $response
        ->assertOk()
        ->assertDontSee('Paling sering dipinjam oleh seluruh pengguna')
        ->assertSee('user-mobile-menu')
        ->assertSee('Barang unggulan ketiga')
        ->assertSee('Barang unggulan keempat')
        ->assertDontSee('Barang di luar empat besar')
        ->assertDontSee('Siap dipinjam');

    expect($stepsPosition)->toBeLessThan($summaryPosition);
    expect($popularPosition)->toBeLessThan($lessPopularPosition);
    expect($lessPopularPosition)->toBeLessThan($thirdPosition);
    expect($thirdPosition)->toBeLessThan($fourthPosition);
});

it('renders a selectable cart without the empty message or stock availability text', function () {
    $borrower = createLoanTestUser();

    $this->actingAs($borrower)
        ->get(route('user.beranda', ['page' => 'apply']))
        ->assertOk()
        ->assertSee('Pilih barang dari keranjang untuk diajukan.')
        ->assertSee('Jelajahi katalog')
        ->assertSee('underline-offset-4', false)
        ->assertSee('toggleCartItem(item.id', false)
        ->assertSee('selectedCartItems.length', false)
        ->assertDontSee('Keranjang masih kosong.')
        ->assertDontSee('Tersedia', false);
});

it('preserves the cart after a successful quick-loan request', function () {
    $borrower = createLoanTestUser();
    $barang = createLoanTestItem(5);

    $response = $this->actingAs($borrower)
        ->post(route('user.peminjaman.store'), [
            ...loanRequestData($barang, 1),
            'preserve_cart' => '1',
        ]);

    $response
        ->assertRedirect(route('user.beranda', ['page' => 'history']))
        ->assertSessionMissing('success')
        ->assertSessionMissing('cart_cleared');

    $this->assertDatabaseHas('detail_peminjaman', [
        'barang_id' => $barang->barang_id,
        'jumlah_pinjam' => 1,
        'status' => 'pending',
    ]);
});

it('clears only submitted cart items after a selected-item request', function () {
    $borrower = createLoanTestUser();
    $selectedItem = createLoanTestItem(5);
    $unselectedItem = createLoanTestItem(4);
    $requestData = loanRequestData($selectedItem, 2);
    $requestData['preserve_cart'] = '1';
    $requestData['clear_submitted_items'] = '1';

    $response = $this->actingAs($borrower)
        ->post(route('user.peminjaman.store'), $requestData);

    $response
        ->assertRedirect(route('user.beranda', ['page' => 'history']))
        ->assertSessionMissing('success')
        ->assertSessionHas('cart_items_submitted', [$selectedItem->barang_id])
        ->assertSessionMissing('cart_cleared');

    $this->assertDatabaseCount('detail_peminjaman', 1);
    $this->assertDatabaseHas('detail_peminjaman', [
        'barang_id' => $selectedItem->barang_id,
        'jumlah_pinjam' => 2,
    ]);
    $this->assertDatabaseMissing('detail_peminjaman', [
        'barang_id' => $unselectedItem->barang_id,
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $unselectedItem->barang_id,
        'stok_tersedia' => 4,
    ]);
});

it('creates one loan for multiple selected items', function () {
    $borrower = createLoanTestUser();
    $firstItem = createLoanTestItem(4);
    $secondItem = createLoanTestItem(3, [3]);
    $requestData = loanRequestData($firstItem, 2);
    $requestData['items'][] = [
        'barang_id' => $secondItem->barang_id,
        'quantity' => 1,
    ];

    $this->actingAs($borrower)
        ->post(route('user.peminjaman.store'), $requestData)
        ->assertRedirect(route('user.beranda', ['page' => 'history']));

    $loan = Peminjaman::query()->firstOrFail();

    $this->assertDatabaseCount('peminjaman', 1);
    $this->assertDatabaseCount('detail_peminjaman', 2);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $firstItem->barang_id,
        'stok_tersedia' => 2,
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $secondItem->barang_id,
        'stok_tersedia' => 2,
    ]);
    $this->assertDatabaseHas('detail_peminjaman', [
        'peminjaman_id' => $loan->peminjaman_id,
        'barang_id' => $firstItem->barang_id,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('detail_peminjaman', [
        'peminjaman_id' => $loan->peminjaman_id,
        'barang_id' => $secondItem->barang_id,
        'status' => 'pending',
    ]);

    $historyResponse = $this->get(route('user.beranda', ['page' => 'history']));

    $historyResponse->assertOk();
    expect(substr_count(strip_tags($historyResponse->getContent()), 'Menunggu konfirmasi'))->toBe(1);
});

it('rejects a request when location stock is lower than global stock', function () {
    $borrower = createLoanTestUser();
    $barang = createLoanTestItem(5, [2]);

    $this->actingAs($borrower)
        ->from(route('user.beranda', ['page' => 'apply']))
        ->post(route('user.peminjaman.store'), loanRequestData($barang, 3))
        ->assertRedirect(route('user.beranda', ['page' => 'apply']))
        ->assertSessionHasErrors('items');

    $this->assertDatabaseCount('peminjaman', 0);
    $this->assertDatabaseCount('detail_peminjaman', 0);
    $this->assertDatabaseCount('log_mutasi_barang', 0);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 5,
    ]);
    $this->assertDatabaseHas('lokasi_barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 2,
    ]);
});

it('does not partially reserve a cart when one item is unavailable', function () {
    $borrower = createLoanTestUser();
    $firstItem = createLoanTestItem(4);
    $secondItem = createLoanTestItem(4);
    $orderedItems = collect([$firstItem, $secondItem])->sortBy('barang_id')->values();
    $unavailableItem = $orderedItems[1];
    $room = Ruangan::query()->create([
        'kode_ruangan' => Str::upper(Str::random(8)),
        'nama_ruangan' => 'Ruang '.Str::random(6),
    ]);
    LokasiBarang::query()->create([
        'barang_id' => $unavailableItem->barang_id,
        'ruangan_id' => $room->ruangan_id,
        'stok_total' => 1,
        'stok_tersedia' => 1,
    ]);
    $requestData = loanRequestData($orderedItems[0], 2);
    $requestData['items'][] = [
        'barang_id' => $unavailableItem->barang_id,
        'quantity' => 2,
    ];

    $this->actingAs($borrower)
        ->from(route('user.beranda', ['page' => 'apply']))
        ->post(route('user.peminjaman.store'), $requestData)
        ->assertRedirect(route('user.beranda', ['page' => 'apply']))
        ->assertSessionHasErrors('items');

    $this->assertDatabaseCount('peminjaman', 0);
    $this->assertDatabaseCount('detail_peminjaman', 0);
    $this->assertDatabaseCount('log_mutasi_barang', 0);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $firstItem->barang_id,
        'stok_tersedia' => 4,
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $secondItem->barang_id,
        'stok_tersedia' => 4,
    ]);
});

it('rejects quantities below one without writing a loan', function () {
    $borrower = createLoanTestUser();
    $barang = createLoanTestItem(5);

    $this->actingAs($borrower)
        ->from(route('user.beranda', ['page' => 'apply']))
        ->post(route('user.peminjaman.store'), loanRequestData($barang, 0))
        ->assertRedirect(route('user.beranda', ['page' => 'apply']))
        ->assertSessionHasErrors('items.0.quantity');

    $this->assertDatabaseCount('peminjaman', 0);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 5,
    ]);
});

it('submits a cancellation request without releasing reserved stock', function () {
    $borrower = createLoanTestUser();
    $barang = createLoanTestItem(5, [2, 3]);

    $this->actingAs($borrower)
        ->post(route('user.peminjaman.store'), loanRequestData($barang, 4));

    $loan = Peminjaman::query()->firstOrFail();
    $loan->catatan = 'Kegiatan kelas';
    $loan->save();

    $this->post(route('user.peminjaman.cancellation.store', $loan->peminjaman_id))
        ->assertRedirect(route('user.beranda', ['page' => 'history']))
        ->assertSessionHas('success');

    $this->assertDatabaseHas('peminjaman', [
        'peminjaman_id' => $loan->peminjaman_id,
        'catatan' => "Kegiatan kelas\n".Peminjaman::CANCELLATION_REQUEST_MARKER,
    ]);
    $this->assertDatabaseHas('detail_peminjaman', [
        'peminjaman_id' => $loan->peminjaman_id,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 1,
    ]);
    $this->assertDatabaseCount('log_mutasi_barang', 2);
    $this->get(route('user.beranda', ['page' => 'history']))
        ->assertOk()
        ->assertSee('Menunggu keputusan pembatalan')
        ->assertSee('Tampilkan QR')
        ->assertDontSee('Ajukan pembatalan');

    expect(LokasiBarang::query()->orderBy('stok_total')->pluck('stok_tersedia')->all())->toBe([0, 1]);
});

it('does not let another borrower request cancellation of a loan', function () {
    $owner = createLoanTestUser();
    $otherBorrower = createLoanTestUser();
    $barang = createLoanTestItem(5);

    $this->actingAs($owner)
        ->post(route('user.peminjaman.store'), loanRequestData($barang, 2));

    $loan = Peminjaman::query()->firstOrFail();

    $this->actingAs($otherBorrower)
        ->post(route('user.peminjaman.cancellation.store', $loan->peminjaman_id))
        ->assertNotFound();

    $this->assertDatabaseHas('peminjaman', [
        'peminjaman_id' => $loan->peminjaman_id,
        'catatan' => null,
    ]);
    $this->assertDatabaseHas('detail_peminjaman', [
        'peminjaman_id' => $loan->peminjaman_id,
        'status' => 'pending',
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 3,
    ]);
});

it('does not allow duplicate cancellation requests', function () {
    $borrower = createLoanTestUser();
    $barang = createLoanTestItem(5);

    $this->actingAs($borrower)
        ->post(route('user.peminjaman.store'), loanRequestData($barang, 2));

    $loan = Peminjaman::query()->firstOrFail();

    $this->post(route('user.peminjaman.cancellation.store', $loan->peminjaman_id))
        ->assertRedirect(route('user.beranda', ['page' => 'history']));

    $this->assertDatabaseHas('peminjaman', [
        'peminjaman_id' => $loan->peminjaman_id,
        'catatan' => Peminjaman::CANCELLATION_REQUEST_MARKER,
    ]);

    $this->from(route('user.beranda', ['page' => 'history']))
        ->post(route('user.peminjaman.cancellation.store', $loan->peminjaman_id))
        ->assertRedirect(route('user.beranda', ['page' => 'history']))
        ->assertSessionHasErrors('peminjaman');

    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 3,
    ]);
});

it('does not allow cancellation requests after a loan is confirmed', function () {
    $borrower = createLoanTestUser();
    $barang = createLoanTestItem(5);

    $this->actingAs($borrower)
        ->post(route('user.peminjaman.store'), loanRequestData($barang, 2));

    $loan = Peminjaman::query()->firstOrFail();
    $detail = DetailPeminjaman::query()->firstOrFail();
    $detail->status = 'dipinjam';
    $detail->save();

    $this->post(route('user.peminjaman.cancellation.store', $loan->peminjaman_id))
        ->assertSessionHasErrors('peminjaman');

    $this->assertDatabaseHas('peminjaman', [
        'peminjaman_id' => $loan->peminjaman_id,
        'catatan' => null,
    ]);
    $this->assertDatabaseHas('barang', [
        'barang_id' => $barang->barang_id,
        'stok_tersedia' => 3,
    ]);
});
