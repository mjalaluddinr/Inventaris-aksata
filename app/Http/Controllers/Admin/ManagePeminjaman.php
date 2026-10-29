<?php

namespace App\Http\Controllers\Admin;

use App\Exports\PeminjamanExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\ManagePeminjaman\RejectPeminjamanRequest;
use App\Http\Requests\ManagePeminjaman\ReturnPeminjamanRequest;
use App\Http\Requests\ManagePeminjaman\ScanPeminjamanRequest;
use App\Models\Barang;
use App\Models\DetailPeminjaman;
use App\Models\LogMutasiBarang;
use App\Models\LokasiBarang;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ManagePeminjaman extends Controller
{
    private const RESERVATION_NOTE = 'Reservasi QR peminjaman';

    public function index(Request $request): View|string
    {
        $reportYears = $this->reportYears();

        $filters = $request->validate([
            'tab' => ['nullable', 'in:pending,borrowed,history'],
            'year' => ['nullable', 'integer', Rule::in($reportYears)],
        ]);
        $activeTab = $filters['tab'] ?? 'pending';
        $year = (int) ($filters['year'] ?? ($reportYears[0] ?? now()->year));

        $peminjamans = $this->loanQueryForTab($activeTab)
            ->with([
                'akun',
                'diprosesAkun',
                'detailPeminjamans.barang',
                'detailPeminjamans.logMutasiBarangs' => fn (HasMany $query) => $query
                    ->where('tipe', 'dipinjam')
                    ->with(['ruangan', 'lemariAsal']),
            ])
            ->orderByDesc('tanggal_pengajuan')
            ->orderByDesc('peminjaman_id')
            ->paginate(10)
            ->withQueryString();

        $monthlyLoans = $this->monthlyLoanSummary($year);

        /** @var View $renderedView */
        $renderedView = view('pages.admin.peminjaman', compact(
            'activeTab',
            'monthlyLoans',
            'peminjamans',
            'reportYears',
            'year',
        ))->with('isPrintPreview', false);

        return $renderedView->fragmentIf($request->ajax(), 'loan-list');
    }

    public function printReport(Request $request): View
    {
        $reportYears = $this->reportYears();
        $yearRules = $reportYears === []
            ? ['nullable', 'integer', 'min:2000', 'max:2100']
            : ['nullable', 'integer', Rule::in($reportYears)];
        $filters = $request->validate([
            'year' => $yearRules,
            'tab' => ['nullable', 'in:pending,borrowed,history'],
        ]);
        $year = (int) ($filters['year'] ?? ($reportYears[0] ?? now()->year));
        $activeTab = $filters['tab'] ?? 'pending';
        $monthlyLoans = $this->monthlyLoanSummary($year);
        $backUrl = route('admin.peminjaman', array_filter([
            'tab' => $activeTab,
            'year' => $reportYears === [] ? null : $year,
        ], static fn (mixed $value): bool => $value !== null));

        return view('pages.admin.peminjaman', [
            'backUrl' => $backUrl,
            'isPrintPreview' => true,
            'monthlyLoans' => $monthlyLoans,
            'year' => $year,
        ]);
    }

    /**
     * @return array<int, int>
     */
    private function reportYears(): array
    {
        $yearExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y', tanggal_pengajuan)",
            'pgsql' => 'EXTRACT(YEAR FROM tanggal_pengajuan)',
            'sqlsrv' => 'YEAR(tanggal_pengajuan)',
            default => 'YEAR(tanggal_pengajuan)',
        };

        return DB::table('peminjaman')
            ->whereNotNull('tanggal_pengajuan')
            ->selectRaw("{$yearExpression} as report_year")
            ->distinct()
            ->orderByDesc('report_year')
            ->pluck('report_year')
            ->map(static fn (string|int $year): int => (int) $year)
            ->all();
    }

    /**
     * @return Collection<int, array{label: string, total: int}>
     */
    private function monthlyLoanSummary(int $year): Collection
    {
        $yearStart = now()->setDate($year, 1, 1)->startOfDay();
        $yearEnd = $yearStart->copy()->endOfYear();
        $monthExpression = match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%m', tanggal_pengajuan)",
            'pgsql' => 'EXTRACT(MONTH FROM tanggal_pengajuan)::integer',
            'sqlsrv' => 'MONTH(tanggal_pengajuan)',
            default => "DATE_FORMAT(tanggal_pengajuan, '%m')",
        };
        $monthlyTotals = DB::table('peminjaman')
            ->whereBetween('tanggal_pengajuan', [$yearStart, $yearEnd])
            ->selectRaw("{$monthExpression} as month, COUNT(*) as total")
            ->groupBy('month')
            ->pluck('total', 'month');

        return collect(range(1, 12))
            ->map(static fn (int $month): array => [
                'label' => $yearStart->copy()->addMonths($month - 1)->translatedFormat('M'),
                'total' => (int) ($monthlyTotals[(string) $month] ?? $monthlyTotals[sprintf('%02d', $month)] ?? 0),
            ]);
    }

    public function scan(ScanPeminjamanRequest $request): JsonResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof User, 403);

        $code = $request->validated('code');
        $loanId = DB::transaction(function () use ($admin, $code): string {
            $loan = Peminjaman::query()
                ->where('kode_peminjaman', $code)
                ->lockForUpdate()
                ->first();

            if (! $loan) {
                throw ValidationException::withMessages([
                    'code' => 'QR tidak valid atau pengajuan sudah diproses.',
                ]);
            }

            $this->confirmLoan($loan->peminjaman_id, $admin);

            return $loan->peminjaman_id;
        }, 3);

        session()->flash('success', 'Konfirmasi berhasil.');

        return response()->json([
            'message' => 'QR berhasil dipindai. Peminjaman telah dikonfirmasi.',
            'loan_id' => $loanId,
            'redirect_url' => route('admin.peminjaman', ['tab' => 'borrowed'], false),
        ]);
    }

    public function confirm(Request $request, Peminjaman $peminjaman): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof User, 403);

        $this->confirmLoan($peminjaman->peminjaman_id, $admin);

        return redirect()
            ->route('admin.peminjaman', ['tab' => 'borrowed'])
            ->with('success', 'Konfirmasi berhasil.');
    }

    public function reject(RejectPeminjamanRequest $request, Peminjaman $peminjaman): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof User, 403);
        $reason = $request->validated('reason');

        DB::transaction(function () use ($admin, $peminjaman, $reason): void {
            $loan = Peminjaman::query()
                ->whereKey($peminjaman->peminjaman_id)
                ->lockForUpdate()
                ->firstOrFail();
            $details = $this->lockLoanDetails($loan);

            $this->ensureLoanHasStatus($loan, $details, 'pending');

            foreach ($details as $detail) {
                $this->restoreReservation($loan, $detail, $admin, 'Reservasi dipulihkan karena pengajuan ditolak.');
                $detail->status = 'ditolak';
                $detail->catatan_kembali = $reason;
                $detail->save();
            }

            $loan->diproses_akun_id = $admin->akun_id;
            $loan->clearCancellationRequest();
            $loan->save();
        }, 3);

        return redirect()
            ->route('admin.peminjaman', ['tab' => 'pending'])
            ->with('success', 'Pengajuan ditolak dan stok dikembalikan.');
    }

    public function returnItems(ReturnPeminjamanRequest $request, Peminjaman $peminjaman): RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof User, 403);
        $validated = $request->validated();

        DB::transaction(function () use ($admin, $peminjaman, $validated): void {
            $loan = Peminjaman::query()
                ->whereKey($peminjaman->peminjaman_id)
                ->lockForUpdate()
                ->firstOrFail();
            $details = $this->lockLoanDetails($loan);
            $this->ensureLoanHasStatus($loan, $details, 'dipinjam');

            $expectedIds = $details->pluck('detail_peminjaman_id')->sort()->values()->all();
            $receivedIds = collect(array_keys($validated['conditions']))->sort()->values()->all();
            $receivedQuantityIds = collect(array_keys($validated['quantities']))->sort()->values()->all();

            if ($expectedIds !== $receivedIds || $expectedIds !== $receivedQuantityIds) {
                throw ValidationException::withMessages([
                    'conditions' => 'Data kondisi atau jumlah barang tidak lengkap. Muat ulang halaman lalu coba lagi.',
                ]);
            }

            // ASVS 2.2.1/2.2.2: validate each submitted quantity against the locked, current loan balance.
            $hasReturnedItems = false;
            foreach ($details as $detail) {
                $quantity = (int) $validated['quantities'][$detail->detail_peminjaman_id];
                $remainingQuantity = $detail->jumlah_pinjam - $detail->jumlah_kembali;

                if ($quantity > $remainingQuantity) {
                    throw ValidationException::withMessages([
                        'quantities' => 'Jumlah pengembalian melebihi sisa barang yang dipinjam.',
                    ]);
                }

                if ($quantity === 0) {
                    continue;
                }

                $hasReturnedItems = true;
                $condition = $validated['conditions'][$detail->detail_peminjaman_id];
                $this->restoreReservation(
                    $loan,
                    $detail,
                    $admin,
                    'Pengembalian barang: '.$condition,
                    $condition,
                    $quantity,
                );
                $detail->jumlah_kembali += $quantity;
                $detail->status = $detail->jumlah_kembali === $detail->jumlah_pinjam
                    ? 'dikembalikan'
                    : 'dipinjam';
                $detail->tanggal_kembali = $detail->status === 'dikembalikan' ? now() : null;
                $detail->kondisi_kembali = $condition;
                $detail->catatan_kembali = $validated['note'] ?? null;
                $detail->save();
            }

            if (! $hasReturnedItems) {
                throw ValidationException::withMessages([
                    'quantities' => 'Masukkan jumlah barang yang akan dikembalikan.',
                ]);
            }

            $loan->diproses_akun_id = $admin->akun_id;
            $loan->clearCancellationRequest();
            $loan->save();
        }, 3);

        $hasRemainingItems = $peminjaman->detailPeminjamans()
            ->where('status', 'dipinjam')
            ->exists();

        return redirect()
            ->route('admin.peminjaman', ['tab' => $hasRemainingItems ? 'borrowed' : 'history'])
            ->with('success', $hasRemainingItems ? 'Pengembalian berhasil dicatat.' : 'Pengembalian selesai.');
    }

    public function export(Request $request): BinaryFileResponse
    {
        $filters = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);
        $year = (int) $filters['year'];
        $loans = Peminjaman::query()
            ->with(['akun', 'detailPeminjamans.barang'])
            ->whereBetween('tanggal_pengajuan', [
                now()->setDate($year, 1, 1)->startOfDay(),
                now()->setDate($year, 12, 31)->endOfDay(),
            ])
            ->orderBy('tanggal_pengajuan')
            ->get();

        $rows = $loans->flatMap(static function (Peminjaman $loan): Collection {
            return $loan->detailPeminjamans->map(static fn (DetailPeminjaman $detail): array => [
                $loan->kode_peminjaman,
                trim(($loan->akun?->first_name ?? '').' '.($loan->akun?->last_name ?? '')),
                $detail->barang?->nama_barang ?? 'Barang tidak tersedia',
                $detail->jumlah_pinjam,
                $loan->keperluan,
                $detail->status === 'dibatalkan' ? 'Peminjaman Dibatalkan' : $detail->status,
                $loan->tanggal_pengajuan?->format('d-m-Y H:i'),
                $loan->tanggal_rencana_kembali?->format('d-m-Y'),
            ]);
        });

        return Excel::download(new PeminjamanExport($rows), "laporan-peminjaman-{$year}.xlsx");
    }

    private function confirmLoan(string $loanId, User $admin): void
    {
        DB::transaction(function () use ($admin, $loanId): void {
            $loan = Peminjaman::query()->whereKey($loanId)->lockForUpdate()->firstOrFail();
            $details = $this->lockLoanDetails($loan);
            $this->ensureLoanHasStatus($loan, $details, 'pending');

            if ($loan->hasCancellationRequest()) {
                throw ValidationException::withMessages([
                    'peminjaman' => 'Pengajuan ini memiliki permintaan pembatalan dan tidak dapat dikonfirmasi.',
                ]);
            }

            foreach ($details as $detail) {
                $detail->status = 'dipinjam';
                $detail->save();
            }

            $loan->tanggal_pinjam = now();
            $loan->diproses_akun_id = $admin->akun_id;
            $loan->save();
        }, 3);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, DetailPeminjaman>
     */
    private function lockLoanDetails(Peminjaman $loan): \Illuminate\Database\Eloquent\Collection
    {
        return DetailPeminjaman::query()
            ->where('peminjaman_id', $loan->peminjaman_id)
            ->orderBy('barang_id')
            ->lockForUpdate()
            ->get();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, DetailPeminjaman>  $details
     */
    private function ensureLoanHasStatus(Peminjaman $loan, \Illuminate\Database\Eloquent\Collection $details, string $status): void
    {
        if ($details->isEmpty() || $details->contains(static fn (DetailPeminjaman $detail): bool => $detail->status !== $status)) {
            throw ValidationException::withMessages([
                'peminjaman' => 'Status pengajuan sudah berubah. Muat ulang halaman sebelum mencoba lagi.',
            ]);
        }
    }

    private function restoreReservation(
        Peminjaman $loan,
        DetailPeminjaman $detail,
        User $admin,
        string $description,
        string $condition = 'baik',
        ?int $quantity = null,
    ): void {
        $barang = Barang::withTrashed()
            ->whereKey($detail->barang_id)
            ->lockForUpdate()
            ->firstOrFail();
        $reservations = LogMutasiBarang::query()
            ->where('detail_peminjaman_id', $detail->detail_peminjaman_id)
            ->where('tipe', 'dipinjam')
            ->where('keterangan', self::RESERVATION_NOTE)
            ->orderBy('created_at')
            ->orderBy('log_mutasi_barang_id')
            ->get();

        if ($reservations->isEmpty()) {
            $reservations = collect([new LogMutasiBarang([
                'barang_id' => $barang->barang_id,
                'jumlah' => $detail->jumlah_pinjam,
            ])]);
        }

        $reservedQuantity = (int) $reservations->sum('jumlah');
        $quantity ??= $reservedQuantity;

        if ($reservedQuantity !== $detail->jumlah_pinjam) {
            throw ValidationException::withMessages([
                'peminjaman' => 'Catatan reservasi stok tidak sesuai. Periksa stok barang sebelum melanjutkan.',
            ]);
        }

        if ($quantity < 1 || $quantity > $detail->jumlah_pinjam - $detail->jumlah_kembali) {
            throw ValidationException::withMessages([
                'quantities' => 'Jumlah pengembalian tidak sesuai dengan sisa barang yang dipinjam.',
            ]);
        }

        $previousReturns = LogMutasiBarang::query()
            ->where('detail_peminjaman_id', $detail->detail_peminjaman_id)
            ->whereIn('tipe', ['dikembalikan', 'ditarik', 'penyesuaian'])
            ->where('keterangan', 'like', 'Pengembalian barang:%')
            ->get()
            ->groupBy(static fn (LogMutasiBarang $log): string => implode('|', [
                $log->ruangan_id ?? '',
                $log->lemari_asal_id ?? '',
            ]))
            ->map(static fn (Collection $logs): int => (int) $logs->sum('jumlah'));

        $remainingReservedByLocation = $reservations
            ->groupBy(static fn (LogMutasiBarang $reservation): string => implode('|', [
                $reservation->ruangan_id ?? '',
                $reservation->lemari_asal_id ?? '',
            ]))
            ->map(function (Collection $locationReservations, string $locationKey) use ($previousReturns): array {
                return [
                    'reservation' => $locationReservations->first(),
                    'remaining' => max(
                        0,
                        (int) $locationReservations->sum('jumlah') - ($previousReturns[$locationKey] ?? 0),
                    ),
                ];
            });

        if ($remainingReservedByLocation->sum('remaining') < $quantity) {
            throw ValidationException::withMessages([
                'peminjaman' => 'Jumlah reservasi tidak mencukupi untuk pengembalian ini.',
            ]);
        }

        if ($condition === 'baik' && $barang->stok_tersedia + $quantity > $barang->stok_total) {
            throw ValidationException::withMessages([
                'peminjaman' => 'Stok tersedia melebihi stok total. Periksa data inventaris sebelum melanjutkan.',
            ]);
        }

        if ($condition === 'hilang' && $barang->stok_total < $quantity) {
            throw ValidationException::withMessages([
                'peminjaman' => 'Stok total tidak cukup untuk mencatat barang hilang.',
            ]);
        }

        $quantityRemaining = $quantity;
        foreach ($remainingReservedByLocation as $allocation) {
            if ($quantityRemaining === 0) {
                break;
            }

            $reservation = $allocation['reservation'];
            $allocationQuantity = min($quantityRemaining, $allocation['remaining']);
            if ($allocationQuantity === 0) {
                continue;
            }

            $location = null;
            if ($reservation->ruangan_id !== null) {
                $locationQuery = LokasiBarang::query()
                    ->where('barang_id', $barang->barang_id)
                    ->where('ruangan_id', $reservation->ruangan_id);

                $locationQuery = $reservation->lemari_asal_id === null
                    ? $locationQuery->whereNull('lemari_id')
                    : $locationQuery->where('lemari_id', $reservation->lemari_asal_id);

                $location = $locationQuery->lockForUpdate()->first();

                if (! $location) {
                    throw ValidationException::withMessages([
                        'peminjaman' => 'Lokasi stok reservasi tidak ditemukan. Periksa data inventaris sebelum melanjutkan.',
                    ]);
                }

                if ($condition === 'baik' && $location->stok_tersedia + $allocationQuantity > $location->stok_total) {
                    throw ValidationException::withMessages([
                        'peminjaman' => 'Stok lokasi tidak cukup untuk menerima pengembalian. Periksa data inventaris.',
                    ]);
                }

                if ($condition === 'hilang' && $location->stok_total < $allocationQuantity) {
                    throw ValidationException::withMessages([
                        'peminjaman' => 'Stok lokasi tidak cukup untuk mencatat barang hilang.',
                    ]);
                }
            }

            $stockBefore = $location?->stok_tersedia ?? $barang->stok_tersedia;
            $stockAfter = $stockBefore;

            if ($condition === 'baik') {
                if ($location) {
                    $location->stok_tersedia += $allocationQuantity;
                    $location->save();
                    $stockAfter = $location->stok_tersedia;
                }

                $barang->stok_tersedia += $allocationQuantity;
            } elseif ($condition === 'hilang') {
                if ($location) {
                    $location->stok_total -= $allocationQuantity;
                    $location->save();
                }

                $barang->stok_total -= $allocationQuantity;
            }

            $mutationType = match ($condition) {
                'baik' => 'dikembalikan',
                'hilang' => 'ditarik',
                default => 'penyesuaian',
            };

            LogMutasiBarang::query()->create([
                'barang_id' => $barang->barang_id,
                'ruangan_id' => $reservation->ruangan_id,
                'lemari_asal_id' => $reservation->lemari_asal_id,
                'akun_id' => $loan->akun_id,
                'oleh_akun_id' => $admin->akun_id,
                'detail_peminjaman_id' => $detail->detail_peminjaman_id,
                'tipe' => $mutationType,
                'jumlah' => $allocationQuantity,
                'stok_sebelum' => $stockBefore,
                'stok_sesudah' => $stockAfter,
                'keterangan' => $description,
                'created_at' => now(),
            ]);
            $quantityRemaining -= $allocationQuantity;
        }

        $barang->save();
    }

    private function loanQueryForTab(string $tab): Builder
    {
        $query = Peminjaman::query()->whereHas('detailPeminjamans');

        return match ($tab) {
            'pending' => $query->whereHas('detailPeminjamans', static fn (Builder $details) => $details->where('status', 'pending')),
            'borrowed' => $query->whereHas('detailPeminjamans', static fn (Builder $details) => $details->where('status', 'dipinjam')),
            default => $query
                ->whereDoesntHave('detailPeminjamans', static fn (Builder $details) => $details->whereIn('status', ['pending', 'dipinjam']))
                ->whereHas('detailPeminjamans'),
        };
    }
}
