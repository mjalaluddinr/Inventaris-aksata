<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\DetailPeminjaman;
use App\Models\Kategori;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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

        return view('pages.user.beranda', compact(
            'page',
            'categories',
            'featuredItems',
            'inventoryKinds',
            'loanCounts',
        ));
    }
}
