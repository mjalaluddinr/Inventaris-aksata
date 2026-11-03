<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\DetailPeminjaman;
use App\Models\Kategori;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class BerandaController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $page = $request->query('page', 'home');
        $page = in_array($page, ['home', 'catalog', 'apply', 'history'], true) ? $page : 'home';
        $categories = collect();
        $featuredItems = collect();
        $inventoryKinds = 0;
        $userLoans = collect();

        if ($page === 'home') {
            $categories = Kategori::query()
                ->withCount('barangs')
                ->orderBy('nama_kategori')
                ->get();
            $featuredItems = Barang::query()
                ->with('kategori')
                ->withCount('detailPeminjamans')
                ->where('stok_tersedia', '>', 0)
                ->orderByDesc('detail_peminjamans_count')
                ->orderBy('stok_tersedia')
                ->limit(4)
                ->get();
            $inventoryKinds = Barang::query()->count();
            $userLoans = DetailPeminjaman::query()
                ->whereHas('peminjaman', fn (Builder $query) => $query->where('akun_id', $user->getKey()))
                ->get(['status']);
        }

        $loanCounts = [
            'dipinjam' => $userLoans->where('status', 'dipinjam')->count(),
            'pending' => $userLoans->where('status', 'pending')->count(),
            'dikembalikan' => $userLoans->where('status', 'dikembalikan')->count(),
            'closed' => $userLoans->whereIn('status', ['ditolak', 'dibatalkan'])->count(),
        ];
        $notifications = $this->notificationsFor($user);

        return view('pages.user.beranda', compact(
            'page',
            'categories',
            'featuredItems',
            'inventoryKinds',
            'loanCounts',
            'notifications',
        ));
    }

    /**
     * @return Collection<int, array{type: string, icon: string, title: string, time: string, url: string}>
     */
    private function notificationsFor(User $user): Collection
    {
        // ASVS 8.2.2: scope notifications to the authenticated borrower's own loans.
        $loans = $user->peminjamans()
            ->where(function (Builder $query): void {
                $query->whereHas('detailPeminjamans', fn (Builder $details) => $details->where('status', 'pending'))
                    ->orWhere(function (Builder $query): void {
                        $query->where('updated_at', '>=', now()->subDays(30))
                            ->whereHas('detailPeminjamans', fn (Builder $details) => $details->where('status', 'ditolak'));
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->where('tanggal_pinjam', '>=', now()->subDays(30))
                            ->whereHas('detailPeminjamans', fn (Builder $details) => $details->where('status', 'dipinjam'));
                    });
            })
            ->with(['detailPeminjamans' => function ($query): void {
                $query->whereIn('status', ['pending', 'dipinjam', 'ditolak'])
                    ->with('barang');
            }])
            ->latest('updated_at')
            ->limit(30)
            ->get();
        $dueLoans = $user->peminjamans()
            ->whereDate('tanggal_rencana_kembali', '<=', today()->addDays(3))
            ->whereHas('detailPeminjamans', fn (Builder $query) => $query->where('status', 'dipinjam'))
            ->with(['detailPeminjamans' => function ($query): void {
                $query->where('status', 'dipinjam')
                    ->with('barang');
            }])
            ->orderBy('tanggal_rencana_kembali')
            ->limit(30)
            ->get();
        $notifications = collect();

        foreach ($loans as $loan) {
            $details = $loan->detailPeminjamans;

            if ($details->contains('status', 'pending')) {
                $occurredAt = $loan->tanggal_pengajuan ?? $loan->created_at;
                $notifications->push([
                    'type' => 'pending',
                    'icon' => 'mdi:clock-outline',
                    'title' => "Pengajuan {$loan->kode_peminjaman} menunggu konfirmasi",
                    'time' => $this->formatNotificationTime($occurredAt),
                    'url' => route('user.beranda', ['page' => 'history', 'status' => 'active']),
                    'sort_at' => $occurredAt ?? now(),
                ]);
            }

            if ($details->contains('status', 'ditolak')) {
                $occurredAt = $loan->updated_at;
                $notifications->push([
                    'type' => 'rejected',
                    'icon' => 'mdi:close-circle-outline',
                    'title' => "Pengajuan {$loan->kode_peminjaman} ditolak",
                    'time' => $this->formatNotificationTime($occurredAt),
                    'url' => route('user.beranda', ['page' => 'history', 'status' => 'finished']),
                    'sort_at' => $occurredAt ?? now(),
                ]);
            }

            if (! $details->contains('status', 'dipinjam')) {
                continue;
            }

            if ($loan->tanggal_pinjam?->greaterThanOrEqualTo(now()->subDays(30))) {
                $occurredAt = $loan->tanggal_pinjam;
                $notifications->push([
                    'type' => 'confirmed',
                    'icon' => 'mdi:qrcode-scan',
                    'title' => "Pengajuan {$loan->kode_peminjaman} dikonfirmasi, QR sudah tersedia",
                    'time' => $this->formatNotificationTime($occurredAt),
                    'url' => route('user.beranda', ['page' => 'history', 'status' => 'active']),
                    'sort_at' => $occurredAt,
                ]);
            }

        }

        foreach ($dueLoans as $loan) {
            $dueDate = $loan->tanggal_rencana_kembali;

            if ($dueDate === null) {
                continue;
            }

            $today = today();
            if ($dueDate->isAfter($today)) {
                $daysUntilDue = (int) $today->diffInDays($dueDate);
                $dueLabel = $daysUntilDue.' hari lagi';
            } elseif ($dueDate->isSameDay($today)) {
                $dueLabel = 'hari ini';
            } else {
                $daysOverdue = (int) $dueDate->diffInDays($today);
                $dueLabel = "terlambat {$daysOverdue} hari";
            }

            foreach ($loan->detailPeminjamans as $detail) {
                $notifications->push([
                    'type' => 'due',
                    'icon' => 'mdi:calendar-alert',
                    'title' => ($detail->barang?->nama_barang ?? 'Barang pinjaman').' jatuh tempo '.$dueLabel,
                    'time' => 'Pengingat pengembalian',
                    'url' => route('user.beranda', ['page' => 'history', 'status' => 'active']),
                    'sort_at' => today(),
                ]);
            }
        }

        return $notifications
            ->sortByDesc('sort_at')
            ->take(8)
            ->values()
            ->map(static function (array $notification): array {
                unset($notification['sort_at']);

                return $notification;
            });
    }

    private function formatNotificationTime(?Carbon $occurredAt): string
    {
        if ($occurredAt === null) {
            return 'Baru saja';
        }

        $occurredAt = $occurredAt->copy();
        $now = now();

        if ($occurredAt->greaterThan($now)) {
            return 'Baru saja';
        }

        $minutes = (int) $occurredAt->diffInMinutes($now);

        if ($minutes < 60) {
            return $minutes < 1 ? 'Baru saja' : "{$minutes} menit lalu";
        }

        if ($occurredAt->isToday()) {
            return 'Hari ini';
        }

        if ($occurredAt->isYesterday()) {
            return 'Kemarin';
        }

        return (int) $occurredAt->diffInDays($now).' hari lalu';
    }
}
