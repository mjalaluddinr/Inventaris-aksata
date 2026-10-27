<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageRuangan\StoreRequest;
use App\Http\Requests\ManageRuangan\UpdateRequest;
use App\Models\Ruangan;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ManageRuangan extends Controller
{
    public function index(Request $request): View|string
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $search = trim($filters['search'] ?? '');

        $ruangans = Ruangan::query()
            ->withCount('lemaris')
            // ASVS 2.2.1, 1.2.4: validated search text is applied through bound Eloquent predicates.
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('kode_ruangan', 'like', '%'.$search.'%')
                        ->orWhere('nama_ruangan', 'like', '%'.$search.'%');
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        /** @var View $renderedView */
        $renderedView = view('pages.admin.ruangan', compact('ruangans', 'filters'));

        return $renderedView->fragmentIf($request->ajax(), 'ruangan-results');
    }

    public function store(StoreRequest $request)
    {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($validated) {
                Ruangan::create($validated);
            });

            return redirect()->route('admin.ruangan.index')->with('success', 'Ruangan berhasil ditambahkan.');
        } catch (Exception $e) {
            Log::error('Gagal menambahkan ruangan.', ['exception' => $e]);

            return redirect()->back()->with('error', 'Terjadi kesalahan pada sistem. Silakan coba lagi.');
        }
    }

    public function update(UpdateRequest $request, Ruangan $ruangan)
    {
        $validated = $request->validated();

        try {
            DB::transaction(function () use ($ruangan, $validated) {
                $ruangan->update($validated);
            });

            return redirect()->route('admin.ruangan.index')->with('success', 'Ruangan berhasil diupdate.');
        } catch (Exception $e) {
            Log::error('Gagal memperbarui ruangan.', ['exception' => $e]);

            return redirect()->back()->with('error', 'Terjadi kesalahan pada sistem. Silakan coba lagi.');
        }
    }

    public function destroy(Ruangan $ruangan)
    {
        try {
            // ASVS 2.3.2: Preserve the existing restriction while cabinets still depend on the room.
            $lemariCount = $ruangan->lemaris()->count();
            if ($lemariCount > 0) {
                return redirect()->back()->with('error', 'Ruangan tidak dapat dihapus karena masih memiliki '.$lemariCount.' lemari.');
            }

            DB::transaction(function () use ($ruangan) {
                $ruangan->delete();
            });

            return redirect()->route('admin.ruangan.index')->with('success', 'Ruangan berhasil dihapus.');
        } catch (Exception $e) {
            Log::error('Gagal menghapus ruangan.', ['exception' => $e]);

            return redirect()->back()->with('error', 'Terjadi kesalahan pada sistem. Silakan coba lagi.');
        }
    }
}
