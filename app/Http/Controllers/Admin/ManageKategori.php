<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageKategori\StoreRequest;
use App\Http\Requests\ManageKategori\UpdateRequest;
use App\Models\Kategori;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ManageKategori extends Controller
{
    public function index(Request $request): View|string
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $kategoris = Kategori::query()
            ->withCount('barangs')
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('nama_kategori', 'like', '%'.$search.'%')
                        ->orWhere('keterangan', 'like', '%'.$search.'%');
                });
            })
            ->orderByDesc('created_at')
            ->orderBy('nama_kategori')
            ->paginate(10)
            ->withQueryString();

        /** @var View $renderedView */
        $renderedView = view('pages.admin.kategori', compact('kategoris', 'filters'));

        return $renderedView->fragmentIf($request->ajax(), 'kategori-results');
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        Kategori::create($request->validated());

        return redirect()->route('admin.kategori')
            ->with('success', "Kategori {$kategori->nama_kategori} berhasil ditambahkan.");
    }

    public function update(UpdateRequest $request, Kategori $kategori): RedirectResponse
    {
        $kategori->update($request->validated());

        return redirect()->route('admin.kategori')
            ->with('success', "Kategori {$kategori->nama_kategori} berhasil diperbarui.");
    }

    public function destroy(Kategori $kategori): RedirectResponse
    {
        if ($kategori->barangs()->exists()) {
            return redirect()->route('admin.kategori')
                ->with('error', 'Kategori tidak dapat dihapus karena masih digunakan oleh barang.');
        }

        $kategori->delete();

        return redirect()->route('admin.kategori')
            ->with('success', "Kategori {$kategori->nama_kategori} berhasil dihapus.");
    }
}
