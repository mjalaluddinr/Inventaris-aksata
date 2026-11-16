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
use App\Services\RedisNotificationService;
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

    public function __construct(private RedisNotificationService $notifications) {}

    public function index(Request $request): View|string
    {
        $reportYears = $this->reportYears();
        $yearRules = $reportYears === []
            ? ['nullable', 'integer', 'min:2000', 'max:2100']
            : ['nullable', 'integer', Rule::in($reportYears)];

        $filters = $request->validate([
            'tab' => ['nullable', 'in:pending,borrowed,history'],
            'year' => $yearRules,
        ]);
        $activeTab = $filters['tab'] ?? 'pending';
        $year = (int) ($filters['year'] ?? ($reportYears[0] ?? now()->year));

        $loanRelations = [
            'akun',
            'diprosesAkun',
            'detailPeminjamans.barang',
            'detailPeminjamans.logMutasiBarangs' => fn (HasMany $query) => $query
                ->where('tipe', 'dipinjam')
                ->with(['ruangan', 'lemariAsal']),
        ];
        if ($activeTab === 'pending') {
            $loanRelations[] = 'detailPeminjamans.barang.lokasiBarangs.ruangan';
        }

        $peminjamans = $this->loanQueryForTab($activeTab)
            ->with($loanRelations)
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

        session()->flash('success', "Peminjaman {$code} berhasil dikonfirmasi.");

        return response()->json([
            'message' => 'QR berhasil dipindai. Peminjaman telah dikonfirmasi.',
            'loan_id' => $loanId,
            'redirect_url' => route('admin.peminjaman', ['tab' => 'borrowed'], false),
        ]);
    }

    public function confirm(Request $request, Peminjaman $peminjaman): JsonResponse|RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof User, 403);
        $validated = $request->validate([
            'allocations' => ['sometimes', 'array'],
            'allocations.*' => ['required', 'array'],
            'allocations.*.*' => ['required', 'integer', 'min:0'],
            'tab' => ['nullable', 'in:pending,borrowed,history'],
        ]);
        $activeTab = $validated['tab'] ?? 'pending';

        $this->confirmLoan($peminjaman->peminjaman_id, $admin, $validated['allocations'] ?? [], true);

        return $this->respondToLoanAction(
            $request,
            $activeTab,
            'Peminjaman berhasil dikonfirmasi.',
        );
    }

    public function reject(RejectPeminjamanRequest $request, Peminjaman $peminjaman): JsonResponse|RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof User, 403);
        $reason = $request->validated('reason');
        $activeTab = $request->validated('tab', 'pending');

        $loan = DB::transaction(function () use ($admin, $peminjaman, $reason): Peminjaman {
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

            return $loan;
        }, 3);

        $this->notifyBorrower(
            $loan->akun_id,
            "Pengajuan peminjaman ditolak. Alasan: {$reason}",
        );

        return $this->respondToLoanAction(
            $request,
            $activeTab,
            'Pengajuan peminjaman ditolak dan stok dikembalikan.',
        );
    }

    public function returnItems(ReturnPeminjamanRequest $request, Peminjaman $peminjaman): JsonResponse|RedirectResponse
    {
        $admin = $request->user();
        abort_unless($admin instanceof User, 403);
        $validated = $request->validated();
        $activeTab = $validated['tab'] ?? 'borrowed';

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

            // ASVS 2.2.1/2.2.2 and 2.3.3: require the complete locked loan balance to be returned atomically.
            foreach ($details as $detail) {
                $quantity = (int) $validated['quantities'][$detail->detail_peminjaman_id];
                $remainingQuantity = $detail->jumlah_pinjam - $detail->jumlah_kembali;

                if ($quantity !== $remainingQuantity) {
                    throw ValidationException::withMessages([
                        'quantities' => 'Semua barang pada pengajuan ini harus dikembalikan sekaligus.',
                    ]);
                }

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

            $loan->diproses_akun_id = $admin->akun_id;
            $loan->clearCancellationRequest();
            $loan->save();
        }, 3);

        $borrower = $peminjaman->akun;
        $borrowerName = trim(($borrower?->first_name ?? '').' '.($borrower?->last_name ?? ''))
            ?: ($borrower?->username ?? 'peminjam');
        $this->notifications->notifyLoanProcessors(
            "Seluruh barang peminjaman dari {$borrowerName} telah dikembalikan dan dicatat.",
        );
        $this->notifications->clearLoanReminderMarkers(
            $peminjaman->peminjaman_id,
            $peminjaman->akun_id,
        );

        $this->notifyBorrower(
            $peminjaman->akun_id,
            'Seluruh barang peminjaman Anda telah dikembalikan dan dicatat.',
        );

        return $this->respondToLoanAction(
            $request,
            $activeTab,
            'Peminjaman berhasil dikembalikan sepenuhnya.',
        );
    }

    private function respondToLoanAction(Request $request, string $activeTab, string $message): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'tab' => $activeTab,
            ]);
        }

        return redirect()
            ->route('admin.peminjaman', ['tab' => $activeTab])
            ->with('success', $message);
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

    /**
     * @param  array<string, array<string, int|string>>  $selectedAllocations
     */
    private function confirmLoan(
        string $loanId,
        User $admin,
        array $selectedAllocations = [],
        bool $requireAllocationSelection = false,
    ): void {
        DB::transaction(function () use ($admin, $loanId, $selectedAllocations, $requireAllocationSelection): void {
            $loan = Peminjaman::query()->whereKey($loanId)->lockForUpdate()->firstOrFail();
            $details = $this->lockLoanDetails($loan);
            $this->ensureLoanHasStatus($loan, $details, 'pending');

            if ($loan->hasCancellationRequest()) {
                throw ValidationException::withMessages([
                    'peminjaman' => 'Pengajuan ini memiliki permintaan pembatalan dan tidak dapat dikonfirmasi.',
                ]);
            }

            $unknownDetails = array_diff(array_keys($selectedAllocations), $details->pluck('detail_peminjaman_id')->all());
            if ($unknownDetails !== []) {
                throw ValidationException::withMessages([
                    'allocations' => 'Pilihan alokasi tidak sesuai dengan barang pada pengajuan ini.',
                ]);
            }

            foreach ($details as $detail) {
                $this->reallocateReservation(
                    $loan,
                    $detail,
                    $admin,
                    $selectedAllocations[$detail->detail_peminjaman_id] ?? null,
                    $requireAllocationSelection,
                );
                $detail->status = 'dipinjam';
                $detail->save();
            }

            $loan->tanggal_pinjam = now();
            $loan->diproses_akun_id = $admin->akun_id;
            $loan->save();

            DB::afterCommit(function () use ($loan): void {
                $this->notifyBorrower(
                    $loan->akun_id,
                    'Pengajuan peminjaman Anda telah disetujui admin dan kini berstatus dipinjam.',
                );
            });
        }, 3);
    }

    private function reallocateReservation(
        Peminjaman $loan,
        DetailPeminjaman $detail,
        User $admin,
        ?array $selectedRoomQuantities,
        bool $requireAllocationSelection,
    ): void {
        // ASVS 2.2.1/2.2.2 and 2.3.3/2.3.4: validate room allocations against locked stock and move reservations atomically.
        $locations = LokasiBarang::query()
            ->with('ruangan')
            ->where('barang_id', $detail->barang_id)
            ->orderBy('ruangan_id')
            ->orderBy('lemari_id')
            ->orderBy('toolbox_id')
            ->orderBy('lokasi_barang_id')
            ->lockForUpdate()
            ->get();

        if ($locations->isEmpty()) {
            if ($selectedRoomQuantities !== null) {
                throw ValidationException::withMessages([
                    "allocations.{$detail->detail_peminjaman_id}" => 'Barang ini tidak memiliki lokasi stok yang dapat dipilih.',
                ]);
            }

            return;
        }

        $reservations = LogMutasiBarang::query()
            ->where('detail_peminjaman_id', $detail->detail_peminjaman_id)
            ->where('tipe', 'dipinjam')
            ->where('keterangan', 'like', self::RESERVATION_NOTE.'%')
            ->orderBy('created_at')
            ->orderBy('log_mutasi_barang_id')
            ->lockForUpdate()
            ->get();

        if ($reservations->isEmpty()) {
            if ($selectedRoomQuantities !== null) {
                throw ValidationException::withMessages([
                    "allocations.{$detail->detail_peminjaman_id}" => 'Lokasi reservasi barang ini belum tercatat.',
                ]);
            }

            return;
        }

        if ((int) $reservations->sum('jumlah') !== $detail->jumlah_pinjam) {
            throw ValidationException::withMessages([
                "allocations.{$detail->detail_peminjaman_id}" => 'Catatan reservasi stok tidak sesuai. Periksa data inventaris sebelum melanjutkan.',
            ]);
        }

        if ($reservations->contains(static fn (LogMutasiBarang $reservation): bool => $reservation->ruangan_id === null)) {
            if ($selectedRoomQuantities !== null) {
                throw ValidationException::withMessages([
                    "allocations.{$detail->detail_peminjaman_id}" => 'Lokasi reservasi barang ini tidak dapat dipastikan.',
                ]);
            }

            return;
        }

        if ($selectedRoomQuantities === null) {
            if ($requireAllocationSelection) {
                throw ValidationException::withMessages([
                    "allocations.{$detail->detail_peminjaman_id}" => 'Tentukan jumlah pengambilan dari tiap ruangan.',
                ]);
            }

            return;
        }

        $reservedByRoom = $reservations
            ->groupBy('ruangan_id')
            ->map(static fn (Collection $roomReservations): int => (int) $roomReservations->sum('jumlah'));
        $locationsByRoom = $locations
            ->filter(static fn (LokasiBarang $location): bool => $location->ruangan !== null)
            ->groupBy('ruangan_id');
        $availableByRoom = $locationsByRoom
            ->map(static fn (Collection $roomLocations, string $roomId): int => (int) $roomLocations->sum('stok_tersedia') + (int) ($reservedByRoom[$roomId] ?? 0));
        $selectedRoomQuantities = collect($selectedRoomQuantities)
            ->map(static fn (int|string $quantity): int => (int) $quantity)
            ->sortKeys();
        $validRoomIds = $locationsByRoom->keys()->map(static fn (string $roomId): string => $roomId)->all();
        if (array_diff(array_map('strval', $selectedRoomQuantities->keys()->all()), $validRoomIds) !== []) {
            throw ValidationException::withMessages([
                "allocations.{$detail->detail_peminjaman_id}" => 'Pilih ruangan yang memiliki stok untuk barang ini.',
            ]);
        }

        if ($selectedRoomQuantities->sum() !== $detail->jumlah_pinjam) {
            throw ValidationException::withMessages([
                "allocations.{$detail->detail_peminjaman_id}" => 'Jumlah yang dibagi per ruangan harus sama dengan jumlah barang yang dipinjam.',
            ]);
        }

        foreach ($selectedRoomQuantities as $roomId => $quantity) {
            if ($quantity > (int) ($availableByRoom[$roomId] ?? 0)) {
                throw ValidationException::withMessages([
                    "allocations.{$detail->detail_peminjaman_id}" => 'Jumlah pengambilan melebihi stok yang tersedia di salah satu ruangan.',
                ]);
            }
        }

        $selectedRoomQuantities = $selectedRoomQuantities
            ->filter(static fn (int $quantity): bool => $quantity > 0);
        $currentQuantitiesByRoom = $reservedByRoom->sortKeys();
        if ($currentQuantitiesByRoom->all() === $selectedRoomQuantities->all()) {
            return;
        }

        $sourceAllocations = [];
        foreach ($reservations as $reservation) {
            if ($reservation->ruangan_id === null) {
                throw ValidationException::withMessages([
                    "allocations.{$detail->detail_peminjaman_id}" => 'Lokasi reservasi barang ini tidak dapat dipastikan.',
                ]);
            }

            $location = $this->findLockedReservationLocation($locations, $reservation);
            if (! $location) {
                throw ValidationException::withMessages([
                    "allocations.{$detail->detail_peminjaman_id}" => 'Lokasi stok reservasi tidak ditemukan. Periksa data inventaris sebelum melanjutkan.',
                ]);
            }

            $stockBefore = $location->stok_tersedia;
            if ($stockBefore + $reservation->jumlah > $location->stok_total) {
                throw ValidationException::withMessages([
                    "allocations.{$detail->detail_peminjaman_id}" => 'Stok lokasi tidak cukup untuk memindahkan reservasi ini.',
                ]);
            }

            $location->stok_tersedia += $reservation->jumlah;
            $location->save();

            $sourceAllocations[] = [
                'location' => $location,
                'reservation' => $reservation,
                'stock_before' => $stockBefore,
            ];
        }

        foreach ($sourceAllocations as $sourceAllocation) {
            /** @var LogMutasiBarang $reservation */
            $reservation = $sourceAllocation['reservation'];
            $reservation->tipe = 'dipindahkan';
            $reservation->oleh_akun_id = $admin->akun_id;
            $reservation->stok_sebelum = $sourceAllocation['stock_before'];
            $reservation->stok_sesudah = $sourceAllocation['location']->stok_tersedia;
            $reservation->keterangan = 'Reservasi peminjaman dialihkan sesuai pilihan ruangan admin.';
            $reservation->save();
        }

        foreach ($selectedRoomQuantities as $roomId => $roomQuantity) {
            $remainingQuantity = $roomQuantity;
            foreach ($locationsByRoom[$roomId] as $location) {
                if ($remainingQuantity === 0) {
                    break;
                }

                $allocatedQuantity = min($remainingQuantity, $location->stok_tersedia);
                if ($allocatedQuantity === 0) {
                    continue;
                }

                $stockBefore = $location->stok_tersedia;
                $location->stok_tersedia -= $allocatedQuantity;
                $location->save();

                LogMutasiBarang::query()->create([
                    'barang_id' => $detail->barang_id,
                    'ruangan_id' => $location->ruangan_id,
                    'lemari_asal_id' => $location->lemari_id,
                    'akun_id' => $loan->akun_id,
                    'oleh_akun_id' => $admin->akun_id,
                    'detail_peminjaman_id' => $detail->detail_peminjaman_id,
                    'tipe' => 'dipinjam',
                    'jumlah' => $allocatedQuantity,
                    'stok_sebelum' => $stockBefore,
                    'stok_sesudah' => $location->stok_tersedia,
                    'keterangan' => LogMutasiBarang::keteranganWithToolbox(self::RESERVATION_NOTE, $location->toolbox_id),
                    'created_at' => now(),
                ]);

                $remainingQuantity -= $allocatedQuantity;
            }

            if ($remainingQuantity > 0) {
                throw ValidationException::withMessages([
                    "allocations.{$detail->detail_peminjaman_id}" => 'Stok ruangan berubah. Muat ulang halaman sebelum mencoba lagi.',
                ]);
            }
        }
    }

    private function notifyBorrower(string $borrowerId, string $message): void
    {
        $borrower = User::query()
            ->whereKey($borrowerId)
            ->where('is_active', true)
            ->first();

        if ($borrower instanceof User) {
            $this->notifications->notifyUser($borrower, $message);
        }
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
            ->where('keterangan', 'like', self::RESERVATION_NOTE.'%')
            ->orderBy('created_at')
            ->orderBy('log_mutasi_barang_id')
            ->lockForUpdate()
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
                $log->reservationToolboxId() ?? '',
            ]))
            ->map(static fn (Collection $logs): int => (int) $logs->sum('jumlah'));

        $remainingReservedByLocation = $reservations
            ->groupBy(static fn (LogMutasiBarang $reservation): string => implode('|', [
                $reservation->ruangan_id ?? '',
                $reservation->lemari_asal_id ?? '',
                $reservation->reservationToolboxId() ?? '',
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
                $location = $this->findReservationLocation($barang->barang_id, $reservation);

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
                'hilang' => 'penyesuaian',
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
                'keterangan' => LogMutasiBarang::keteranganWithToolbox($description, $location?->toolbox_id),
                'created_at' => now(),
            ]);
            $quantityRemaining -= $allocationQuantity;
        }

        $barang->save();
    }

    private function findReservationLocation(string $barangId, LogMutasiBarang $reservation): ?LokasiBarang
    {
        $locations = LokasiBarang::query()
            ->where('barang_id', $barangId)
            ->where('ruangan_id', $reservation->ruangan_id);

        $locations = $reservation->lemari_asal_id === null
            ? $locations->whereNull('lemari_id')
            : $locations->where('lemari_id', $reservation->lemari_asal_id);

        return $this->findLockedReservationLocation($locations->lockForUpdate()->get(), $reservation);
    }

    /**
     * @param  Collection<int, LokasiBarang>  $locations
     */
    private function findLockedReservationLocation(Collection $locations, LogMutasiBarang $reservation): ?LokasiBarang
    {
        $toolboxId = $reservation->reservationToolboxId();
        $matches = $locations->filter(static fn (LokasiBarang $location): bool => $location->ruangan_id === $reservation->ruangan_id
            && $location->lemari_id === $reservation->lemari_asal_id
            && ($toolboxId === null || $location->toolbox_id === $toolboxId));

        if ($matches->count() > 1) {
            throw ValidationException::withMessages([
                'peminjaman' => 'Lokasi stok reservasi tidak dapat dipastikan. Periksa data inventaris sebelum melanjutkan.',
            ]);
        }

        return $matches->first();
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
