<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageToolbox\StoreRequest;
use App\Http\Requests\ManageToolbox\UpdateRequest;
use App\Models\Toolbox;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ManageToolbox extends Controller
{
  public function index(Request $request): View|string
  {
    $filters = $request->validate([
      'search' => ['nullable', 'string', 'max:100'],
    ]);

    $toolboxes = Toolbox::query()
      ->withCount('lokasiBarangs')
      ->when($filters['search'] ?? null, function ($query, string $search): void {
        $query->where(function ($query) use ($search): void {
          $query->where('kode_toolbox', 'like', '%' . $search . '%')
            ->orWhere('nama_toolbox', 'like', '%' . $search . '%')
            ->orWhere('keterangan', 'like', '%' . $search . '%');
        });
      })
      ->orderByDesc('created_at')
      ->orderBy('nama_toolbox')
      ->paginate(10)
      ->withQueryString();

    /** @var View $renderedView */
    $renderedView = view('pages.admin.wadah', compact('toolboxes', 'filters'));

    return $renderedView->fragmentIf($request->ajax(), 'toolbox-results');
  }

  public function store(StoreRequest $request): RedirectResponse
  {
    $attributes = $request->safe()->only(['kode_toolbox', 'nama_toolbox', 'keterangan']);

    try {
      DB::transaction(function () use ($attributes): void {
        Toolbox::query()->create($attributes);
      });
    } catch (QueryException $exception) {
      if ($exception->getCode() !== '23000') {
        throw $exception;
      }

      return back()->withInput()->with('error', 'Kode wadah sudah digunakan. Gunakan kode lain.');
    }

    $destination = $request->validated('return_to') === 'toolbox'
      ? 'admin.toolbox.index'
      : 'admin.barang';

    return redirect()->route($destination)
      ->with('success', "Wadah {$attributes['nama_toolbox']} berhasil ditambahkan.");
  }

  public function update(UpdateRequest $request, Toolbox $toolbox): RedirectResponse
  {
    $attributes = $request->safe()->only(['nama_toolbox', 'keterangan']);
    $toolbox->update($attributes);

    return redirect()->route('admin.toolbox.index')
      ->with('success', "Wadah {$toolbox->nama_toolbox} berhasil diperbarui.");
  }

  public function destroy(Toolbox $toolbox): RedirectResponse
  {
    // ASVS V2.2.1: Enforce the location-usage rule before deactivating a toolbox.
    if ($toolbox->lokasiBarangs()->exists()) {
      return redirect()->route('admin.toolbox.index')
        ->with('error', 'Wadah tidak dapat dihapus karena masih digunakan oleh lokasi barang.');
    }

    $toolbox->delete();

    return redirect()->route('admin.toolbox.index')
      ->with('success', "Wadah {$toolbox->nama_toolbox} berhasil dihapus.");
  }
}
