<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Models\DetailPeminjaman;
use App\Models\Kategori;
use App\Models\Peminjaman;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BerandaController extends Controller
{
    private const LEGACY_PAGE_ROUTES = [
        'catalog' => 'user.catalog',
        'apply' => 'user.apply',
        'history' => 'user.history',
    ];

    public function __invoke(Request $request): View|RedirectResponse
    {
        $legacyPage = $request->query('page');
        if (is_string($legacyPage) && isset(self::LEGACY_PAGE_ROUTES[$legacyPage])) {
            $query = $request->query();
            unset($query['page']);

            return redirect()->route(self::LEGACY_PAGE_ROUTES[$legacyPage], $query);
        }

        return $this->renderPage($request, 'home');
    }

    public function catalog(Request $request): View
    {
        return $this->renderPage($request, 'catalog');
    }

    public function apply(Request $request): View
    {
        return $this->renderPage($request, 'apply');
    }

    public function history(Request $request): View
    {
        return $this->renderPage($request, 'history');
    }

    public function detail(Request $request): View
    {
        $barangId = $request->query('barang');

        // ASVS V2.2.1: Require a valid inventory UUID before querying the requested item.
        abort_unless(is_string($barangId) && Str::isUuid($barangId), 404);

        $barang = Barang::query()
            ->with('kategori:kategori_id,nama_kategori')
            ->whereKey($barangId)
            ->firstOrFail([
                'barang_id',
                'kategori_id',
                'nama_barang',
                'image_path',
                'satuan',
                'stok_total',
                'stok_tersedia',
                'kondisi',
                'spesifikasi',
            ]);

        $relatedProducts = Barang::query()
            ->with('kategori:kategori_id,nama_kategori')
            ->select([
                'barang_id',
                'kategori_id',
                'nama_barang',
                'image_path',
                'satuan',
                'stok_total',
                'stok_tersedia',
                'kondisi',
                'spesifikasi',
            ])
            ->withCount('detailPeminjamans')
            ->where('stok_tersedia', '>', 0)
            ->where('barang_id', '!=', $barang->getKey())
            ->orderByDesc('detail_peminjamans_count')
            ->orderBy('nama_barang')
            ->limit(3)
            ->get();

        return view('pages.user.detail-barang', compact('barang', 'relatedProducts'));
    }

    public function cartItems(Request $request): JsonResponse
    {
        // ASVS V2.2.1/2.2.2: Bound and validate browser-provided inventory identifiers at the server boundary.
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:50'],
            'ids.*' => ['required', 'uuid'],
        ]);

        $items = Barang::query()
            ->with('kategori:kategori_id,nama_kategori')
            ->whereIn('barang_id', $validated['ids'])
            ->get(['barang_id', 'nama_barang', 'kategori_id', 'stok_tersedia'])
            ->map(static fn (Barang $barang): array => [
                'id' => $barang->barang_id,
                'name' => $barang->nama_barang,
                'category' => $barang->kategori?->nama_kategori ?? 'Tanpa kategori',
                'stock' => $barang->stok_tersedia,
            ]);

        // ASVS V14.3.2: Cart item state is user-specific and must not be cached.
        return response()->json(['items' => $items])->header('Cache-Control', 'no-store');
    }

    private function renderPage(Request $request, string $page): View
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $categories = collect();
        $featuredItems = collect();
        $inventoryKinds = 0;
        $loanStatusCounts = collect();
        $catalogItems = collect();
        $search = '';
        $historySearch = '';
        $selectedCategory = '';
        $historyStatus = $request->query('status') === 'finished' ? 'finished' : 'active';
        $historyLoans = collect();
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
            $loanStatusCounts = DetailPeminjaman::query()
                ->whereHas('peminjaman', fn (Builder $query) => $query->where('akun_id', $user->getKey()))
                ->selectRaw('status, COUNT(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status');
        }

        if ($page === 'catalog') {
            $searchQuery = $request->query('q', '');
            $categoryQuery = $request->query('category', '');
            $search = is_string($searchQuery) ? mb_substr(trim($searchQuery), 0, 100) : '';
            $selectedCategory = is_string($categoryQuery) ? $categoryQuery : '';
            $categories = Kategori::query()
                ->orderBy('nama_kategori')
                ->get();
            $catalogItems = Barang::query()
                ->with('kategori')
                ->where('stok_tersedia', '>', 0)
                ->when($search !== '', fn (Builder $query): Builder => $query->where(function (Builder $query) use ($search): void {
                    $query->where('nama_barang', 'like', '%'.$search.'%')
                        ->orWhere('kode_barang', 'like', '%'.$search.'%');
                }))
                ->when($selectedCategory !== '', fn (Builder $query): Builder => $query->where('kategori_id', $selectedCategory))
                ->orderBy('nama_barang')
                ->paginate(12, ['*'], 'catalog_page')
                ->withQueryString();
        }

        if ($page === 'history') {
            $searchQuery = $request->query('q', '');
            // ASVS V2.2.1: Accept only a bounded scalar search term.
            $historySearch = is_string($searchQuery) ? mb_substr(trim($searchQuery), 0, 100) : '';

            $historyLoans = Peminjaman::query()
                ->with(['detailPeminjamans.barang', 'diprosesAkun'])
                ->where('akun_id', $user->getKey())
                ->when($historyStatus === 'active', fn (Builder $query): Builder => $query->whereHas(
                    'detailPeminjamans',
                    fn (Builder $details): Builder => $details->whereIn('status', ['pending', 'dipinjam']),
                ))
                ->when($historyStatus === 'finished', fn (Builder $query): Builder => $query
                    ->whereHas('detailPeminjamans')
                    ->whereDoesntHave(
                        'detailPeminjamans',
                        fn (Builder $details): Builder => $details->whereIn('status', ['pending', 'dipinjam']),
                    ))
                // ASVS V1.2.4: Keep search input as a bound query value.
                ->when($historySearch !== '', fn (Builder $query): Builder => $query->where(
                    fn (Builder $query): Builder => $query
                        ->where('keperluan', 'like', '%'.$historySearch.'%')
                        ->orWhereHas('detailPeminjamans.barang', fn (Builder $barang): Builder => $barang
                            ->where('nama_barang', 'like', '%'.$historySearch.'%')
                            ->orWhere('kode_barang', 'like', '%'.$historySearch.'%')),
                ))
                ->latest('tanggal_pengajuan')
                ->paginate(8, ['*'], 'history_page')
                ->withQueryString();
        }

        $loanCounts = [
            'dipinjam' => (int) $loanStatusCounts->get('dipinjam', 0),
            'pending' => (int) $loanStatusCounts->get('pending', 0),
            'dikembalikan' => (int) $loanStatusCounts->get('dikembalikan', 0),
            'closed' => (int) $loanStatusCounts->get('ditolak', 0)
                + (int) $loanStatusCounts->get('dibatalkan', 0),
        ];

        return view('pages.user.beranda', compact(
            'page',
            'categories',
            'featuredItems',
            'inventoryKinds',
            'loanCounts',
            'catalogItems',
            'search',
            'selectedCategory',
            'historySearch',
            'historyStatus',
            'historyLoans',
        ));
    }
}
