<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageBarang\ImportRequest;
use App\Http\Requests\ManageBarang\StoreRequest;
use App\Http\Requests\ManageBarang\UpdateRequest;
use App\Jobs\ImportBarangJob;
use App\Models\Barang;
use App\Models\BarangDipakai;
use App\Models\Kategori;
use App\Models\Lemari;
use App\Models\LogInput;
use App\Models\LogMutasiBarang;
use App\Models\LokasiBarang;
use App\Models\Ruangan;
use App\Models\Toolbox;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader2;
use ZipArchive;

class ManageBarang extends Controller
{
    private const MAX_IMPORT_ROWS = 1000;

    private const MAX_IMPORT_COLUMNS = 32;

    private const MAX_IMPORT_SHEETS = 10;

    private const MAX_IMPORT_ZIP_ENTRIES = 2000;

    private const MAX_IMPORT_IMAGE_BYTES = 5242880;

    private const MAX_IMPORT_TOTAL_IMAGE_BYTES = 52428800;

    private const MAX_IMPORT_IMAGE_PIXELS = 40000000;

    private const IMPORT_COLUMNS = [
        'kategori' => ['kategori', 'nama_kategori'],
        'keterangan_kategori' => ['keterangan_kategori'],
        'kode_ruangan' => ['kode_ruangan'],
        'ruangan' => ['ruangan', 'nama_ruangan'],
        'keterangan_ruangan' => ['keterangan_ruangan'],
        'kode_lemari' => ['kode_lemari'],
        'lemari' => ['lemari', 'nama_lemari'],
        'keterangan_lemari' => ['keterangan_lemari'],
        'nomor_barang' => ['no', 'nomor', 'nomor_barang'],
        'kode_barang' => ['kode_barang', 'kode'],
        'nama_barang' => ['nama_barang', 'nama'],
        'satuan' => ['satuan'],
        'stok_total' => ['stok_total', 'jumlah_total', 'jumlah_total_lokasi', 'jumlah'],
        'stok_tersedia' => ['stok_tersedia', 'jumlah_tersedia', 'jumlah_tersedia_lokasi'],
        'kondisi' => ['kondisi'],
        'kelayakan' => ['kelayakan'],
        'spesifikasi' => ['spesifikasi'],
        'image' => ['image', 'gambar', 'gambar_barang', 'image_barang', 'foto', 'file_gambar', 'nama_file_gambar'],
    ];

    public function index(Request $request): View|string
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kategori_id' => [
                'nullable',
                'string',
                Rule::exists('kategori', 'kategori_id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'ruangan_id' => [
                'nullable',
                'string',
                Rule::exists('ruangan', 'ruangan_id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'lemari_id' => [
                'nullable',
                'string',
                Rule::exists('lemari', 'lemari_id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'kondisi' => ['nullable', Rule::in(['baru', 'lama'])],
            'kelayakan' => ['nullable', Rule::in(['layak', 'tidak_layak'])],
            'barang_id' => [
                'nullable',
                'uuid',
                Rule::exists('barang', 'barang_id')->whereNull('deleted_at'),
            ],
            'panel' => ['nullable', Rule::in(['history', 'usage'])],
            'mutation_page' => ['nullable', 'integer', 'min:1'],
            'usage_page' => ['nullable', 'integer', 'min:1'],
        ]);

        $applyBarangFilters = static fn (Builder $query): Builder => $query
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('kode_barang', 'like', '%'.$search.'%')
                        ->orWhere('nama_barang', 'like', '%'.$search.'%')
                        ->orWhere('spesifikasi', 'like', '%'.$search.'%');
                });
            })
            ->when($filters['kategori_id'] ?? null, fn ($query, string $id) => $query->where('kategori_id', $id))
            ->when($filters['ruangan_id'] ?? null, function ($query, string $id): void {
                $query->whereHas('lokasiBarangs', fn ($locations) => $locations->where('ruangan_id', $id));
            })
            ->when($filters['lemari_id'] ?? null, function ($query, string $id): void {
                $query->whereHas('lokasiBarangs', fn ($locations) => $locations->where('lemari_id', $id));
            })
            ->when($filters['kondisi'] ?? null, fn ($query, string $value) => $query->where('kondisi', $value))
            ->when($filters['kelayakan'] ?? null, fn ($query, string $value) => $query->where('kelayakan', $value));

        $barangs = $applyBarangFilters(Barang::query())
            ->with([
                'kategori',
                'lokasiBarangs' => fn ($query) => $query->orderBy('created_at')->orderBy('lokasi_barang_id'),
                'lokasiBarangs.ruangan',
                'lokasiBarangs.lemari',
                'lokasiBarangs.toolbox',
            ])
            ->when(
                $request->user()?->canManageInventory(),
                fn (Builder $query) => $query->withCount([
                    'barangDipakais as active_usages_count' => fn (Builder $query) => $query->where('status', 'aktif'),
                    'detailPeminjamans as active_loans_count' => fn (Builder $query) => $query->whereIn('status', ['pending', 'dipinjam']),
                ]),
            )
            ->latest()
            ->orderByDesc('kode_barang')
            ->paginate(10)
            ->withQueryString();

        $kategoris = Kategori::query()->orderBy('nama_kategori')->get(['kategori_id', 'nama_kategori']);
        $ruangans = Ruangan::query()->orderBy('nama_ruangan')->get(['ruangan_id', 'nama_ruangan']);
        $lemaris = Lemari::query()->orderBy('nama_lemari')->get(['lemari_id', 'ruangan_id', 'nama_lemari']);
        $toolboxes = Toolbox::query()->orderBy('nama_toolbox')->get(['toolbox_id', 'kode_toolbox', 'nama_toolbox']);
        $selectedBarang = null;
        $adminAccounts = collect();
        $mutationLogs = collect();
        $activeUsages = collect();
        $usageLocations = collect();
        $usageBarangs = collect();

        if ($request->user()?->canManageAllAccounts()) {
            $usageBarangs = $barangs->getCollection();
            $selectedBarang = $usageBarangs->firstWhere('barang_id', $filters['barang_id'] ?? null)
                ?? $usageBarangs->first();

            if ($selectedBarang instanceof Barang) {
                $adminAccounts = User::query()
                    ->where('role', 'admin')
                    ->where('is_active', true)
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get(['akun_id', 'first_name', 'last_name', 'username']);
                $mutationLogs = $selectedBarang->logMutasiBarangs()
                    ->with(['ruangan', 'lemariAsal', 'lemariTujuan', 'akun', 'olehAkun', 'barangDipakai.ruangan'])
                    ->orderByDesc('created_at')
                    ->orderByDesc('log_mutasi_barang_id')
                    ->paginate(5, ['*'], 'mutation_page')
                    ->appends($request->except('mutation_page'))
                    ->appends([
                        'barang_id' => $selectedBarang->barang_id,
                        'panel' => 'history',
                    ]);
                $activeUsages = BarangDipakai::query()
                    ->where('status', 'aktif')
                    ->whereHas('barang')
                    ->with(['barang', 'ruangan', 'akun'])
                    ->orderByDesc('tanggal_pasang')
                    ->orderByDesc('barang_dipakai_id')
                    ->paginate(10, ['*'], 'usage_page')
                    ->appends($request->except(['fragment', 'mutation_page', 'usage_page']))
                    ->appends([
                        'barang_id' => $selectedBarang->barang_id,
                        'panel' => 'usage',
                    ]);
                $usageLocations = $ruangans;
            }

        }

        /** @var View $renderedView */
        $renderedView = view('pages.admin.barang', compact(
            'activeUsages',
            'adminAccounts',
            'barangs',
            'filters',
            'kategoris',
            'lemaris',
            'mutationLogs',
            'ruangans',
            'selectedBarang',
            'toolboxes',
            'usageBarangs',
            'usageLocations',
        ));

        if ($request->ajax()) {
            if ($request->header('X-Fragment') === 'barang-usage-panel' || $request->input('fragment') === 'barang-usage-panel') {
                return $renderedView->fragment('barang-usage-panel');
            }

            return $renderedView->fragment('barang-results');
        }

        return $renderedView;
    }

    public function recordUsage(Request $request, Barang $barang): RedirectResponse|JsonResponse
    {
        // ASVS 2.2.2: Validate the usage destination independently from the storage location.
        $attributes = $request->validate([
            'jumlah' => ['required', 'integer', 'min:1'],
            'ruangan_id' => [
                'required',
                'uuid',
                Rule::exists('ruangan', 'ruangan_id')->whereNull('deleted_at'),
            ],
            'akun_id' => [
                'required',
                'uuid',
                Rule::exists('akun', 'akun_id')->where(fn ($query) => $query
                    ->where('role', 'admin')
                    ->where('is_active', true)
                    ->whereNull('deleted_at')),
            ],
            'tanggal_pasang' => ['required', 'date', 'before_or_equal:today'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ]);
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        DB::transaction(function () use ($attributes, $actor, $barang): void {
            $lockedBarang = Barang::query()
                ->whereKey($barang->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $adminAccount = User::query()
                ->whereKey($attributes['akun_id'])
                ->where('role', 'admin')
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $adminAccount instanceof User) {
                throw ValidationException::withMessages([
                    'akun_id' => 'Akun yang memakai barang harus merupakan admin aktif.',
                ]);
            }

            $usageRoom = Ruangan::query()
                ->whereKey($attributes['ruangan_id'])
                ->whereNull('deleted_at')
                ->lockForUpdate()
                ->first();

            if (! $usageRoom instanceof Ruangan) {
                throw ValidationException::withMessages([
                    'ruangan_id' => 'Ruangan pemakaian tidak ditemukan atau sudah tidak aktif.',
                ]);
            }

            $locations = $lockedBarang->lokasiBarangs()
                ->where('stok_tersedia', '>', 0)
                ->orderBy('lokasi_barang_id')
                ->lockForUpdate()
                ->get();
            $quantity = (int) $attributes['jumlah'];
            $locationAvailableStock = $locations->sum('stok_tersedia');

            // ASVS 2.2.3: Enforce availability across locked storage locations, not the usage destination.
            if ($quantity > $lockedBarang->stok_tersedia || $quantity > $locationAvailableStock) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Jumlah pemakaian melebihi stok tersedia pada lokasi penyimpanan barang.',
                ]);
            }

            $remainingQuantity = $quantity;
            $stockAfterAllocation = $lockedBarang->stok_tersedia;
            $stockAllocations = [];

            foreach ($locations as $location) {
                if ($remainingQuantity === 0) {
                    break;
                }

                $allocatedQuantity = min($remainingQuantity, $location->stok_tersedia);
                $location->stok_tersedia -= $allocatedQuantity;
                $location->save();
                $stockAllocations[] = [
                    'ruangan_id' => $location->ruangan_id,
                    'lemari_id' => $location->lemari_id,
                    'jumlah' => $allocatedQuantity,
                    'stok_sebelum' => $stockAfterAllocation,
                    'stok_sesudah' => $stockAfterAllocation - $allocatedQuantity,
                ];
                $stockAfterAllocation -= $allocatedQuantity;
                $remainingQuantity -= $allocatedQuantity;
            }

            $lockedBarang->stok_tersedia -= $quantity;
            $lockedBarang->save();

            $usage = BarangDipakai::query()->create([
                'barang_id' => $lockedBarang->barang_id,
                'ruangan_id' => $attributes['ruangan_id'],
                'akun_id' => $adminAccount->akun_id,
                'jumlah' => $quantity,
                'keterangan' => $attributes['keterangan'] ?? null,
                'status' => 'aktif',
                'tanggal_pasang' => $attributes['tanggal_pasang'],
            ]);

            foreach ($stockAllocations as $allocation) {
                LogMutasiBarang::query()->create([
                    'barang_id' => $lockedBarang->barang_id,
                    'ruangan_id' => $allocation['ruangan_id'],
                    'lemari_asal_id' => $allocation['lemari_id'],
                    'akun_id' => $adminAccount->akun_id,
                    'oleh_akun_id' => $actor->akun_id,
                    'barang_dipakai_id' => $usage->barang_dipakai_id,
                    'tipe' => 'dipakai',
                    'jumlah' => $allocation['jumlah'],
                    'stok_sebelum' => $allocation['stok_sebelum'],
                    'stok_sesudah' => $allocation['stok_sesudah'],
                    'keterangan' => 'Stok penyimpanan dikurangi untuk pemakaian di '.$usageRoom->nama_ruangan.'.',
                    'created_at' => now(),
                ]);
            }
        });

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pemakaian barang berhasil dicatat.',
                'barang_id' => $barang->barang_id,
            ]);
        }

        return redirect()->route('admin.barang', [
            'barang_id' => $barang->barang_id,
            'panel' => 'usage',
        ])->with('success', "Barang {$barang->nama_barang} berhasil dicatat untuk pemakaian.");
    }

    public function withdrawUsage(Request $request, Barang $barang, BarangDipakai $usage): RedirectResponse|JsonResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        DB::transaction(function () use ($actor, $barang, $usage): void {
            $lockedBarang = Barang::query()
                ->whereKey($barang->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $lockedUsage = $lockedBarang->barangDipakais()
                ->whereKey($usage->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedUsage->status !== 'aktif') {
                throw ValidationException::withMessages([
                    'pemakaian' => 'Pemakaian ini sudah ditarik kembali.',
                ]);
            }

            $usageMutations = $lockedUsage->logMutasiBarangs()
                ->where('tipe', 'dipakai')
                ->orderBy('log_mutasi_barang_id')
                ->lockForUpdate()
                ->get();
            $quantity = $lockedUsage->jumlah;

            if ($usageMutations->isEmpty() || $usageMutations->sum('jumlah') !== $quantity) {
                throw ValidationException::withMessages([
                    'pemakaian' => 'Rincian lokasi penyimpanan untuk pemakaian ini tidak lengkap.',
                ]);
            }

            $locations = $lockedBarang->lokasiBarangs()
                ->orderBy('lokasi_barang_id')
                ->lockForUpdate()
                ->get();
            $globalCapacity = $lockedBarang->stok_total - $lockedBarang->stok_tersedia;
            $locationCapacity = $locations->sum(
                static fn (LokasiBarang $location): int => max(0, $location->stok_total - $location->stok_tersedia),
            );

            if ($quantity > $globalCapacity || $quantity > $locationCapacity) {
                throw ValidationException::withMessages([
                    'pemakaian' => 'Lokasi penyimpanan tidak memiliki kapasitas yang cukup untuk mengembalikan barang.',
                ]);
            }

            $remainingCapacities = $locations->mapWithKeys(
                static fn (LokasiBarang $location): array => [
                    $location->lokasi_barang_id => max(0, $location->stok_total - $location->stok_tersedia),
                ],
            )->all();
            $restoredAllocations = [];
            $stockAfterAllocation = $lockedBarang->stok_tersedia;

            // ASVS 2.2.3: Restore recorded allocations without exceeding any storage location's capacity.
            foreach ($usageMutations as $usageMutation) {
                if ($usageMutation->ruangan_id === null) {
                    throw ValidationException::withMessages([
                        'pemakaian' => 'Lokasi penyimpanan asal untuk pemakaian ini tidak tersedia.',
                    ]);
                }

                $remainingQuantity = $usageMutation->jumlah;
                $candidateLocations = $locations->sortBy(
                    static fn (LokasiBarang $location): int => (
                        $location->ruangan_id === $usageMutation->ruangan_id
                        && ($usageMutation->lemari_asal_id === null
                            || $location->lemari_id === $usageMutation->lemari_asal_id)
                    ) ? 0 : 1,
                )->values();

                foreach ($candidateLocations as $location) {
                    if ($remainingQuantity === 0) {
                        break;
                    }

                    $restoredQuantity = min(
                        $remainingQuantity,
                        $remainingCapacities[$location->lokasi_barang_id],
                    );
                    if ($restoredQuantity === 0) {
                        continue;
                    }

                    $location->stok_tersedia += $restoredQuantity;
                    $location->save();
                    $remainingCapacities[$location->lokasi_barang_id] -= $restoredQuantity;
                    $restoredAllocations[] = [
                        'ruangan_id' => $location->ruangan_id,
                        'lemari_id' => $location->lemari_id,
                        'jumlah' => $restoredQuantity,
                        'stok_sebelum' => $stockAfterAllocation,
                        'stok_sesudah' => $stockAfterAllocation + $restoredQuantity,
                    ];
                    $stockAfterAllocation += $restoredQuantity;
                    $remainingQuantity -= $restoredQuantity;
                }

                if ($remainingQuantity > 0) {
                    throw ValidationException::withMessages([
                        'pemakaian' => 'Lokasi penyimpanan tidak memiliki kapasitas yang cukup untuk mengembalikan barang.',
                    ]);
                }
            }

            $lockedBarang->stok_tersedia = $stockAfterAllocation;
            $lockedBarang->save();

            $lockedUsage->update([
                'status' => 'ditarik',
                'tanggal_tarik' => now(),
            ]);

            foreach ($restoredAllocations as $allocation) {
                LogMutasiBarang::query()->create([
                    'barang_id' => $lockedBarang->barang_id,
                    'ruangan_id' => $allocation['ruangan_id'],
                    'lemari_tujuan_id' => $allocation['lemari_id'],
                    'akun_id' => $lockedUsage->akun_id,
                    'oleh_akun_id' => $actor->akun_id,
                    'barang_dipakai_id' => $lockedUsage->barang_dipakai_id,
                    'tipe' => 'ditarik',
                    'jumlah' => $allocation['jumlah'],
                    'stok_sebelum' => $allocation['stok_sebelum'],
                    'stok_sesudah' => $allocation['stok_sesudah'],
                    'keterangan' => mb_substr(
                        $lockedUsage->keterangan
                          ? 'Pemakaian ditarik kembali: '.$lockedUsage->keterangan
                          : 'Pemakaian ditarik kembali.',
                        0,
                        255,
                    ),
                    'created_at' => now(),
                ]);
            }
        });

        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Barang berhasil ditarik kembali.',
                'barang_id' => $barang->barang_id,
            ]);
        }

        return redirect()->route('admin.barang', [
            'barang_id' => $barang->barang_id,
            'panel' => 'history',
        ])->with('success', "Barang {$barang->nama_barang} berhasil ditarik kembali.");
    }

    public function import(ImportRequest $request): JsonResponse
    {
        $files = $request->file('files', []);
        if (! is_array($files)) {
            $files = [];
        }
        if ($request->file('file') !== null) {
            array_unshift($files, $request->file('file'));
        }

        $imagesZip = $request->file('images_zip');
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $localDisk = Storage::disk('local');
        $logs = [];
        $uploadDirectories = [];
        $jobs = [];

        try {
            foreach ($files as $file) {
                $fileName = Str::of(preg_replace('/[\x00-\x1F\x7F]/', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'barang-import')
                    ->limit(255, '')
                    ->toString();
                $uploadDirectory = 'temp/barang-import/uploads/'.Str::uuid();
                $uploadDirectories[] = $uploadDirectory;
                $filePath = $file->storeAs(
                    $uploadDirectory,
                    'data.'.Str::lower($file->getClientOriginalExtension()),
                    'local',
                );
                $imagesZipPath = $imagesZip?->storeAs($uploadDirectory, 'images.zip', 'local');

                if (! is_string($filePath) || ($imagesZip !== null && ! is_string($imagesZipPath))) {
                    throw new \RuntimeException('File impor gagal disimpan.');
                }

                $log = LogInput::create([
                    'jenis_input' => 'barang',
                    'nama_file' => $fileName,
                    'total_baris' => 0,
                    'baris_berhasil' => 0,
                    'baris_gagal' => 0,
                    'status' => 'proses',
                    'mulai_at' => now(),
                ]);
                $logs[] = $log;
                $jobs[] = new ImportBarangJob(
                    $log->log_input_id,
                    $uploadDirectory,
                    $filePath,
                    $imagesZipPath,
                    $actor->akun_id,
                );
            }

            // ASVS 2.2.2: ImportRequest validates each upload before dispatch.
            // Windows cannot reliably spawn Laravel's separate background PHP process.
            $queueConnection = PHP_OS_FAMILY === 'Windows' ? 'deferred' : 'background';
            Bus::chain($jobs)
                ->onConnection($queueConnection)
                ->dispatch();
        } catch (\Throwable $exception) {
            foreach ($uploadDirectories as $uploadDirectory) {
                $localDisk->deleteDirectory($uploadDirectory);
            }
            report($exception);

            foreach ($logs as $log) {
                $log->update([
                    'status' => 'gagal',
                    'detail_error' => [
                        $log->nama_file,
                        'Import gagal disiapkan untuk diproses. Silakan coba kembali.',
                        [],
                    ],
                    'selesai_at' => now(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'File gagal disiapkan untuk proses import. Silakan coba kembali.',
            ], 503);
        }

        $imports = array_map(
            static fn (LogInput $log): array => [
                'log_id' => $log->log_input_id,
                'file_name' => $log->nama_file,
                'status' => $log->status,
            ],
            $logs,
        );

        return response()->json([
            'success' => true,
            'status' => 'proses',
            'log_id' => count($imports) === 1 ? $imports[0]['log_id'] : null,
            'imports' => $imports,
            'message' => count($imports).' file berhasil diterima dan sedang diproses.',
        ], 202);
    }

    public function processImport(
        string $logInputId,
        string $filePath,
        ?string $imagesZipPath,
        string $operatorId,
    ): void {
        $log = LogInput::query()->findOrFail($logInputId);
        $fileName = $log->nama_file;
        $operator = User::query()->findOrFail($operatorId);
        $path = Storage::disk('local')->path($filePath);

        try {
            if (! is_file($path)) {
                $this->completeImport($log, $fileName, 0, 0, 0, [], 'File tidak dapat dibaca.');

                return;
            }

            $reader = IOFactory::createReaderForFile($path);
            if (! $reader instanceof IReader2 || ! $this->hasBoundedWorksheetInfo($reader->listWorksheetInfo($path))) {
                $this->completeImport(
                    $log,
                    $fileName,
                    0,
                    0,
                    0,
                    [],
                    'File melebihi batas pemrosesan. Maksimal 1.000 baris data dan 32 kolom.',
                );

                return;
            }

            $emptyImport = new class implements ToArray
            {
                public function array(array $array): void {}
            };

            $sheets = Excel::toArray($emptyImport, $path);
        } catch (\Throwable $e) {
            report($e);

            $this->completeImport(
                $log,
                $fileName,
                0,
                0,
                0,
                [],
                'Gagal membaca file Excel. Periksa format dan isi file.',
            );

            return;
        }

        $rows = $sheets[0] ?? [];
        if ($rows === []) {
            $this->completeImport($log, $fileName, 0, 0, 0, [], 'File tidak memiliki data untuk diimpor.');

            return;
        }

        // 1. Deteksi otomatis baris header Excel
        $mapping = null;
        $headerRowIndex = -1;
        for ($index = 0; $index < min(50, count($rows)); $index++) {
            $mapping = $this->detectImportColumns($rows[$index]);
            if ($mapping !== null) {
                $headerRowIndex = $index;
                break;
            }
        }

        if ($mapping === null) {
            $this->completeImport(
                $log,
                $fileName,
                0,
                0,
                0,
                [],
                'Kolom wajib tidak ditemukan. Gunakan susunan kolom yang ditampilkan pada formulir impor.',
            );

            return;
        }

        $dataRows = array_slice($rows, $headerRowIndex + 1);
        $nonEmptyRows = array_filter($dataRows, fn (array $row): bool => ! $this->isEmptyImportRow($row));
        if ($nonEmptyRows === []) {
            $this->completeImport($log, $fileName, 0, 0, 0, [], 'File tidak memiliki baris data untuk diimpor.');

            return;
        }

        if (count($nonEmptyRows) > self::MAX_IMPORT_ROWS) {
            $this->completeImport(
                $log,
                $fileName,
                count($nonEmptyRows),
                0,
                count($nonEmptyRows),
                [],
                'File melebihi batas 1.000 baris data per impor.',
            );

            return;
        }

        // ASVS 2.2.3: Preflight same-name rows to avoid partially importing conflicting item data.
        $rowsByName = [];
        $signaturesByName = [];
        $codesByName = [];
        foreach ($dataRows as $index => $row) {
            if ($this->isEmptyImportRow($row)) {
                continue;
            }

            $name = $this->importCell($row[$mapping['nama_barang']] ?? null);
            if ($name === null) {
                continue;
            }

            $normalizedName = Str::lower(trim($name));
            $rawEligibility = isset($mapping['kelayakan'])
                ? $this->importCell($row[$mapping['kelayakan']] ?? null)
                : null;
            $signature = $this->importGroupFingerprint([
                'kategori' => $this->importCell($row[$mapping['kategori']] ?? null),
                'nama_barang' => $name,
                'satuan' => isset($mapping['satuan'])
                    ? $this->importCell($row[$mapping['satuan']] ?? null)
                    : null,
                'kondisi' => $this->normalizeImportCondition(
                    $this->importCell($row[$mapping['kondisi']] ?? null),
                ),
                'kelayakan' => $rawEligibility === null
                    ? 'layak'
                    : $this->normalizeImportEligibility($rawEligibility),
                'spesifikasi' => isset($mapping['spesifikasi'])
                    ? $this->importCell($row[$mapping['spesifikasi']] ?? null)
                    : null,
            ]);
            $itemCode = isset($mapping['kode_barang'])
                ? $this->importCell($row[$mapping['kode_barang']] ?? null)
                : null;
            if ($itemCode !== null && is_numeric($itemCode)) {
                $itemCode = null;
            }

            $rowsByName[$normalizedName][] = $index;
            $signaturesByName[$normalizedName][$signature] = true;
            if ($itemCode !== null) {
                $codesByName[$normalizedName][Str::upper(trim($itemCode))] = $itemCode;
            }
        }

        $inconsistentNameRows = [];
        $sourceCodesByName = [];
        foreach ($rowsByName as $normalizedName => $rowIndexes) {
            $sourceCodes = array_values($codesByName[$normalizedName] ?? []);
            if (count($signaturesByName[$normalizedName]) > 1 || count($sourceCodes) > 1) {
                foreach ($rowIndexes as $rowIndex) {
                    $inconsistentNameRows[$rowIndex] = true;
                }
            } elseif ($sourceCodes !== []) {
                $sourceCodesByName[$normalizedName] = $sourceCodes[0];
            }
        }

        /** @var EloquentCollection<int, Kategori> $kategoriRecords */
        $kategoriRecords = Kategori::withTrashed()->get(['kategori_id', 'nama_kategori', 'keterangan', 'deleted_at']);
        $kategoriByName = $this->referenceIndex($kategoriRecords, 'nama_kategori');

        /** @var EloquentCollection<int, Ruangan> $ruanganRecords */
        $ruanganRecords = Ruangan::withTrashed()->get([
            'ruangan_id',
            'kode_ruangan',
            'nama_ruangan',
            'keterangan',
            'deleted_at',
        ]);
        $ruanganByName = $this->referenceIndex($ruanganRecords, 'nama_ruangan');
        $ruanganByCode = $this->referenceIndex($ruanganRecords, 'kode_ruangan');

        /** @var EloquentCollection<int, Lemari> $lemariRecords */
        $lemariRecords = Lemari::withTrashed()->get([
            'lemari_id',
            'ruangan_id',
            'kode_lemari',
            'nama_lemari',
            'keterangan',
            'deleted_at',
        ]);
        $lemariByRoomAndName = [];
        $lemariByRoomAndCode = [];
        foreach ($lemariRecords as $lemari) {
            $key = (string) $lemari->getAttribute('ruangan_id').'|'.Str::lower(trim((string) $lemari->getAttribute('nama_lemari')));
            if (! array_key_exists($key, $lemariByRoomAndName)) {
                $lemariByRoomAndName[$key] = $lemari;
            }

            $codeKey = (string) $lemari->getAttribute('ruangan_id').'|'.Str::lower(trim((string) $lemari->getAttribute('kode_lemari')));
            if (! array_key_exists($codeKey, $lemariByRoomAndCode)) {
                $lemariByRoomAndCode[$codeKey] = $lemari;
            }
        }

        $usedCodes = array_fill_keys(
            Barang::withTrashed()
                ->pluck('kode_barang')
                ->map(static fn (string $code): string => Str::upper($code))
                ->all(),
            true,
        );
        $importedItems = [];
        $existingItems = Barang::query()
            ->with([
                'kategori' => fn ($query) => $query->withTrashed(),
                'lokasiBarangs' => fn ($query) => $query->with([
                    'ruangan' => fn ($query) => $query->withTrashed(),
                    'lemari' => fn ($query) => $query->withTrashed(),
                    'toolbox' => fn ($query) => $query->withTrashed(),
                ]),
            ])
            ->get([
                'barang_id',
                'kategori_id',
                'nama_barang',
                'satuan',
                'stok_total',
                'stok_tersedia',
                'kondisi',
                'kelayakan',
                'spesifikasi',
            ]);
        foreach ($existingItems as $existingItem) {
            $existingData = [
                'kategori' => $existingItem->kategori?->nama_kategori ?? '',
                'nama_barang' => $existingItem->nama_barang,
                'satuan' => $existingItem->satuan,
                'stok_total' => $existingItem->stok_total,
                'stok_tersedia' => $existingItem->stok_tersedia,
                'kondisi' => $existingItem->kondisi,
                'kelayakan' => $existingItem->kelayakan,
                'spesifikasi' => $existingItem->spesifikasi,
            ];
            $locations = $existingItem->lokasiBarangs;

            if ($locations->isEmpty()) {
                $importedItems[$this->importItemFingerprint($existingData, null, null)] = true;

                continue;
            }

            foreach ($locations as $location) {
                $locationData = [
                    ...$existingData,
                    'stok_total' => $location->stok_total,
                    'stok_tersedia' => $location->stok_tersedia,
                ];
                $importedItems[$this->importItemFingerprint(
                    $locationData,
                    $location->ruangan?->nama_ruangan,
                    $location->lemari?->nama_lemari,
                    $location->toolbox?->nama_toolbox,
                )] = true;
            }
        }
        $nextCodeNumber = 0;
        foreach (array_keys($usedCodes) as $code) {
            if (preg_match('/^BRG-(\d+)$/i', $code, $matches) === 1) {
                $nextCodeNumber = max($nextCodeNumber, (int) $matches[1]);
            }
        }

        $totalRows = 0;
        $successRows = 0;
        $failedRows = 0;
        $rowErrors = [];
        $importGroups = [];

        $imageReferences = [];
        if (isset($mapping['image'])) {
            foreach ($dataRows as $row) {
                $imageReference = $this->importCell($row[$mapping['image']] ?? null);
                if ($imageReference !== null) {
                    $imageReferences[Barang::normalizeImageReference($imageReference)] = true;
                }
            }
        }

        $temporaryDirectory = null;
        try {
            $extractedImages = $this->extractImportImages(
                $imagesZipPath === null ? null : Storage::disk('local')->path($imagesZipPath),
                $imageReferences,
                $temporaryDirectory,
            );
        } catch (\Throwable $exception) {
            report($exception);

            $this->completeImport(
                $log,
                $fileName,
                0,
                0,
                0,
                [],
                'Gagal mengekstrak file ZIP gambar. Periksa isi arsip.',
            );

            return;
        }

        try {
            foreach ($dataRows as $index => $row) {
                if ($this->isEmptyImportRow($row)) {
                    continue;
                }

                $totalRows++;
                $rowNumber = $headerRowIndex + $index + 2;
                $rowErrorContext = ['row' => $rowNumber, 'data_row' => $totalRows];
                if (isset($inconsistentNameRows[$index])) {
                    $failedRows++;
                    $rowErrors[] = $rowErrorContext + ['code' => 'inconsistent_group'];

                    continue;
                }

                $mappedRow = [];
                foreach ($mapping as $field => $columnIndex) {
                    if (in_array($field, ['ruangan', 'lemari', 'kode_ruangan', 'kode_lemari'], true)) {
                        $mappedRow[$field] = $this->importLocationCell($row[$columnIndex] ?? null);
                    } else {
                        $mappedRow[$field] = $this->importCell($row[$columnIndex] ?? null);
                    }
                }

                $sourceCondition = Str::lower(trim((string) ($mappedRow['kondisi'] ?? '')));
                $condition = $this->normalizeImportCondition($sourceCondition);
                $eligibility = filled($mappedRow['kelayakan'] ?? null)
                    ? $this->normalizeImportEligibility((string) $mappedRow['kelayakan'])
                    : 'layak';

                $roomName = $mappedRow['ruangan'] ?? null;
                $roomCode = $mappedRow['kode_ruangan'] ?? null;
                $roomDescription = $mappedRow['keterangan_ruangan'] ?? null;
                $cabinetName = $mappedRow['lemari'] ?? null;
                $cabinetCode = $mappedRow['kode_lemari'] ?? null;
                $cabinetDescription = $mappedRow['keterangan_lemari'] ?? null;
                $sourceCode = $mappedRow['kode_barang'] ?? null;
                if ($sourceCode !== null && is_numeric($sourceCode)) {
                    $sourceCode = null;
                }
                $sourceGroupNumber = $mappedRow['nomor_barang'] ?? null;
                $imageReference = filled($mappedRow['image'] ?? null)
                    ? Barang::normalizeImageReference($mappedRow['image'])
                    : null;
                $groupName = $mappedRow['nama_barang'] ?? null;
                $normalizedGroupName = filled($groupName) ? Str::lower(trim((string) $groupName)) : null;
                $sourceCode ??= $normalizedGroupName === null ? null : ($sourceCodesByName[$normalizedGroupName] ?? null);
                $groupKey = filled($groupName)
                    ? 'name:'.$normalizedGroupName
                    : 'row:'.$rowNumber;
                $existingGroup = $importGroups[$groupKey] ?? null;
                $code = $existingGroup['kode_barang'] ?? $sourceCode;

                if ($code === null || is_numeric($code)) {
                    do {
                        $code = sprintf('BRG-%03d', ++$nextCodeNumber);
                    } while (isset($usedCodes[Str::upper($code)]) || in_array(
                        Str::upper($code),
                        array_column($importGroups, 'kode_barang'),
                        true,
                    ));
                }

                $data = [
                    'nomor_barang' => $sourceGroupNumber,
                    'kategori' => $mappedRow['kategori'] ?? null,
                    'keterangan_kategori' => $mappedRow['keterangan_kategori'] ?? null,
                    'kode_barang' => $code,
                    'nama_barang' => $mappedRow['nama_barang'] ?? null,
                    'satuan' => $mappedRow['satuan'] ?? null,
                    'stok_total' => $mappedRow['stok_total'] ?? 1,
                    'stok_tersedia' => $mappedRow['stok_tersedia'] ?? $mappedRow['stok_total'] ?? 1,
                    'kondisi' => $condition,
                    'kelayakan' => $eligibility,
                    'spesifikasi' => $mappedRow['spesifikasi'] ?? null,
                    'kode_ruangan' => $roomCode,
                    'ruangan' => $roomName,
                    'keterangan_ruangan' => $roomDescription,
                    'kode_lemari' => $cabinetCode,
                    'lemari' => $cabinetName,
                    'keterangan_lemari' => $cabinetDescription,
                ];

                // ASVS V2.2.1/V2.2.2/V2.2.3: Validate each row and keep grouped location rows consistent.
                $validator = Validator::make($data, [
                    'nomor_barang' => ['nullable', 'string', 'max:30'],
                    'kategori' => ['required', 'string', 'max:100'],
                    'keterangan_kategori' => ['nullable', 'string', 'max:255'],
                    'kode_barang' => ['required', 'string', 'max:30'],
                    'nama_barang' => ['required', 'string', 'max:150'],
                    'satuan' => ['required', 'string', 'max:20'],
                    'stok_total' => ['required', 'integer', 'min:0'],
                    'stok_tersedia' => ['required', 'integer', 'min:0', 'lte:stok_total'],
                    'kondisi' => ['required', Rule::in(['baru', 'lama'])],
                    'kelayakan' => ['required', Rule::in(['layak', 'tidak_layak'])],
                    'spesifikasi' => ['nullable', 'string', 'max:255'],
                    'kode_ruangan' => ['nullable', 'string', 'max:20'],
                    'ruangan' => ['required', 'string', 'max:100'],
                    'keterangan_ruangan' => ['nullable', 'string', 'max:255'],
                    'kode_lemari' => ['nullable', 'string', 'max:20'],
                    'lemari' => [
                        Rule::requiredIf($cabinetCode !== null || $cabinetDescription !== null),
                        'nullable',
                        'string',
                        'max:100',
                    ],
                    'keterangan_lemari' => ['nullable', 'string', 'max:255'],
                ], [
                    'kondisi.required' => 'Kondisi barang wajib diisi atau tidak dikenali.',
                    'kondisi.in' => 'Kondisi barang harus Baru atau Lama.',
                    'ruangan.required' => 'Ruangan wajib diisi pada setiap baris.',
                    'lemari.required' => 'Nama lemari wajib diisi jika kode atau keterangannya dimasukkan.',
                ]);

                if ($validator->fails()) {
                    $failedRows++;
                    $rowErrors[] = $rowErrorContext + [
                        'message' => implode(' | ', $validator->errors()->all()),
                    ];

                    continue;
                }

                $normalizedCode = Str::upper($code);
                if (isset($usedCodes[$normalizedCode]) && $existingGroup === null) {
                    $failedRows++;
                    $rowErrors[] = $rowErrorContext + ['code' => 'duplicate_code'];

                    continue;
                }

                if ($existingGroup !== null && (
                    ($existingGroup['source_code'] === null) !== ($sourceCode === null)
                    || (
                        $existingGroup['source_code'] !== null
                        && Str::upper(trim((string) $existingGroup['source_code'])) !== Str::upper(trim((string) $sourceCode))
                    )
                )) {
                    $failedRows++;
                    $rowErrors[] = $rowErrorContext + ['code' => 'inconsistent_group'];

                    continue;
                }

                $validated = $validator->validated();
                $masterFingerprint = $this->importGroupFingerprint($validated);
                if (
                    $existingGroup !== null
                    && $existingGroup['master_fingerprint'] !== $masterFingerprint
                ) {
                    $failedRows++;
                    $rowErrors[] = $rowErrorContext + ['code' => 'inconsistent_group'];

                    continue;
                }

                $itemFingerprint = $this->importItemFingerprint($validated, $roomName, $cabinetName);
                if (isset($importedItems[$itemFingerprint])) {
                    $failedRows++;
                    $rowErrors[] = $rowErrorContext + ['code' => 'duplicate_item'];

                    continue;
                }

                $locationKey = Str::lower(trim((string) $roomName)).'|'
                    .Str::lower(trim((string) $cabinetName));
                if ($existingGroup !== null && isset($existingGroup['locations'][$locationKey])) {
                    $failedRows++;
                    $rowErrors[] = $rowErrorContext + ['code' => 'duplicate_location'];

                    continue;
                }

                if (
                    $existingGroup !== null
                    && $existingGroup['image_reference'] !== null
                    && $imageReference !== null
                    && $existingGroup['image_reference'] !== $imageReference
                ) {
                    $failedRows++;
                    $rowErrors[] = $rowErrorContext + ['code' => 'inconsistent_group'];

                    continue;
                }

                $imagePathStored = null;
                $groupedBarangId = null;
                try {
                    // 3. Pencocokan nama file gambar dari Excel dengan file di dalam ZIP
                    $imageColumnValue = $mappedRow['image'] ?? null;
                    if ($imageReference !== null && ($existingGroup === null || $existingGroup['image_reference'] === null)) {
                        $cleanImageName = $imageReference;
                        if (isset($extractedImages[$cleanImageName])) {
                            $imgData = $extractedImages[$cleanImageName];
                            $imagePathStored = $this->storeSupabaseImage(
                                new File($imgData['path']),
                            );
                        }
                    }

                    DB::transaction(function () use (
                        $validator,
                        &$kategoriByName,
                        &$ruanganByName,
                        &$ruanganByCode,
                        &$lemariByRoomAndName,
                        &$lemariByRoomAndCode,
                        $operator,
                        $log,
                        $fileName,
                        $roomName,
                        $roomCode,
                        $roomDescription,
                        $cabinetName,
                        $cabinetCode,
                        $cabinetDescription,
                        $imagePathStored,
                        $existingGroup,
                        &$groupedBarangId,
                    ): void {
                        $validated = $validator->validated();
                        $kategori = $this->resolveKategori(
                            $validated['kategori'],
                            $validated['keterangan_kategori'] ?? null,
                            $kategoriByName,
                        );
                        $ruangan = $this->resolveRuangan(
                            $roomName,
                            $roomCode,
                            $roomDescription,
                            $ruanganByName,
                            $ruanganByCode,
                        );
                        $lemari = $ruangan instanceof Ruangan && filled($cabinetName)
                          ? $this->resolveLemari(
                              $ruangan,
                              $cabinetName,
                              $cabinetCode,
                              $cabinetDescription,
                              $lemariByRoomAndName,
                              $lemariByRoomAndCode,
                          )
                          : null;

                        if ($existingGroup !== null) {
                            $barang = Barang::query()
                                ->whereKey($existingGroup['barang_id'])
                                ->lockForUpdate()
                                ->firstOrFail();
                            $groupedBarangId = $barang->barang_id;
                            $stockBefore = $barang->stok_tersedia;
                            if ($imagePathStored !== null && $barang->image_path === null) {
                                $barang->image_path = $imagePathStored;
                                $barang->save();
                            }
                            $barang->increment('stok_total', (int) $validated['stok_total']);
                            $barang->increment('stok_tersedia', (int) $validated['stok_tersedia']);
                        } else {
                            $barang = Barang::create([
                                'kategori_id' => $kategori->kategori_id,
                                'kode_barang' => $validated['kode_barang'],
                                'image_path' => $imagePathStored,
                                'nama_barang' => $validated['nama_barang'],
                                'satuan' => $validated['satuan'],
                                'stok_total' => $validated['stok_total'],
                                'stok_tersedia' => $validated['stok_tersedia'],
                                'kondisi' => $validated['kondisi'] ?? 'baru',
                                'kelayakan' => $validated['kelayakan'] ?? 'layak',
                                'spesifikasi' => $validated['spesifikasi'] ?? null,
                            ]);
                            $groupedBarangId = $barang->barang_id;
                            $stockBefore = 0;
                        }

                        if ($ruangan instanceof Ruangan) {
                            $barang->lokasiBarangs()->create([
                                'ruangan_id' => $ruangan->ruangan_id,
                                'lemari_id' => $lemari?->lemari_id,
                                'stok_total' => $validated['stok_total'],
                                'stok_tersedia' => $validated['stok_tersedia'],
                            ]);
                        }

                        $this->recordInboundMutation(
                            $barang,
                            $operator,
                            $ruangan?->ruangan_id,
                            $lemari?->lemari_id,
                            (int) $validated['stok_total'],
                            $stockBefore,
                            $stockBefore + (int) $validated['stok_tersedia'],
                            $log->log_input_id,
                            "Barang diimpor dari {$fileName}.",
                        );
                    });
                } catch (\Throwable $ex) {
                    if (isset($imagePathStored) && is_string($imagePathStored)) {
                        $this->deleteStoredImage($imagePathStored);
                    }

                    $failedRows++;
                    $rowErrors[] = $rowErrorContext + [
                        'message' => 'Baris gagal diproses. Periksa data dan pastikan kode barang belum digunakan.',
                    ];

                    continue;
                }

                $successRows++;
                if ($existingGroup === null) {
                    $usedCodes[$normalizedCode] = true;
                    $importGroups[$groupKey] = [
                        'barang_id' => $groupedBarangId,
                        'kode_barang' => $code,
                        'source_code' => $sourceCode,
                        'master_fingerprint' => $masterFingerprint,
                        'image_reference' => $imageReference,
                        'locations' => [$locationKey => true],
                    ];
                } else {
                    $importGroups[$groupKey]['locations'][$locationKey] = true;
                    $importGroups[$groupKey]['image_reference'] ??= $imageReference;
                }
                $importedItems[$itemFingerprint] = true;
            }

            $this->completeImport($log, $fileName, $totalRows, $successRows, $failedRows, $rowErrors);
        } finally {
            if ($temporaryDirectory !== null) {
                Storage::disk('local')->deleteDirectory($temporaryDirectory);
            }
        }
    }

    public function importStatus(LogInput $logInput): JsonResponse
    {
        abort_unless($logInput->jenis_input === 'barang', 404);

        $status = $logInput->status;
        $message = match ($status) {
            'proses' => 'Import sedang diproses.',
            'selesai' => "Import selesai. Berhasil: {$logInput->baris_berhasil}, gagal: {$logInput->baris_gagal}.",
            'sebagian' => "Import selesai sebagian. Berhasil: {$logInput->baris_berhasil}, gagal: {$logInput->baris_gagal}.",
            default => 'Import gagal diproses. Periksa format file, kolom wajib, dan arsip ZIP.',
        };
        $rowErrors = is_array($logInput->detail_error[2] ?? null)
            ? $logInput->detail_error[2]
            : [];
        $rowErrorMessages = [
            'inconsistent_group' => 'Data barang dengan nama sama memiliki kategori, satuan, spesifikasi, kondisi, kelayakan, atau kode yang tidak konsisten.',
            'duplicate_code' => 'Kode barang sudah digunakan.',
            'duplicate_item' => 'Barang dengan nama, kategori, satuan, stok, kondisi, kelayakan, spesifikasi, dan lokasi yang sama sudah tercatat di inventaris. Baris ini dilewati agar tidak terjadi duplikasi.',
            'duplicate_location' => 'Lokasi barang yang sama tercantum lebih dari sekali.',
        ];
        $failureSummaries = [];

        foreach (array_slice($rowErrors, 0, 3) as $rowError) {
            if (! is_array($rowError)) {
                continue;
            }

            $excelRow = is_numeric($rowError['row'] ?? null) ? (int) $rowError['row'] : null;
            $dataRow = is_numeric($rowError['data_row'] ?? null) ? (int) $rowError['data_row'] : null;
            $rowLabel = $dataRow !== null && $excelRow !== null
                ? "Data ke-{$dataRow} (baris Excel ke-{$excelRow})"
                : ($excelRow !== null ? "Baris Excel ke-{$excelRow}" : 'Baris tidak diketahui');
            $reason = $rowErrorMessages[$rowError['code'] ?? '']
                ?? (is_string($rowError['message'] ?? null) ? $rowError['message'] : '');

            if (str_contains($reason, 'SQLSTATE[')) {
                $reason = 'Kesalahan database saat memproses baris ini. Periksa data wajib dan format file.';
            }

            if ($reason !== '') {
                $failureSummaries[] = "{$rowLabel}: {$reason}";
            }
        }

        if ($failureSummaries !== []) {
            $message .= ' '.implode(' ', $failureSummaries);
            if (count($rowErrors) > count($failureSummaries)) {
                $message .= ' Dan '.(count($rowErrors) - count($failureSummaries)).' kegagalan lainnya; buka Log Import untuk detail lengkap.';
            }
        } elseif ($status === 'gagal' && is_string($logInput->detail_error[1] ?? null)) {
            $message .= ' '.$logInput->detail_error[1];
        }

        return response()->json([
            'success' => $status !== 'gagal',
            'status' => $status,
            'total_rows' => $logInput->total_baris,
            'success_rows' => $logInput->baris_berhasil,
            'failed_rows' => $logInput->baris_gagal,
            'message' => $message,
        ]);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $imagePathStored = null;

        try {
            $barang = DB::transaction(function () use ($request, $actor, &$imagePathStored): Barang {
                $attributes = $request->validated();
                $locations = $attributes['locations'];
                unset($attributes['locations'], $attributes['image']);
                $attributes['kode_barang'] = $this->nextBarangCode();

                if ($request->hasFile('image')) {
                    $imagePathStored = $this->storeSupabaseImage($request->file('image'));
                    $attributes['image_path'] = $imagePathStored;
                }

                $barang = Barang::create($attributes);
                $stockBefore = 0;

                foreach ($locations as $locationAttributes) {
                    $location = $barang->lokasiBarangs()->create($locationAttributes);
                    $stockAfter = $stockBefore + $location->stok_tersedia;
                    $this->recordInboundMutation(
                        $barang,
                        $actor,
                        $location->ruangan_id,
                        $location->lemari_id,
                        $location->stok_total,
                        $stockBefore,
                        $stockAfter,
                        null,
                        'Barang ditambahkan dari formulir.',
                    );
                    $stockBefore = $stockAfter;
                }

                return $barang;
            });
        } catch (\Throwable $exception) {
            if (is_string($imagePathStored)) {
                $this->deleteStoredImage($imagePathStored);
            }

            throw $exception;
        }

        return redirect()->route('admin.barang')
            ->with('success', "Barang {$barang->nama_barang} berhasil ditambahkan.")
            ->with('barang_feedback_name', $barang->nama_barang)
            ->with('barang_feedback_action', 'ditambahkan');
    }

    private function nextBarangCode(): string
    {
        $usedCodes = array_fill_keys(
            array_map('strtoupper', Barang::withTrashed()->lockForUpdate()->pluck('kode_barang')->all()),
            true,
        );
        $nextCodeNumber = 0;

        foreach (array_keys($usedCodes) as $code) {
            if (preg_match('/^BRG-(\d+)$/', $code, $matches) === 1) {
                $nextCodeNumber = max($nextCodeNumber, (int) $matches[1]);
            }
        }

        do {
            $code = sprintf('BRG-%03d', ++$nextCodeNumber);
        } while (isset($usedCodes[$code]));

        return $code;
    }

    private function storeSupabaseImage(\Symfony\Component\HttpFoundation\File\File $file): string
    {
        $diskConfig = config('filesystems.disks.supabase', []);
        if (($diskConfig['driver'] ?? null) === 's3') {
            foreach (['key', 'secret', 'bucket', 'endpoint', 'url'] as $requiredConfig) {
                if (blank($diskConfig[$requiredConfig] ?? null)) {
                    throw new \RuntimeException(
                        'Konfigurasi Supabase Storage belum lengkap. Isi SUPABASE_PROJECT_REF, SUPABASE_BUCKET, SUPABASE_S3_ACCESS_KEY_ID, dan SUPABASE_S3_SECRET_ACCESS_KEY.',
                    );
                }
            }
        }

        $extension = $file->guessExtension();
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new \RuntimeException('Format gambar barang tidak didukung.');
        }

        // ASVS 5.3.2: Generate object names on the server instead of trusting uploaded filenames.
        $path = Storage::disk('supabase')->putFileAs(
            'barang_img',
            $file,
            Str::uuid().'.'.$extension,
        );

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Gambar barang gagal disimpan ke Supabase Storage.');
        }

        return Barang::SUPABASE_IMAGE_PATH_PREFIX.$path;
    }

    private function deleteStoredImage(string $imageReference): void
    {
        $disk = 'public';
        $path = $imageReference;

        if (str_starts_with($imageReference, Barang::SUPABASE_IMAGE_PATH_PREFIX)) {
            $disk = 'supabase';
            $path = substr($imageReference, strlen(Barang::SUPABASE_IMAGE_PATH_PREFIX));
        }

        if (preg_match('/\Abarang_img\/[A-Za-z0-9._-]+\.(?:jpe?g|png|webp)\z/i', $path) !== 1) {
            throw new \RuntimeException('Referensi gambar barang tidak valid.');
        }

        $storage = Storage::disk($disk);
        if ($storage->exists($path) && ! $storage->delete($path)) {
            throw new \RuntimeException('Gambar barang lama gagal dihapus dari penyimpanan.');
        }
    }

    public function update(UpdateRequest $request, Barang $barang): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $previousImagePath = null;
        $newImagePathStored = null;

        try {
            DB::transaction(function () use ($request, $barang, $actor, &$previousImagePath, &$newImagePathStored): void {
                $attributes = $request->validated();
                $isEdit = $attributes['barang_form'] === 'edit';
                $locations = $attributes['locations'] ?? [];
                $statusField = $attributes['status_field'] ?? null;
                unset($attributes['barang_form'], $attributes['status_field'], $attributes['locations'], $attributes['image']);
                if ($isEdit) {
                    unset($attributes['kondisi'], $attributes['kelayakan']);
                } elseif (is_string($statusField)) {
                    $attributes = [$statusField => $attributes[$statusField]];
                }

                $lockedBarang = Barang::query()
                    ->whereKey($barang->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($request->hasFile('image')) {
                    $previousImagePath = $lockedBarang->image_path;
                    $newImagePathStored = $this->storeSupabaseImage($request->file('image'));
                    $attributes['image_path'] = $newImagePathStored;
                }

                if ($isEdit) {
                    $this->syncStockLocations($lockedBarang, $locations, $actor);
                }

                $lockedBarang->update($attributes);
            });
        } catch (\Throwable $exception) {
            if (is_string($newImagePathStored)) {
                $this->deleteStoredImage($newImagePathStored);
            }

            throw $exception;
        }

        if (is_string($previousImagePath)) {
            $this->deleteStoredImage($previousImagePath);
        }

        return redirect()->route('admin.barang')
            ->with('success', "Barang {$barang->nama_barang} berhasil diperbarui.")
            ->with('barang_feedback_name', $barang->nama_barang)
            ->with('barang_feedback_action', 'diperbarui');
    }

    private function syncStockLocations(Barang $barang, array $allocations, User $operator): void
    {
        $existingLocations = $barang->lokasiBarangs()
            ->with(['ruangan', 'lemari', 'toolbox'])
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (LokasiBarang $location): string => $this->stockLocationKey(
                $location->ruangan_id,
                $location->lemari_id,
                $location->toolbox_id,
            ));
        $desiredLocations = [];

        foreach ($allocations as $attributes) {
            $key = $this->stockLocationKey(
                $attributes['ruangan_id'],
                $attributes['lemari_id'] ?? null,
                $attributes['toolbox_id'] ?? null,
            );
            $desiredLocations[$key] = [
                'ruangan_id' => $attributes['ruangan_id'],
                'lemari_id' => $attributes['lemari_id'] ?: null,
                'toolbox_id' => $attributes['toolbox_id'] ?: null,
                'stok_total' => (int) $attributes['stok_total'],
                'stok_tersedia' => (int) $attributes['stok_tersedia'],
            ];
        }

        $existingTotal = $existingLocations->sum('stok_total');
        $existingAvailable = $existingLocations->sum('stok_tersedia');
        $unallocatedTotal = $barang->stok_total - $existingTotal;
        $unallocatedAvailable = $barang->stok_tersedia - $existingAvailable;

        $hasActiveReservations = $barang->barangDipakais()
            ->where('status', 'aktif')
            ->exists()
            || $barang->detailPeminjamans()
                ->whereIn('status', ['pending', 'dipinjam'])
                ->exists();

        if ($hasActiveReservations) {
            if ($unallocatedTotal < 0) {
                throw ValidationException::withMessages([
                    'stock_location_total' => 'Jumlah total stok per lokasi tidak boleh melebihi jumlah total stok barang.',
                ]);
            }

            if ($unallocatedAvailable < 0) {
                throw ValidationException::withMessages([
                    'stock_location_available' => 'Jumlah stok tersedia per lokasi tidak boleh melebihi jumlah stok tersedia barang.',
                ]);
            }

            if ($unallocatedAvailable > $unallocatedTotal) {
                throw ValidationException::withMessages([
                    'stock_location_available' => 'Jumlah stok tersedia per lokasi tidak boleh kurang dari jumlah stok tersedia barang.',
                ]);
            }
        }

        // Only active usage and loan records should prevent stock from changing locations.
        $unallocatedReserved = $hasActiveReservations
            ? $unallocatedTotal - $unallocatedAvailable
            : 0;
        $newlyAllocatedReserved = 0;

        foreach ($existingLocations as $key => $location) {
            $reservedQuantity = $hasActiveReservations
                ? $location->stok_total - $location->stok_tersedia
                : 0;
            $desired = $desiredLocations[$key] ?? null;

            if ($desired === null && $reservedQuantity > 0) {
                throw ValidationException::withMessages([
                    'locations' => 'Lokasi tidak dapat dihapus karena masih memiliki barang yang sedang dipinjam atau dipakai.',
                ]);
            }

            $desiredReservedQuantity = $desired === null || ! $hasActiveReservations
                ? 0
                : $desired['stok_total'] - $desired['stok_tersedia'];

            if ($desired !== null && $desiredReservedQuantity < $reservedQuantity) {
                throw ValidationException::withMessages([
                    'locations' => 'Stok yang sedang dipinjam atau dipakai harus tetap berada di lokasi asal.',
                ]);
            }

            $newlyAllocatedReserved += max(0, $desiredReservedQuantity - $reservedQuantity);
        }

        foreach ($desiredLocations as $key => $desired) {
            if (! $existingLocations->has($key) && $hasActiveReservations) {
                $newlyAllocatedReserved += $desired['stok_total'] - $desired['stok_tersedia'];
            }
        }

        if ($newlyAllocatedReserved !== $unallocatedReserved) {
            throw ValidationException::withMessages([
                'locations' => 'Stok yang sedang dipinjam atau dipakai harus tetap berada di lokasi asal, dan stok sisa harus sesuai dengan jumlah barang.',
            ]);
        }

        $roomIds = collect($desiredLocations)
            ->pluck('ruangan_id')
            ->merge($existingLocations->pluck('ruangan_id'))
            ->unique()
            ->values();
        $cabinetIds = collect($desiredLocations)
            ->pluck('lemari_id')
            ->merge($existingLocations->pluck('lemari_id'))
            ->filter()
            ->unique()
            ->values();
        $toolboxIds = collect($desiredLocations)
            ->pluck('toolbox_id')
            ->merge($existingLocations->pluck('toolbox_id'))
            ->filter()
            ->unique()
            ->values();
        $roomNames = Ruangan::query()->whereIn('ruangan_id', $roomIds)->pluck('nama_ruangan', 'ruangan_id');
        $cabinetNames = Lemari::query()->whereIn('lemari_id', $cabinetIds)->pluck('nama_lemari', 'lemari_id');
        $toolboxNames = Toolbox::query()->whereIn('toolbox_id', $toolboxIds)->pluck('nama_toolbox', 'toolbox_id');
        $sources = [];
        $targets = [];

        foreach ($existingLocations as $key => $location) {
            $availableAfter = $desiredLocations[$key]['stok_tersedia'] ?? 0;
            $surplus = $location->stok_tersedia - $availableAfter;

            if ($surplus > 0) {
                $sources[] = [
                    'location' => $location,
                    'quantity' => $surplus,
                    'remaining' => $location->stok_tersedia,
                ];
            }
        }

        if ($unallocatedAvailable > 0) {
            $sources[] = [
                'location' => null,
                'quantity' => $unallocatedAvailable,
                'remaining' => $unallocatedAvailable,
            ];
        }

        foreach ($desiredLocations as $key => $desired) {
            $availableBefore = $existingLocations->get($key)?->stok_tersedia ?? 0;
            $increase = $desired['stok_tersedia'] - $availableBefore;

            if ($increase > 0) {
                $targets[] = [
                    'attributes' => $desired,
                    'quantity' => $increase,
                    'initial_quantity' => $increase,
                    'stock_before' => $availableBefore,
                ];
            }
        }

        foreach ($sources as &$source) {
            foreach ($targets as &$target) {
                if ($source['quantity'] === 0 || $target['quantity'] === 0) {
                    continue;
                }

                $quantity = min($source['quantity'], $target['quantity']);
                $sourceLocation = $source['location'];
                $destination = $target['attributes'];
                $sourceRoomId = $sourceLocation?->ruangan_id;
                $sourceCabinetId = $sourceLocation?->lemari_id;
                $sourceLabel = $sourceLocation === null
                  ? 'stok barang yang belum memiliki lokasi'
                  : $this->stockLocationLabel(
                      $sourceRoomId,
                      $sourceCabinetId,
                      $sourceLocation->toolbox_id,
                      $roomNames,
                      $cabinetNames,
                      $toolboxNames,
                  );
                $destinationLabel = $this->stockLocationLabel(
                    $destination['ruangan_id'],
                    $destination['lemari_id'],
                    $destination['toolbox_id'],
                    $roomNames,
                    $cabinetNames,
                    $toolboxNames,
                );

                LogMutasiBarang::query()->create([
                    'barang_id' => $barang->barang_id,
                    'ruangan_id' => $sourceRoomId ?? $destination['ruangan_id'],
                    'lemari_asal_id' => $sourceCabinetId,
                    'lemari_tujuan_id' => $destination['lemari_id'],
                    'akun_id' => null,
                    'oleh_akun_id' => $operator->akun_id,
                    'tipe' => 'dipindahkan',
                    'jumlah' => $quantity,
                    'stok_sebelum' => $source['remaining'],
                    'stok_sesudah' => $source['remaining'] - $quantity,
                    'keterangan' => "Stok dipindahkan dari {$sourceLabel} ke {$destinationLabel}.",
                    'created_at' => now(),
                ]);

                $source['quantity'] -= $quantity;
                $source['remaining'] -= $quantity;
                $target['quantity'] -= $quantity;
            }
            unset($target);
        }
        unset($source);

        foreach ($sources as $source) {
            if ($source['quantity'] === 0) {
                continue;
            }

            $sourceLocation = $source['location'];
            $this->recordStockAdjustment(
                $barang,
                $operator,
                $sourceLocation?->ruangan_id,
                $sourceLocation?->lemari_id,
                null,
                $source['quantity'],
                $source['remaining'],
                $source['remaining'] - $source['quantity'],
                'Stok dikurangi saat mengubah jumlah barang.',
            );
        }

        foreach ($targets as $target) {
            if ($target['quantity'] === 0) {
                continue;
            }

            $destination = $target['attributes'];
            $stockBefore = $target['stock_before']
                + $target['initial_quantity']
                - $target['quantity'];

            $this->recordStockAdjustment(
                $barang,
                $operator,
                $destination['ruangan_id'],
                null,
                $destination['lemari_id'],
                $target['quantity'],
                $stockBefore,
                $stockBefore + $target['quantity'],
                'Stok ditambahkan saat mengubah jumlah barang.',
            );
        }

        foreach ($desiredLocations as $key => $attributes) {
            $location = $existingLocations->get($key);

            if ($location instanceof LokasiBarang) {
                $location->update($attributes);
            } else {
                $barang->lokasiBarangs()->create($attributes);
            }
        }

        foreach ($existingLocations as $key => $location) {
            if (! array_key_exists($key, $desiredLocations)) {
                $location->delete();
            }
        }
    }

    private function recordStockAdjustment(
        Barang $barang,
        User $operator,
        ?string $roomId,
        ?string $sourceCabinetId,
        ?string $destinationCabinetId,
        int $quantity,
        int $stockBefore,
        int $stockAfter,
        string $description,
    ): void {
        LogMutasiBarang::query()->create([
            'barang_id' => $barang->barang_id,
            'ruangan_id' => $roomId,
            'lemari_asal_id' => $sourceCabinetId,
            'lemari_tujuan_id' => $destinationCabinetId,
            'akun_id' => null,
            'oleh_akun_id' => $operator->akun_id,
            'tipe' => 'penyesuaian',
            'jumlah' => $quantity,
            'stok_sebelum' => $stockBefore,
            'stok_sesudah' => $stockAfter,
            'keterangan' => $description,
            'created_at' => now(),
        ]);
    }

    private function stockLocationKey(string $roomId, ?string $cabinetId, ?string $toolboxId): string
    {
        return $roomId.'|'.($cabinetId ?? '').'|'.($toolboxId ?? '');
    }

    private function stockLocationLabel(
        string $roomId,
        ?string $cabinetId,
        ?string $toolboxId,
        Collection $roomNames,
        Collection $cabinetNames,
        Collection $toolboxNames,
    ): string {
        $roomName = $roomNames->get($roomId, 'Ruangan tidak tersedia');
        $cabinetName = $cabinetId === null ? null : $cabinetNames->get($cabinetId, 'Lemari tidak tersedia');
        $toolboxName = $toolboxId === null ? null : $toolboxNames->get($toolboxId, 'Wadah tidak tersedia');
        $locationNames = array_filter([$roomName, $cabinetName, $toolboxName]);

        return implode(' · ', $locationNames);
    }

    private function recordInboundMutation(
        Barang $barang,
        User $operator,
        ?string $roomId,
        ?string $cabinetId,
        int $quantity,
        int $stockBefore,
        int $stockAfter,
        ?string $inputLogId,
        string $description,
    ): void {
        LogMutasiBarang::query()->create([
            'barang_id' => $barang->barang_id,
            'ruangan_id' => $roomId,
            'lemari_tujuan_id' => $cabinetId,
            'akun_id' => null,
            'oleh_akun_id' => $operator->akun_id,
            'log_input_id' => $inputLogId,
            'tipe' => 'masuk',
            'jumlah' => $quantity,
            'stok_sebelum' => $stockBefore,
            'stok_sesudah' => $stockAfter,
            'keterangan' => $description,
            'created_at' => now(),
        ]);
    }

    public function destroy(Request $request, Barang $barang): RedirectResponse|JsonResponse
    {
        $barangName = $barang->nama_barang;

        $errorMessage = DB::transaction(function () use ($barang): ?string {
            $lockedBarang = Barang::query()
                ->whereKey($barang->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedBarang->detailPeminjamans()->whereIn('status', ['pending', 'dipinjam'])->exists()) {
                return "Barang {$lockedBarang->nama_barang} tidak dapat dihapus karena masih memiliki peminjaman yang menunggu atau belum dikembalikan.";
            }

            if ($lockedBarang->barangDipakais()->where('status', 'aktif')->exists()) {
                return "Barang {$lockedBarang->nama_barang} tidak dapat dihapus karena masih digunakan di ruangan.";
            }

            $lockedBarang->delete();

            return null;
        }, 3);

        if ($errorMessage !== null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $errorMessage], 409);
            }

            return redirect()->route('admin.barang')
                ->with('error', $errorMessage);
        }

        if ($request->expectsJson()) {
            $request->session()->flash('success', "Barang {$barangName} berhasil dihapus.");
            $request->session()->flash('barang_feedback_name', $barangName);
            $request->session()->flash('barang_feedback_action', 'dihapus');

            return response()->json(['message' => "Barang {$barangName} berhasil dihapus."]);
        }

        return redirect()->route('admin.barang')
            ->with('success', "Barang {$barangName} berhasil dihapus.")
            ->with('barang_feedback_name', $barangName)
            ->with('barang_feedback_action', 'dihapus');
    }

    private function completeImport(
        LogInput $log,
        string $fileName,
        int $totalRows,
        int $successRows,
        int $failedRows,
        array $rowErrors,
        ?string $fileError = null,
    ): void {
        $status = $fileError !== null || ($failedRows > 0 && $successRows === 0)
          ? 'gagal'
          : ($failedRows > 0 ? 'sebagian' : 'selesai');

        $log->update([
            'total_baris' => $totalRows,
            'baris_berhasil' => $successRows,
            'baris_gagal' => $failedRows,
            'status' => $status,
            'detail_error' => [$fileName, $fileError, $rowErrors],
            'selesai_at' => now(),
        ]);
    }

    private function importItemFingerprint(
        array $data,
        ?string $roomName,
        ?string $cabinetName,
        ?string $toolboxName = null,
    ): string {
        // ASVS V2.2.1: Compare all inventory and location fields because item names may legitimately repeat.
        $normalized = [
            Str::lower(trim((string) ($data['kategori'] ?? ''))),
            Str::lower(trim((string) ($data['nama_barang'] ?? ''))),
            Str::lower(trim((string) ($data['satuan'] ?? ''))),
            (int) ($data['stok_total'] ?? 0),
            (int) ($data['stok_tersedia'] ?? 0),
            Str::lower(trim((string) ($data['kondisi'] ?? ''))),
            Str::lower(trim((string) ($data['kelayakan'] ?? ''))),
            Str::lower(trim((string) ($data['spesifikasi'] ?? ''))),
            $roomName === null ? null : Str::lower(trim($roomName)),
            $cabinetName === null ? null : Str::lower(trim($cabinetName)),
            $toolboxName === null ? null : Str::lower(trim($toolboxName)),
        ];

        return hash('sha256', serialize($normalized));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function importGroupFingerprint(array $data): string
    {
        $normalized = [
            Str::lower(trim((string) ($data['kategori'] ?? ''))),
            Str::lower(trim((string) ($data['nama_barang'] ?? ''))),
            Str::lower(trim((string) ($data['satuan'] ?? ''))),
            Str::lower(trim((string) ($data['kondisi'] ?? ''))),
            Str::lower(trim((string) ($data['kelayakan'] ?? ''))),
            Str::lower(trim((string) ($data['spesifikasi'] ?? ''))),
        ];

        return hash('sha256', serialize($normalized));
    }

    private function extractImportImages(?string $zipPath, array $imageReferences, ?string &$temporaryDirectory): array
    {
        if ($zipPath === null || $imageReferences === []) {
            return [];
        }

        $zip = new ZipArchive;
        if (! is_file($zipPath) || $zip->open($zipPath) !== true) {
            throw new \RuntimeException('Arsip ZIP tidak dapat dibuka.');
        }

        if ($zip->numFiles > self::MAX_IMPORT_ZIP_ENTRIES) {
            $zip->close();
            throw new \RuntimeException('Arsip ZIP berisi terlalu banyak file.');
        }

        $localDisk = Storage::disk('local');
        $temporaryDirectory = 'temp/barang-import/'.Str::uuid();

        try {
            if (! $localDisk->makeDirectory($temporaryDirectory)) {
                throw new \RuntimeException('Direktori sementara tidak dapat dibuat.');
            }

            $temporaryPath = $localDisk->path($temporaryDirectory);
            $extractedImages = [];
            $totalImageBytes = 0;

            // ASVS 2.2.1: Bound archive entries and expanded image bytes before extraction.
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $entryName = $zip->getNameIndex($index);
                if (! is_string($entryName)) {
                    continue;
                }

                $normalizedEntryName = str_replace('\\', '/', $entryName);
                if (
                    str_starts_with($normalizedEntryName, '/')
                    || preg_match('/^[A-Za-z]:/', $normalizedEntryName) === 1
                    || str_ends_with($normalizedEntryName, '/')
                    || preg_match('/[\x00-\x1F\x7F:?"<>|*]/', $normalizedEntryName) === 1
                ) {
                    continue;
                }

                $pathSegments = explode('/', $normalizedEntryName);
                if (in_array('', $pathSegments, true) || in_array('.', $pathSegments, true) || in_array('..', $pathSegments, true)) {
                    continue;
                }

                $baseName = end($pathSegments);
                $extension = strtolower(pathinfo($baseName, PATHINFO_EXTENSION));
                if (! in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                $normalizedImageName = Barang::normalizeImageReference($normalizedEntryName);
                $normalizedBaseName = Barang::normalizeImageReference($baseName);
                if (! isset($imageReferences[$normalizedImageName]) && ! isset($imageReferences[$normalizedBaseName])) {
                    continue;
                }

                if (
                    isset($extractedImages[$normalizedImageName])
                    || (! isset($imageReferences[$normalizedImageName]) && isset($extractedImages[$normalizedBaseName]))
                ) {
                    continue;
                }

                $entry = $zip->statIndex($index);
                $entrySize = is_array($entry) ? ($entry['size'] ?? null) : null;
                if (! is_int($entrySize) || $entrySize > self::MAX_IMPORT_IMAGE_BYTES) {
                    throw new \RuntimeException('Ukuran gambar di arsip ZIP melewati batas.');
                }

                $totalImageBytes += $entrySize;
                if ($totalImageBytes > self::MAX_IMPORT_TOTAL_IMAGE_BYTES) {
                    throw new \RuntimeException('Total ukuran gambar di arsip ZIP melewati batas.');
                }

                $source = $zip->getStream($entryName);
                if ($source === false) {
                    throw new \RuntimeException("File gambar ZIP tidak dapat diekstrak: {$entryName}");
                }

                $relativeImagePath = $temporaryDirectory.'/'.$index.'.'.$extension;
                $imagePath = $temporaryPath.DIRECTORY_SEPARATOR.$index.'.'.$extension;
                try {
                    $stored = $localDisk->put($relativeImagePath, $source);
                } finally {
                    fclose($source);
                }

                if (! $stored) {
                    throw new \RuntimeException("File gambar ZIP tidak dapat disimpan sementara: {$entryName}");
                }

                // ASVS 2.2.1: Verify extracted file content and image dimensions before public storage.
                $imageInfo = @getimagesize($imagePath);
                $expectedMime = match ($extension) {
                    'jpg', 'jpeg' => 'image/jpeg',
                    'png' => 'image/png',
                    'webp' => 'image/webp',
                };
                if (
                    ! is_array($imageInfo)
                    || ($imageInfo['mime'] ?? null) !== $expectedMime
                    || (($imageInfo[0] ?? 0) * ($imageInfo[1] ?? 0)) > self::MAX_IMPORT_IMAGE_PIXELS
                ) {
                    $localDisk->delete($relativeImagePath);
                    throw new \RuntimeException('File ZIP berisi gambar yang tidak valid atau terlalu besar.');
                }

                $image = ['path' => $imagePath, 'ext' => $extension];
                $extractedImages[$normalizedImageName] = $image;
                $extractedImages[$normalizedBaseName] ??= $image;
            }

            return $extractedImages;
        } catch (\Throwable $exception) {
            $localDisk->deleteDirectory($temporaryDirectory);
            $temporaryDirectory = null;

            throw $exception;
        } finally {
            $zip->close();
        }
    }

    private function detectImportColumns(array $headers): ?array
    {
        $mapping = [];
        foreach ($headers as $index => $header) {
            $normalized = trim(preg_replace('/[^a-z0-9]+/', '_', Str::ascii(Str::lower((string) $header))) ?? '', '_');
            if (in_array($normalized, ['no', 'nomor'], true)) {
                $mapping['kode_barang'] = $index;
            }

            foreach (self::IMPORT_COLUMNS as $field => $aliases) {
                if (! in_array($normalized, $aliases, true)) {
                    continue;
                }

                if (array_key_exists($field, $mapping)) {
                    return null;
                }

                $mapping[$field] = $index;
            }
        }
        $requiredFields = array_diff(
            array_keys(self::IMPORT_COLUMNS),
            [
                'nomor_barang',
                'kode_barang',
                'kode_ruangan',
                'kode_lemari',
                'lemari',
                'stok_tersedia',
                'kelayakan',
                'spesifikasi',
                'image',
                'keterangan_kategori',
                'keterangan_ruangan',
                'keterangan_lemari',
            ],
        );

        return array_diff($requiredFields, array_keys($mapping)) === [] ? $mapping : null;
    }

    private function hasBoundedWorksheetInfo(array $worksheets): bool
    {
        if ($worksheets === [] || count($worksheets) > self::MAX_IMPORT_SHEETS) {
            return false;
        }

        foreach ($worksheets as $worksheet) {
            if (
                ($worksheet['totalRows'] ?? PHP_INT_MAX) > self::MAX_IMPORT_ROWS + 50
                || ($worksheet['totalColumns'] ?? PHP_INT_MAX) > self::MAX_IMPORT_COLUMNS
            ) {
                return false;
            }
        }

        return true;
    }

    private function isEmptyImportRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($this->importCell($value) !== null) {
                return false;
            }
        }

        return true;
    }

    private function referenceIndex(iterable $records, string $nameField): array
    {
        $index = [];

        foreach ($records as $record) {
            $key = Str::lower(trim((string) $record->getAttribute($nameField)));
            if (! array_key_exists($key, $index)) {
                $index[$key] = $record;
            } elseif ($index[$key] instanceof Model) {
                if ($index[$key]->getAttribute('deleted_at') !== null && $record->getAttribute('deleted_at') === null) {
                    $index[$key] = $record;
                } elseif (($index[$key]->getAttribute('deleted_at') !== null) === ($record->getAttribute('deleted_at') !== null)) {
                    $index[$key] = null;
                }
            }
        }

        return $index;
    }

    private function resolveKategori(string $name, ?string $description, array &$index): Kategori
    {
        $key = Str::lower(trim($name));
        $kategori = $index[$key] ?? Kategori::withTrashed()
            ->whereRaw('LOWER(TRIM(nama_kategori)) = ?', [$key])
            ->first();

        if (! $kategori instanceof Kategori) {
            $kategori = Kategori::create([
                'nama_kategori' => trim($name),
                'keterangan' => $description,
            ]);
        } else {
            if ($kategori->trashed()) {
                $kategori->restore();
            }

            if ($description !== null && $kategori->keterangan !== $description) {
                $kategori->keterangan = $description;
                $kategori->save();
            }
        }

        $index[$key] = $kategori;

        return $kategori;
    }

    private function resolveRuangan(
        string $name,
        ?string $code,
        ?string $description,
        array &$nameIndex,
        array &$codeIndex,
    ): Ruangan {
        $key = Str::lower(trim($name));
        $normalizedCode = $code === null ? null : Str::lower(trim($code));
        $roomByName = $nameIndex[$key] ?? Ruangan::withTrashed()
            ->whereRaw('LOWER(TRIM(nama_ruangan)) = ?', [$key])
            ->first();
        $roomByCode = $normalizedCode === null
            ? null
            : ($codeIndex[$normalizedCode] ?? Ruangan::withTrashed()
                ->whereRaw('LOWER(TRIM(kode_ruangan)) = ?', [$normalizedCode])
                ->first());

        if (
            $roomByName instanceof Ruangan
            && $roomByCode instanceof Ruangan
            && $roomByName->getKey() !== $roomByCode->getKey()
        ) {
            throw ValidationException::withMessages([
                'kode_ruangan' => 'Kode ruangan tidak sesuai dengan nama ruangan.',
            ]);
        }

        $ruangan = $roomByName ?? $roomByCode;
        if (! $ruangan instanceof Ruangan) {
            $ruangan = Ruangan::create([
                'kode_ruangan' => trim((string) ($code ?? $this->nextLocationCode(
                    'RGN',
                    Ruangan::withTrashed()->lockForUpdate()->pluck('kode_ruangan')->all(),
                ))),
                'nama_ruangan' => trim($name),
                'keterangan' => $description,
            ]);
        } else {
            if (Str::lower(trim($ruangan->nama_ruangan)) !== $key) {
                throw ValidationException::withMessages([
                    'kode_ruangan' => 'Kode ruangan tidak sesuai dengan nama ruangan.',
                ]);
            }

            if ($normalizedCode !== null && Str::lower(trim($ruangan->kode_ruangan)) !== $normalizedCode) {
                throw ValidationException::withMessages([
                    'kode_ruangan' => 'Kode ruangan tidak sesuai dengan nama ruangan.',
                ]);
            }

            if ($ruangan->trashed()) {
                $ruangan->restore();
            }

            if ($description !== null && $ruangan->keterangan !== $description) {
                $ruangan->keterangan = $description;
                $ruangan->save();
            }
        }

        $nameIndex[$key] = $ruangan;
        $codeIndex[Str::lower(trim($ruangan->kode_ruangan))] = $ruangan;

        return $ruangan;
    }

    private function resolveLemari(
        Ruangan $ruangan,
        string $name,
        ?string $code,
        ?string $description,
        array &$nameIndex,
        array &$codeIndex,
    ): Lemari {
        $key = $ruangan->ruangan_id.'|'.Str::lower(trim($name));
        $normalizedCode = $code === null ? null : Str::lower(trim($code));
        $cabinetByName = $nameIndex[$key] ?? Lemari::withTrashed()
            ->where('ruangan_id', $ruangan->ruangan_id)
            ->whereRaw('LOWER(TRIM(nama_lemari)) = ?', [Str::lower(trim($name))])
            ->first();
        $codeKey = $normalizedCode === null ? null : $ruangan->ruangan_id.'|'.$normalizedCode;
        $cabinetByCode = $codeKey === null
            ? null
            : ($codeIndex[$codeKey] ?? Lemari::withTrashed()
                ->where('ruangan_id', $ruangan->ruangan_id)
                ->whereRaw('LOWER(TRIM(kode_lemari)) = ?', [$normalizedCode])
                ->first());

        if (
            $cabinetByName instanceof Lemari
            && $cabinetByCode instanceof Lemari
            && $cabinetByName->getKey() !== $cabinetByCode->getKey()
        ) {
            throw ValidationException::withMessages([
                'kode_lemari' => 'Kode lemari tidak sesuai dengan nama lemari pada ruangan ini.',
            ]);
        }

        $lemari = $cabinetByName ?? $cabinetByCode;
        if (! $lemari instanceof Lemari) {
            $lemari = Lemari::create([
                'ruangan_id' => $ruangan->ruangan_id,
                'kode_lemari' => trim((string) ($code ?? $this->nextLocationCode(
                    'LMR',
                    Lemari::withTrashed()
                        ->lockForUpdate()
                        ->pluck('kode_lemari')
                        ->all(),
                ))),
                'nama_lemari' => trim($name),
                'keterangan' => $description,
            ]);
        } else {
            if (Str::lower(trim($lemari->nama_lemari)) !== Str::lower(trim($name))) {
                throw ValidationException::withMessages([
                    'kode_lemari' => 'Kode lemari tidak sesuai dengan nama lemari pada ruangan ini.',
                ]);
            }

            if ($normalizedCode !== null && Str::lower(trim($lemari->kode_lemari)) !== $normalizedCode) {
                throw ValidationException::withMessages([
                    'kode_lemari' => 'Kode lemari tidak sesuai dengan nama lemari pada ruangan ini.',
                ]);
            }

            if ($lemari->trashed()) {
                $lemari->restore();
            }

            if ($description !== null && $lemari->keterangan !== $description) {
                $lemari->keterangan = $description;
                $lemari->save();
            }
        }

        $nameIndex[$key] = $lemari;
        $codeIndex[$ruangan->ruangan_id.'|'.Str::lower(trim($lemari->kode_lemari))] = $lemari;

        return $lemari;
    }

    private function normalizeImportCondition(?string $value): ?string
    {
        $normalized = Str::lower(trim((string) $value));
        $hasNewCondition = preg_match('/\bbaru\b/', $normalized) === 1;
        $hasOldCondition = preg_match('/\blama\b/', $normalized) === 1;

        if ($hasNewCondition === $hasOldCondition) {
            return null;
        }

        return $hasNewCondition ? 'baru' : 'lama';
    }

    private function normalizeImportEligibility(string $value): ?string
    {
        $normalized = trim(
            preg_replace('/[^a-z0-9]+/', '_', Str::ascii(Str::lower(trim($value))) ?? '') ?? '',
            '_',
        );

        return match ($normalized) {
            'baik', 'layak', 'layak_pakai' => 'layak',
            'tidak_layak', 'tidak_layak_pakai', 'rusak', 'tidak_baik' => 'tidak_layak',
            default => null,
        };
    }

    private function importCell(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function importLocationCell(mixed $value): ?string
    {
        $value = $this->importCell($value);

        return in_array($value, ['-', '–', '—'], true) ? null : $value;
    }

    private function nextLocationCode(string $prefix, array $usedCodes): string
    {
        $usedCodes = array_fill_keys(array_map('strtoupper', $usedCodes), true);
        $nextNumber = 0;

        foreach (array_keys($usedCodes) as $code) {
            if (preg_match('/^'.preg_quote($prefix, '/').'\-(\d+)$/i', $code, $matches) === 1) {
                $nextNumber = max($nextNumber, (int) $matches[1]);
            }
        }

        do {
            $code = sprintf('%s-%03d', $prefix, ++$nextNumber);
        } while (isset($usedCodes[$code]));

        return $code;
    }
}
