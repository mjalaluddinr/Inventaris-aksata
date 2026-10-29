<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LogMutasiBarang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class LogMutasiController extends Controller
{
    private const MUTATION_LABELS = [
        'masuk' => 'Barang Masuk',
        'dipinjam' => 'Dipinjam',
        'dikembalikan' => 'Dikembalikan',
        'dipakai' => 'Dipakai di Ruangan',
        'ditarik' => 'Ditarik dari Ruangan',
        'dipindahkan' => 'Dipindahkan',
        'penyesuaian' => 'Penyesuaian',
    ];

    public function index(Request $request): View|string
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'tipe' => ['nullable', Rule::in(array_keys(self::MUTATION_LABELS))],
        ]);
        $search = trim($filters['search'] ?? '');

        $logs = LogMutasiBarang::query()
            ->with([
                'barang',
                'ruangan',
                'lemariAsal.ruangan',
                'lemariTujuan.ruangan',
                'akun',
                'olehAkun',
            ])
            ->when($filters['tipe'] ?? null, fn (Builder $query, string $type) => $query->where('tipe', $type))
            ->when($search !== '', function (Builder $query) use ($search): void {
                // ASVS 2.2.1 / 1.2.4: bind the validated search term to known log and relation fields.
                $query->where(function (Builder $query) use ($search): void {
                    $query->where('keterangan', 'like', '%'.$search.'%')
                        ->orWhere('tipe', 'like', '%'.$search.'%')
                        ->orWhereHas('barang', function (Builder $barang) use ($search): void {
                            $barang->where('kode_barang', 'like', '%'.$search.'%')
                                ->orWhere('nama_barang', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('ruangan', function (Builder $ruangan) use ($search): void {
                            $ruangan->where('kode_ruangan', 'like', '%'.$search.'%')
                                ->orWhere('nama_ruangan', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('lemariAsal', function (Builder $lemari) use ($search): void {
                            $lemari->where('kode_lemari', 'like', '%'.$search.'%')
                                ->orWhere('nama_lemari', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('lemariTujuan', function (Builder $lemari) use ($search): void {
                            $lemari->where('kode_lemari', 'like', '%'.$search.'%')
                                ->orWhere('nama_lemari', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('akun', function (Builder $akun) use ($search): void {
                            $akun->where('username', 'like', '%'.$search.'%')
                                ->orWhere('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%');
                        })
                        ->orWhereHas('olehAkun', function (Builder $akun) use ($search): void {
                            $akun->where('username', 'like', '%'.$search.'%')
                                ->orWhere('first_name', 'like', '%'.$search.'%')
                                ->orWhere('last_name', 'like', '%'.$search.'%');
                        });
                });
            })
            ->orderByDesc('created_at')
            ->orderByDesc('log_mutasi_barang_id')
            ->paginate(20)
            ->withQueryString();

        $renderedView = view('pages.admin.log_mutasi', [
            'logs' => $logs,
            'filters' => $filters,
            'mutationLabels' => self::MUTATION_LABELS,
        ]);

        return $renderedView->fragmentIf($request->ajax(), 'mutation-results');
    }
}
