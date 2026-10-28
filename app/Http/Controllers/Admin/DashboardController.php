<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Lemari;
use App\Models\LogInput;
use App\Models\LogMutasiBarang;
use App\Models\Peminjaman;
use App\Models\Ruangan;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class DashboardController extends Controller
{
    private const MUTATION_LABELS = [
        'masuk' => 'Stok masuk',
        'dipinjam' => 'Peminjaman dikonfirmasi',
        'dikembalikan' => 'Barang dikembalikan',
        'dipakai' => 'Barang dipakai',
        'ditarik' => 'Barang ditarik',
        'dipindahkan' => 'Barang dipindahkan',
        'penyesuaian' => 'Stok disesuaikan',
    ];

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $canViewInventory = $user->canViewInventory();
        $canProcessLoans = $user->canProcessLoans();
        $canManageAllAccounts = $user->canManageAllAccounts();
        $statistics = [];
        $monthStart = now()->startOfMonth();
        $nextMonthStart = $monthStart->copy()->addMonth();

        if ($canViewInventory) {
            $barangSummary = $this->statisticSummary(Barang::query(), $monthStart, $nextMonthStart);
            $ruanganSummary = $this->statisticSummary(Ruangan::query(), $monthStart, $nextMonthStart);
            $rakSummary = $this->statisticSummary(Lemari::query(), $monthStart, $nextMonthStart);

            $statistics = [
                [
                    'label' => 'Barang terdaftar',
                    ...$barangSummary,
                    'icon' => 'mdi:package-variant-closed',
                    'tone' => 'teal',
                    'url' => route('admin.barang'),
                ],
                [
                    'label' => 'Ruangan aktif',
                    ...$ruanganSummary,
                    'icon' => 'mdi:door-open',
                    'tone' => 'blue',
                    'url' => route('admin.ruangan.index'),
                ],
                [
                    'label' => 'Rak penyimpanan',
                    ...$rakSummary,
                    'icon' => 'mdi:archive-outline',
                    'tone' => 'amber',
                    'url' => route('admin.rak.index'),
                ],
            ];
        }

        $pendingLoanCount = 0;
        $pendingLoans = collect();

        if ($canProcessLoans) {
            // ASVS V8.2: Only users authorized to process loans receive loan counts and borrower data.
            $pendingLoanQuery = Peminjaman::query()
                ->whereHas('detailPeminjamans', static fn (Builder $details): Builder => $details->where('status', 'pending'));
            $pendingLoanSummary = $this->statisticSummary(clone $pendingLoanQuery, $monthStart, $nextMonthStart);
            $pendingLoanCount = $pendingLoanSummary['value'];
            $pendingLoans = $pendingLoanQuery
                ->with([
                    'akun:akun_id,username,first_name,last_name',
                    'detailPeminjamans' => static fn (HasMany $details): HasMany => $details
                        ->where('status', 'pending')
                        ->select(['detail_peminjaman_id', 'peminjaman_id', 'barang_id', 'jumlah_pinjam'])
                        ->with('barang:barang_id,nama_barang'),
                ])
                ->select(['peminjaman_id', 'akun_id', 'kode_peminjaman', 'keperluan', 'tanggal_pengajuan'])
                ->orderByDesc('tanggal_pengajuan')
                ->orderByDesc('peminjaman_id')
                ->limit(5)
                ->get();

            $statistics[] = [
                'label' => 'Peminjaman pending',
                ...$pendingLoanSummary,
                'icon' => 'mdi:clipboard-clock-outline',
                'tone' => 'orange',
                'url' => route('admin.peminjaman'),
            ];
        }

        $activities = $this->recentActivities($canViewInventory, $canProcessLoans, $canManageAllAccounts);

        return view('pages.admin.dashboard', compact(
            'activities',
            'canProcessLoans',
            'pendingLoanCount',
            'pendingLoans',
            'statistics',
        ));
    }

    /**
     * @return array{value: int, month_value: int, month_label: string}
     */
    private function statisticSummary(Builder $query, CarbonInterface $monthStart, CarbonInterface $nextMonthStart): array
    {
        $summary = $query
            ->selectRaw(
                'COUNT(*) as total_count, SUM(CASE WHEN created_at >= ? AND created_at < ? THEN 1 ELSE 0 END) as month_count, MAX(created_at) as latest_created_at',
                [$monthStart, $nextMonthStart],
            )
            ->firstOrFail();
        $monthCount = (int) ($summary->month_count ?? 0);
        $latestCreatedAt = filled($summary->latest_created_at)
            ? Carbon::parse($summary->latest_created_at)
            : null;

        return [
            'value' => (int) $summary->total_count,
            'month_value' => $monthCount,
            'month_label' => $this->statisticRecencyLabel($monthCount, $latestCreatedAt),
        ];
    }

    private function statisticRecencyLabel(int $monthCount, ?CarbonInterface $latestCreatedAt): string
    {
        if ($monthCount > 0) {
            return '+'.number_format($monthCount, 0, ',', '.').' bulan ini';
        }

        if ($latestCreatedAt === null) {
            return 'Belum ada data';
        }

        if ($latestCreatedAt->isFuture()) {
            return 'Baru saja';
        }

        $currentMonth = now();
        $monthsAgo = (($currentMonth->year - $latestCreatedAt->year) * 12)
            + ($currentMonth->month - $latestCreatedAt->month);

        if ($monthsAgo >= 12) {
            return intdiv($monthsAgo, 12).' tahun lalu';
        }

        return max(1, $monthsAgo).' bulan lalu';
    }

    /**
     * @return Collection<int, array{title: string, description: string, occurred_at: CarbonInterface, type: string}>
     */
    private function recentActivities(bool $canViewInventory, bool $canProcessLoans, bool $canManageAllAccounts): Collection
    {
        $activities = collect();

        if ($canViewInventory || $canManageAllAccounts) {
            $importTypes = $canManageAllAccounts ? ['barang', 'akun'] : ['barang'];

            foreach (LogInput::query()
                ->whereIn('jenis_input', $importTypes)
                ->orderByRaw('COALESCE(selesai_at, mulai_at) DESC')
                ->limit(5)
                ->get(['jenis_input', 'nama_file', 'total_baris', 'baris_berhasil', 'status', 'mulai_at', 'selesai_at']) as $log) {
                $this->addActivity(
                    $activities,
                    $log->jenis_input === 'barang' ? 'Import Excel barang' : 'Import Excel akun',
                    "{$log->nama_file} · {$log->baris_berhasil} dari {$log->total_baris} baris berhasil",
                    $log->selesai_at ?? $log->mulai_at,
                    'import',
                );
            }
        }

        if ($canViewInventory || $canProcessLoans) {
            $mutations = LogMutasiBarang::query()
                ->whereNull('log_input_id')
                ->when(! $canProcessLoans, static fn (Builder $query): Builder => $query->whereNotIn('tipe', ['dipinjam', 'dikembalikan']))
                ->with('barang:barang_id,nama_barang')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['log_mutasi_barang_id', 'barang_id', 'tipe', 'jumlah', 'keterangan', 'created_at']);

            foreach ($mutations as $mutation) {
                $this->addActivity(
                    $activities,
                    self::MUTATION_LABELS[$mutation->tipe] ?? 'Aktivitas stok',
                    trim(($mutation->barang?->nama_barang ?? 'Barang inventaris')." · {$mutation->jumlah} unit · {$mutation->keterangan}", ' ·'),
                    $mutation->created_at,
                    'mutation',
                );
            }
        }

        if ($canViewInventory) {
            foreach ($this->withoutImportGeneratedRecords(Barang::query(), 'barang')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['nama_barang', 'created_at']) as $item) {
                $this->addActivity($activities, 'Barang ditambahkan', $item->nama_barang, $item->created_at, 'item');
            }

            foreach ($this->withoutImportGeneratedRecords(Kategori::query(), 'kategori')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['nama_kategori', 'created_at']) as $category) {
                $this->addActivity($activities, 'Kategori ditambahkan', $category->nama_kategori, $category->created_at, 'category');
            }

            foreach ($this->withoutImportGeneratedRecords(Ruangan::query(), 'ruangan')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['nama_ruangan', 'created_at']) as $room) {
                $this->addActivity($activities, 'Ruangan ditambahkan', $room->nama_ruangan, $room->created_at, 'room');
            }

            foreach ($this->withoutImportGeneratedRecords(Lemari::query(), 'lemari')
                ->with('ruangan:ruangan_id,nama_ruangan')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['ruangan_id', 'nama_lemari', 'created_at']) as $rack) {
                $description = $rack->ruangan?->nama_ruangan
                    ? "{$rack->nama_lemari} · {$rack->ruangan->nama_ruangan}"
                    : $rack->nama_lemari;
                $this->addActivity($activities, 'Rak ditambahkan', $description, $rack->created_at, 'rack');
            }
        }

        if ($canProcessLoans) {
            foreach (Peminjaman::query()
                ->with('akun:akun_id,username,first_name,last_name')
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['akun_id', 'keperluan', 'created_at']) as $loan) {
                $borrowerName = trim(($loan->akun?->first_name ?? '').' '.($loan->akun?->last_name ?? ''));
                $borrowerName = $borrowerName !== '' ? $borrowerName : ($loan->akun?->username ?? 'Peminjam');
                $description = filled($loan->keperluan)
                    ? "{$borrowerName} · {$loan->keperluan}"
                    : $borrowerName;

                $this->addActivity($activities, 'Pengajuan peminjaman masuk', $description, $loan->created_at, 'loan');
            }
        }

        $typePriority = [
            'import' => 0,
            'mutation' => 1,
            'item' => 2,
            'category' => 3,
            'room' => 4,
            'rack' => 5,
            'loan' => 6,
        ];

        return $activities
            ->sort(function (array $left, array $right) use ($typePriority): int {
                $timeComparison = $right['occurred_at']->getTimestamp() <=> $left['occurred_at']->getTimestamp();

                if ($timeComparison !== 0) {
                    return $timeComparison;
                }

                return ($typePriority[$left['type']] ?? 99) <=> ($typePriority[$right['type']] ?? 99);
            })
            ->take(5)
            ->values();
    }

    /**
     * @param  Collection<int, array{title: string, description: string, occurred_at: CarbonInterface, type: string}>  $activities
     */
    private function addActivity(
        Collection $activities,
        string $title,
        string $description,
        CarbonInterface $occurredAt,
        string $type,
    ): void {
        $activities->push([
            'title' => $title,
            'description' => $description,
            'occurred_at' => $occurredAt,
            'type' => $type,
        ]);
    }

    /**
     * Exclude records created as part of an Excel import so the feed shows the import as one activity.
     *
     * @param  Builder<Barang|Kategori|Lemari|Ruangan>  $query
     * @param  'barang'|'kategori'|'lemari'|'ruangan'  $table
     * @return Builder<Barang|Kategori|Lemari|Ruangan>
     */
    private function withoutImportGeneratedRecords(Builder $query, string $table): Builder
    {
        // ASVS V1.2.4: This table name comes only from the fixed allowlist at the call sites; values stay parameterized.
        return $query->whereNotExists(static function (QueryBuilder $imports) use ($table): void {
            $imports->selectRaw('1')
                ->from('log_input')
                ->where('jenis_input', 'barang')
                ->whereColumn('log_input.mulai_at', '<=', "{$table}.created_at")
                ->where(static function (QueryBuilder $window) use ($table): void {
                    $window->whereNull('log_input.selesai_at')
                        ->orWhereColumn('log_input.selesai_at', '>=', "{$table}.created_at");
                });
        });
    }
}
