<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageLemari\StoreRequest;
use App\Http\Requests\ManageLemari\UpdateRequest;
use App\Models\Lemari;
use App\Models\Ruangan;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; // ✅ TAMBAHKAN BARIS INI
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ManageLemari extends Controller
{
    public function index(Request $request): View|string
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'ruangan_id' => [
                'nullable',
                'string',
                Rule::exists('ruangan', 'ruangan_id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
        ]);

        $search = $filters['search'] ?? '';

        $lemaris = Lemari::query()
            ->select([
                'lemari_id',
                'ruangan_id',
                'kode_lemari',
                'nama_lemari',
                'keterangan',
                'created_at',
            ])
            ->with(['ruangan:ruangan_id,nama_ruangan'])
            ->withCount('lokasiBarangs')
            ->when($filters['ruangan_id'] ?? null, fn ($query, string $id) => $query->where('ruangan_id', $id))
            // ASVS 2.2.1, 1.2.4: validated search input stays in parameterized Eloquent predicates.
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('kode_lemari', 'like', '%'.$search.'%')
                        ->orWhere('nama_lemari', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $ruangans = $request->ajax()
            ? collect()
            : Ruangan::whereNull('deleted_at')->get(['ruangan_id', 'nama_ruangan']);
        $filteredRuangan = filled($filters['ruangan_id'] ?? null)
            ? ($ruangans->firstWhere('ruangan_id', $filters['ruangan_id'])
                ?? Ruangan::query()->find($filters['ruangan_id']))
            : null;

        /** @var View $renderedView */
        $renderedView = view('pages.admin.lemari', compact('lemaris', 'ruangans', 'filters', 'filteredRuangan'));

        return $renderedView->fragmentIf($request->ajax(), 'lemari-results');
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated) {
                Lemari::create($validated);
            });

            return redirect()->route('admin.rak.index')
                ->with('success', "Lemari/Rak {$validated['nama_lemari']} berhasil ditambahkan.");
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return redirect()->back()->withInput()->with('error', 'Kode lemari untuk ruangan ini sudah digunakan.');
            }

            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan pada sistem.');
        }
    }

    public function update(UpdateRequest $request, Lemari $lemari): RedirectResponse
    {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($lemari, $validated) {
                $lemari->update($validated);
            });

            return redirect()->route('admin.rak.index')
                ->with('success', "Lemari/Rak {$lemari->nama_lemari} berhasil diperbarui.");
        } catch (QueryException $e) {
            if ($e->getCode() === '23000') {
                return redirect()->back()->withInput()->with('error', 'Kode lemari untuk ruangan ini sudah digunakan.');
            }

            return redirect()->back()->withInput()->with('error', 'Terjadi kesalahan pada sistem.');
        }
    }

    public function destroy(Lemari $lemari): RedirectResponse
    {
        try {
            if ($lemari->lokasiBarangs()->exists()) {
                return redirect()->back()->with(
                    'error',
                    "Lemari/Rak {$lemari->nama_lemari} tidak dapat dihapus karena masih memiliki stok barang.",
                );
            }

            DB::transaction(function () use ($lemari) {
                $lemari->delete();
            });

            return redirect()->route('admin.rak.index')
                ->with('success', "Lemari/Rak {$lemari->nama_lemari} berhasil dihapus.");
        } catch (\Exception $e) {
            Log::error('Gagal menghapus lemari: '.$e->getMessage()); // ✅ Sekarang Log sudah terdefinisi

            return redirect()->back()->with('error', 'Terjadi kesalahan pada sistem.');
        }
    }
}
