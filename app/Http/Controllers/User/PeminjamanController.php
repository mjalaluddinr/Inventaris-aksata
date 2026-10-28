<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\StorePeminjamanRequest;
use App\Models\Barang;
use App\Models\DetailPeminjaman;
use App\Models\LogMutasiBarang;
use App\Models\LokasiBarang;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PeminjamanController extends Controller
{
    private const RESERVATION_NOTE = 'Reservasi QR peminjaman';

    public function store(StorePeminjamanRequest $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $validated = $request->validated();

        // ASVS V2.3.3/V2.3.4: lock inventory and commit the reservation as one operation.
        $attempt = 0;
        do {
            try {
                $loan = DB::transaction(function () use ($user, $validated): Peminjaman {
                    $items = collect($validated['items'])
                        ->map(static fn (array $item): array => [
                            'barang_id' => $item['barang_id'],
                            'quantity' => (int) $item['quantity'],
                        ])
                        ->sortBy('barang_id')
                        ->values();

                    /** @var array<int, array{barang: Barang, locations: EloquentCollection<int, LokasiBarang>, quantity: int}> $reservations */
                    $reservations = [];

                    foreach ($items as $item) {
                        $barang = Barang::query()
                            ->whereKey($item['barang_id'])
                            ->lockForUpdate()
                            ->first();

                        if (! $barang) {
                            throw ValidationException::withMessages([
                                'items' => 'Salah satu barang sudah tidak tersedia. Muat ulang katalog lalu coba lagi.',
                            ]);
                        }

                        $locations = LokasiBarang::query()
                            ->where('barang_id', $barang->barang_id)
                            ->orderBy('ruangan_id')
                            ->orderBy('lemari_id')
                            ->orderBy('lokasi_barang_id')
                            ->lockForUpdate()
                            ->get();

                        $locationStock = $locations->isEmpty()
                            ? $barang->stok_tersedia
                            : $locations->sum('stok_tersedia');

                        if ($barang->stok_tersedia < $item['quantity'] || $locationStock < $item['quantity']) {
                            throw ValidationException::withMessages([
                                'items' => "Stok {$barang->nama_barang} baru saja berubah. Kurangi jumlah atau muat ulang katalog.",
                            ]);
                        }

                        $reservations[] = [
                            'barang' => $barang,
                            'locations' => $locations,
                            'quantity' => $item['quantity'],
                        ];
                    }

                    $loan = $user->peminjamans()->create([
                        'kode_peminjaman' => $this->generateLoanCode(),
                        'jenis_peminjaman' => $validated['jenis_peminjaman'],
                        'keperluan' => $validated['keperluan'],
                        'tanggal_pengajuan' => now(),
                        'tanggal_rencana_kembali' => $validated['tanggal_rencana_kembali'],
                    ]);

                    foreach ($reservations as $reservation) {
                        $barang = $reservation['barang'];
                        $quantity = $reservation['quantity'];
                        $detail = $loan->detailPeminjamans()->create([
                            'barang_id' => $barang->barang_id,
                            'jumlah_pinjam' => $quantity,
                            'jumlah_kembali' => 0,
                            'status' => 'pending',
                        ]);

                        $stockBefore = $barang->stok_tersedia;
                        $barang->stok_tersedia -= $quantity;
                        $barang->save();

                        $this->recordReservation(
                            $barang,
                            $reservation['locations'],
                            $quantity,
                            $stockBefore,
                            $detail,
                            $user,
                        );
                    }

                    return $loan;
                }, 3);

                break;
            } catch (UniqueConstraintViolationException $exception) {
                // ASVS 2.3.4: retry when concurrent requests allocate the same daily sequence.
                if (++$attempt >= 5) {
                    throw $exception;
                }
            }
        } while (true);

        $redirect = redirect()->route('user.beranda', ['page' => 'history']);

        if ($validated['clear_submitted_items'] ?? false) {
            $redirect->with('cart_items_submitted', collect($validated['items'])->pluck('barang_id')->all());
        } elseif (! ($validated['preserve_cart'] ?? false)) {
            $redirect->with('cart_cleared', true);
        }

        return $redirect;
    }

    public function requestCancellation(Request $request, string $peminjamanId): RedirectResponse
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        DB::transaction(function () use ($user, $peminjamanId): void {
            // ASVS V8.2.2: resolve a loan only within the authenticated borrower's records.
            $loan = Peminjaman::query()
                ->where('akun_id', $user->akun_id)
                ->where('peminjaman_id', $peminjamanId)
                ->lockForUpdate()
                ->firstOrFail();

            $details = DetailPeminjaman::query()
                ->where('peminjaman_id', $loan->peminjaman_id)
                ->orderBy('barang_id')
                ->lockForUpdate()
                ->get();

            if ($details->isEmpty() || $details->contains(static fn (DetailPeminjaman $detail): bool => $detail->status !== 'pending')) {
                throw ValidationException::withMessages([
                    'peminjaman' => 'Permintaan pembatalan hanya dapat diajukan untuk pesanan yang menunggu konfirmasi admin.',
                ]);
            }

            if ($loan->hasCancellationRequest()) {
                throw ValidationException::withMessages([
                    'peminjaman' => 'Permintaan pembatalan untuk pesanan ini sudah menunggu keputusan admin.',
                ]);
            }

            // ASVS V2.2.2: enforce the request workflow at the trusted server boundary.
            $loan->requestCancellation();
        }, 3);

        return redirect()
            ->route('user.beranda', ['page' => 'history'])
            ->with('success', 'Permintaan pembatalan berhasil diajukan.');
    }

    /**
     * @param  EloquentCollection<int, LokasiBarang>  $locations
     */
    private function recordReservation(
        Barang $barang,
        EloquentCollection $locations,
        int $quantity,
        int $globalStockBefore,
        DetailPeminjaman $detail,
        User $user,
    ): void {
        if ($locations->isEmpty()) {
            $this->recordMutation(
                $barang,
                null,
                $quantity,
                $globalStockBefore,
                $globalStockBefore - $quantity,
                'dipinjam',
                self::RESERVATION_NOTE,
                $detail,
                $user,
            );

            return;
        }

        $remaining = $quantity;

        foreach ($locations as $location) {
            if ($remaining === 0) {
                break;
            }

            $allocated = min($remaining, $location->stok_tersedia);

            if ($allocated === 0) {
                continue;
            }

            $stockBefore = $location->stok_tersedia;
            $location->stok_tersedia -= $allocated;
            $location->save();

            $this->recordMutation(
                $barang,
                $location,
                $allocated,
                $stockBefore,
                $location->stok_tersedia,
                'dipinjam',
                self::RESERVATION_NOTE,
                $detail,
                $user,
            );

            $remaining -= $allocated;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'items' => "Stok {$barang->nama_barang} berubah. Muat ulang katalog lalu coba lagi.",
            ]);
        }
    }

    private function recordMutation(
        Barang $barang,
        ?LokasiBarang $location,
        int $quantity,
        int $stockBefore,
        int $stockAfter,
        string $type,
        string $description,
        DetailPeminjaman $detail,
        User $user,
    ): void {
        // ASVS V16.3.3: record reservations and their reversals in the inventory audit log.
        LogMutasiBarang::query()->create([
            'barang_id' => $barang->barang_id,
            'ruangan_id' => $location?->ruangan_id,
            'lemari_asal_id' => $location?->lemari_id,
            'akun_id' => $user->akun_id,
            'oleh_akun_id' => $user->akun_id,
            'detail_peminjaman_id' => $detail->detail_peminjaman_id,
            'tipe' => $type,
            'jumlah' => $quantity,
            'stok_sebelum' => $stockBefore,
            'stok_sesudah' => $stockAfter,
            'keterangan' => $description,
            'created_at' => now(),
        ]);
    }

    private function generateLoanCode(): string
    {
        // ASVS 2.2.1: issue each loan a date-scoped code in the expected format.
        $prefix = 'PJM-'.now()->format('Ymd').'-';
        $highestSequence = Peminjaman::query()
            ->where('kode_peminjaman', 'like', $prefix.'%')
            ->pluck('kode_peminjaman')
            ->map(static fn (string $code): int => (int) substr($code, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($highestSequence + 1), 4, '0', STR_PAD_LEFT);
    }
}
