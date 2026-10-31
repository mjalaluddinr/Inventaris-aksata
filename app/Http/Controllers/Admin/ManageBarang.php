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
        'kode_ruangan' => ['kode_ruangan'],
        'ruangan' => ['ruangan', 'nama_ruangan'],
        'kode_lemari' => ['kode_lemari'],
        'lemari' => ['lemari', 'nama_lemari'],
        'kode_barang' => ['kode_barang', 'kode'],
        'nama_barang' => ['nama_barang', 'nama'],
        'satuan' => ['satuan'],
        'stok_total' => ['stok_total', 'jumlah_total', 'jumlah'],
        'stok_tersedia' => ['stok_tersedia', 'jumlah_tersedia'],
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
        ]);

        $barangs = Barang::query()
            ->with([
                'kategori',
                'lokasiBarangs' => fn ($query) => $query->orderBy('created_at')->orderBy('lokasi_barang_id'),
                'lokasiBarangs.ruangan',
                'lokasiBarangs.lemari',
                'lokasiBarangs.toolbox',
            ])
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
            ->when($filters['kelayakan'] ?? null, fn ($query, string $value) => $query->where('kelayakan', $value))
            ->latest()
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

        if ($request->user()?->canManageAllAccounts()) {
            $selectedBarang = isset($filters['barang_id'])
              ? Barang::query()->findOrFail($filters['barang_id'])
              : $barangs->first();

            if ($selectedBarang instanceof Barang) {
                $adminAccounts = User::query()
                    ->where('role', 'admin')
                    ->where('is_active', true)
                    ->orderBy('first_name')
                    ->orderBy('last_name')
                    ->get(['akun_id', 'first_name', 'last_name', 'username']);
                $mutationLogs = $selectedBarang->logMutasiBarangs()
                    ->with(['ruangan', 'lemariAsal', 'lemariTujuan', 'akun', 'olehAkun'])
                    ->orderByDesc('created_at')
                    ->orderByDesc('log_mutasi_barang_id')
                    ->limit(30)
                    ->get();
                $activeUsages = $selectedBarang->barangDipakais()
                    ->with(['ruangan', 'akun'])
                    ->where('status', 'aktif')
                    ->orderByDesc('tanggal_pasang')
                    ->get();
                $usageLocations = $selectedBarang->lokasiBarangs()
                    ->with('ruangan')
                    ->orderBy('ruangan_id')
                    ->orderBy('lokasi_barang_id')
                    ->get()
                    ->filter(static fn (LokasiBarang $location): bool => $location->ruangan !== null)
                    ->groupBy('ruangan_id')
                    ->map(static fn ($locations): array => [
                        'ruangan' => $locations->first()->ruangan,
                        'stok_tersedia' => $locations->sum('stok_tersedia'),
                    ])
                    ->filter(static fn (array $location): bool => $location['stok_tersedia'] > 0)
                    ->values();
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

            $locations = $lockedBarang->lokasiBarangs()
                ->where('ruangan_id', $attributes['ruangan_id'])
                ->orderBy('lokasi_barang_id')
                ->lockForUpdate()
                ->get();
            $quantity = (int) $attributes['jumlah'];
            $roomAvailableStock = $locations->sum('stok_tersedia');

            if ($quantity > $lockedBarang->stok_tersedia || $quantity > $roomAvailableStock) {
                throw ValidationException::withMessages([
                    'jumlah' => 'Jumlah pemakaian melebihi stok tersedia pada barang atau lokasi tersebut.',
                ]);
            }

            $remainingQuantity = $quantity;

            foreach ($locations as $location) {
                if ($remainingQuantity === 0) {
                    break;
                }

                $allocatedQuantity = min($remainingQuantity, $location->stok_tersedia);
                $location->stok_tersedia -= $allocatedQuantity;
                $location->save();
                $remainingQuantity -= $allocatedQuantity;
            }

            $stockBefore = $lockedBarang->stok_tersedia;
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

            LogMutasiBarang::query()->create([
                'barang_id' => $lockedBarang->barang_id,
                'ruangan_id' => $attributes['ruangan_id'],
                'akun_id' => $adminAccount->akun_id,
                'oleh_akun_id' => $actor->akun_id,
                'barang_dipakai_id' => $usage->barang_dipakai_id,
                'tipe' => 'dipakai',
                'jumlah' => $quantity,
                'stok_sebelum' => $stockBefore,
                'stok_sesudah' => $lockedBarang->stok_tersedia,
                'keterangan' => $attributes['keterangan'] ?? 'Pemakaian barang dicatat.',
                'created_at' => now(),
            ]);
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
        ])->with('success', 'Pemakaian barang berhasil dicatat.');
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

            $locations = $lockedBarang->lokasiBarangs()
                ->where('ruangan_id', $lockedUsage->ruangan_id)
                ->orderBy('lokasi_barang_id')
                ->lockForUpdate()
                ->get();
            $quantity = $lockedUsage->jumlah;
            $globalCapacity = $lockedBarang->stok_total - $lockedBarang->stok_tersedia;
            $roomCapacity = $locations->sum(
                static fn (LokasiBarang $location): int => max(0, $location->stok_total - $location->stok_tersedia),
            );

            if ($quantity > $globalCapacity || $quantity > $roomCapacity) {
                throw ValidationException::withMessages([
                    'pemakaian' => 'Stok lokasi tidak memiliki kapasitas yang cukup untuk menarik kembali pemakaian ini.',
                ]);
            }

            $remainingQuantity = $quantity;

            foreach ($locations as $location) {
                if ($remainingQuantity === 0) {
                    break;
                }

                $locationCapacity = max(0, $location->stok_total - $location->stok_tersedia);
                $restoredQuantity = min($remainingQuantity, $locationCapacity);
                $location->stok_tersedia += $restoredQuantity;
                $location->save();
                $remainingQuantity -= $restoredQuantity;
            }

            $stockBefore = $lockedBarang->stok_tersedia;
            $lockedBarang->stok_tersedia += $quantity;
            $lockedBarang->save();

            $lockedUsage->update([
                'status' => 'ditarik',
                'tanggal_tarik' => now(),
            ]);

            LogMutasiBarang::query()->create([
                'barang_id' => $lockedBarang->barang_id,
                'ruangan_id' => $lockedUsage->ruangan_id,
                'akun_id' => $lockedUsage->akun_id,
                'oleh_akun_id' => $actor->akun_id,
                'barang_dipakai_id' => $lockedUsage->barang_dipakai_id,
                'tipe' => 'ditarik',
                'jumlah' => $quantity,
                'stok_sebelum' => $stockBefore,
                'stok_sesudah' => $lockedBarang->stok_tersedia,
                'keterangan' => mb_substr(
                    $lockedUsage->keterangan
                      ? 'Pemakaian ditarik kembali: '.$lockedUsage->keterangan
                      : 'Pemakaian ditarik kembali.',
                    0,
                    255,
                ),
                'created_at' => now(),
            ]);
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
        ])->with('success', 'Barang berhasil ditarik kembali.');
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

            Bus::chain($jobs)->dispatch();
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
                        'Import gagal dimasukkan ke antrean. Silakan coba kembali.',
                        [],
                    ],
                    'selesai_at' => now(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'File gagal dimasukkan ke antrean import. Silakan coba kembali.',
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
            'message' => count($imports).' file berhasil diterima. Antrean akan memprosesnya satu per satu.',
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

        $legacyCategoryBeforeSpecification = isset($mapping['spesifikasi'])
          && $mapping['kategori'] < $mapping['spesifikasi'];
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

        /** @var EloquentCollection<int, Kategori> $kategoriRecords */
        $kategoriRecords = Kategori::withTrashed()->get(['kategori_id', 'nama_kategori', 'deleted_at']);
        $kategoriByName = $this->referenceIndex($kategoriRecords, 'nama_kategori');

        /** @var EloquentCollection<int, Ruangan> $ruanganRecords */
        $ruanganRecords = Ruangan::withTrashed()->get(['ruangan_id', 'nama_ruangan', 'deleted_at']);
        $ruanganByName = $this->referenceIndex($ruanganRecords, 'nama_ruangan');

        /** @var EloquentCollection<int, Lemari> $lemariRecords */
        $lemariRecords = Lemari::withTrashed()->get(['lemari_id', 'ruangan_id', 'nama_lemari', 'deleted_at']);
        $lemariByRoomAndName = [];
        foreach ($lemariRecords as $lemari) {
            $key = (string) $lemari->getAttribute('ruangan_id').'|'.Str::lower(trim((string) $lemari->getAttribute('nama_lemari')));
            if (! array_key_exists($key, $lemariByRoomAndName)) {
                $lemariByRoomAndName[$key] = $lemari;
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
        $previousValues = [];

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
                    $previousValues = [];

                    continue;
                }

                $totalRows++;
                $rowNumber = $headerRowIndex + $index + 2;
                $mappedRow = [];
                $emptyLocationFields = [];
                foreach ($mapping as $field => $columnIndex) {
                    if (in_array($field, ['ruangan', 'lemari', 'kode_ruangan', 'kode_lemari'], true)) {
                        $rawValue = $this->importCell($row[$columnIndex] ?? null);
                        $mappedRow[$field] = $this->importLocationCell($rawValue);
                        if ($rawValue !== null && $mappedRow[$field] === null) {
                            $emptyLocationFields[$field] = true;
                        }
                    } else {
                        $mappedRow[$field] = $this->importCell($row[$columnIndex] ?? null);
                    }
                }

                foreach (['nama_barang', 'kategori', 'ruangan', 'lemari'] as $field) {
                    if (filled($mappedRow[$field] ?? null)) {
                        $previousValues[$field] = $mappedRow[$field];
                    } elseif (array_key_exists($field, $previousValues)) {
                        $mappedRow[$field] = $previousValues[$field];
                    }
                }

                $sourceCondition = Str::lower(trim((string) ($mappedRow['kondisi'] ?? '')));
                $eligibility = Str::lower(trim((string) ($mappedRow['kelayakan'] ?? '')));
                $condition = match ($sourceCondition) {
                    'baru' => 'baru',
                    'lama' => 'lama',
                    default => 'baru',
                };
                $eligibility = match ($eligibility) {
                    'baik', 'layak' => 'layak',
                    default => 'layak',
                };

                $roomName = $mappedRow['ruangan'] ?? null;
                $cabinetName = $mappedRow['lemari'] ?? null;
                $code = $mappedRow['kode_barang'] ?? null;

                if ($code === null || is_numeric($code)) {
                    do {
                        $code = sprintf('BRG-%03d', ++$nextCodeNumber);
                    } while (isset($usedCodes[Str::upper($code)]));
                }

                $data = [
                    'kategori' => $mappedRow['kategori'] ?? null,
                    'kode_barang' => $code,
                    'nama_barang' => $mappedRow['nama_barang'] ?? null,
                    'satuan' => $mappedRow['satuan'] ?? null,
                    'stok_total' => $mappedRow['stok_total'] ?? 1,
                    'stok_tersedia' => $mappedRow['stok_tersedia'] ?? $mappedRow['stok_total'] ?? 1,
                    'kondisi' => $condition,
                    'kelayakan' => $eligibility,
                    'spesifikasi' => $mappedRow['spesifikasi'] ?? null,
                    'ruangan' => $roomName,
                    'lemari' => $cabinetName,
                ];

                $validator = Validator::make($data, [
                    'kategori' => ['required', 'string', 'max:100'],
                    'kode_barang' => ['required', 'string', 'max:30'],
                    'nama_barang' => ['required', 'string', 'max:150'],
                    'satuan' => ['required', 'string', 'max:20'],
                    'stok_total' => ['required', 'integer', 'min:0'],
                    'stok_tersedia' => ['required', 'integer', 'min:0'],
                ]);

                if ($validator->fails()) {
                    $failedRows++;
                    $rowErrors[] = ['row' => $rowNumber, 'message' => implode(' | ', $validator->errors()->all())];

                    continue;
                }

                $imagePathStored = null;
                try {
                    // 3. Pencocokan nama file gambar dari Excel dengan file di dalam ZIP
                    $imageColumnValue = $mappedRow['image'] ?? null;
                    if ($imageColumnValue) {
                        $cleanImageName = Barang::normalizeImageReference($imageColumnValue);
                        if (isset($extractedImages[$cleanImageName])) {
                            $imgData = $extractedImages[$cleanImageName];
                            $storageFileName = Str::uuid().'.'.$imgData['ext'];
                            $imagePathStored = Storage::disk('public')->putFileAs(
                                'barang_img',
                                new File($imgData['path']),
                                $storageFileName,
                            );
                            if ($imagePathStored === false) {
                                throw new \RuntimeException('Gagal menyimpan file gambar.');
                            }
                        }
                    }

                    DB::transaction(function () use (
                        $validator,
                        &$kategoriByName,
                        &$ruanganByName,
                        &$lemariByRoomAndName,
                        $operator,
                        $log,
                        $fileName,
                        $roomName,
                        $cabinetName,
                        $imagePathStored,
                    ): void {
                        $validated = $validator->validated();
                        $kategori = $this->resolveKategori($validated['kategori'], $kategoriByName);
                        $ruangan = filled($roomName) ? $this->resolveRuangan($roomName, null, $ruanganByName) : null;
                        $lemari = $ruangan instanceof Ruangan && filled($cabinetName)
                          ? $this->resolveLemari($ruangan, $cabinetName, null, $lemariByRoomAndName)
                          : null;

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

                        if ($ruangan instanceof Ruangan) {
                            $barang->lokasiBarangs()->create([
                                'ruangan_id' => $ruangan->ruangan_id,
                                'lemari_id' => $lemari?->lemari_id,
                                'stok_total' => $barang->stok_total,
                                'stok_tersedia' => $barang->stok_tersedia,
                            ]);
                        }

                        $this->recordInboundMutation(
                            $barang,
                            $operator,
                            $ruangan?->ruangan_id,
                            $lemari?->lemari_id,
                            (int) $validated['stok_total'],
                            0,
                            $barang->stok_tersedia,
                            $log->log_input_id,
                            "Barang diimpor dari {$fileName}.",
                        );
                    });
                } catch (\Throwable $ex) {
                    if (isset($imagePathStored) && is_string($imagePathStored)) {
                        Storage::disk('public')->delete($imagePathStored);
                    }

                    $failedRows++;
                    $rowErrors[] = [
                        'row' => $rowNumber,
                        'message' => 'Baris gagal diproses. Periksa data dan pastikan kode barang belum digunakan.',
                    ];

                    continue;
                }

                $successRows++;
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

        $details = is_array($logInput->detail_error) ? $logInput->detail_error : [];
        $storedRowErrors = is_array($details[2] ?? null) ? $details[2] : [];
        // ASVS 16.5.1: Return safe row summaries rather than stored exception details.
        $rowErrors = array_map(
            static function (mixed $error): array {
                $row = is_array($error) ? ($error['row'] ?? $error[0] ?? null) : null;

                return [
                    'row' => is_int($row) ? $row : null,
                    'message' => 'Baris gagal diproses. Periksa data wajib dan pastikan kode barang belum digunakan.',
                ];
            },
            array_slice($storedRowErrors, 0, 20),
        );
        $status = $logInput->status;

        $message = match ($status) {
            'proses' => 'Import masih dalam antrean atau sedang diproses.',
            'selesai' => "Import selesai. Berhasil: {$logInput->baris_berhasil}, gagal: {$logInput->baris_gagal}.",
            'sebagian' => "Import selesai sebagian. Berhasil: {$logInput->baris_berhasil}, gagal: {$logInput->baris_gagal}.",
            default => 'Import gagal diproses. Periksa format file, kolom wajib, dan arsip ZIP.',
        };

        return response()->json([
            'success' => $status !== 'gagal',
            'status' => $status,
            'total_rows' => $logInput->total_baris,
            'success_rows' => $logInput->baris_berhasil,
            'failed_rows' => $logInput->baris_gagal,
            'message' => $message,
            'errors' => $rowErrors,
            'errors_truncated' => count($storedRowErrors) > 20,
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
                    $storedImagePath = $request->file('image')->store('barang_img', 'public');
                    if (! is_string($storedImagePath)) {
                        throw new \RuntimeException('Gambar barang gagal disimpan.');
                    }

                    $imagePathStored = $storedImagePath;
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
                Storage::disk('public')->delete($imagePathStored);
            }

            throw $exception;
        }

        return redirect()->route('admin.barang')
            ->with('success', 'Barang berhasil ditambahkan.')
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
                unset($attributes['barang_form'], $attributes['locations'], $attributes['image']);

                $lockedBarang = Barang::query()
                    ->whereKey($barang->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($request->hasFile('image')) {
                    $previousImagePath = $lockedBarang->image_path;
                    $newImagePathStored = $request->file('image')->store('barang_img', 'public');
                    if (! is_string($newImagePathStored)) {
                        throw new \RuntimeException('Gambar barang gagal disimpan.');
                    }

                    $attributes['image_path'] = $newImagePathStored;
                }

                if ($isEdit) {
                    $this->syncStockLocations($lockedBarang, $locations, $actor);
                }

                $lockedBarang->update($attributes);
            });
        } catch (\Throwable $exception) {
            if (is_string($newImagePathStored)) {
                Storage::disk('public')->delete($newImagePathStored);
            }

            throw $exception;
        }

        if (is_string($previousImagePath) && Storage::disk('public')->exists($previousImagePath)) {
            Storage::disk('public')->delete($previousImagePath);
        }

        return redirect()->route('admin.barang')
            ->with('success', 'Barang berhasil diperbarui.')
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

        if ($unallocatedTotal < 0 || $unallocatedAvailable < 0 || $unallocatedTotal !== $unallocatedAvailable) {
            throw ValidationException::withMessages([
                'locations' => 'Stok lama memiliki jumlah yang belum terpetakan atau sedang dipinjam. Periksa riwayat stok sebelum mengubah lokasi.',
            ]);
        }

        foreach ($existingLocations as $key => $location) {
            $reservedQuantity = $location->stok_total - $location->stok_tersedia;
            $desired = $desiredLocations[$key] ?? null;

            if ($desired === null && $reservedQuantity > 0) {
                throw ValidationException::withMessages([
                    'locations' => 'Lokasi tidak dapat dihapus karena masih memiliki barang yang sedang dipinjam atau dipakai.',
                ]);
            }

            if ($desired !== null && $reservedQuantity !== $desired['stok_total'] - $desired['stok_tersedia']) {
                throw ValidationException::withMessages([
                    'locations' => 'Stok yang sedang dipinjam atau dipakai harus tetap berada di lokasi asal.',
                ]);
            }
        }

        foreach ($desiredLocations as $key => $desired) {
            if (! $existingLocations->has($key) && $desired['stok_total'] !== $desired['stok_tersedia']) {
                throw ValidationException::withMessages([
                    'locations' => 'Lokasi baru hanya dapat menerima stok yang tersedia.',
                ]);
            }
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
                ];
            }
        }

        $sourceTotal = array_sum(array_column($sources, 'quantity'));
        $targetTotal = array_sum(array_column($targets, 'quantity'));

        if ($sourceTotal !== $targetTotal) {
            throw ValidationException::withMessages([
                'locations' => 'Jumlah stok lokasi tidak seimbang. Pastikan stok total dan stok tersedia tetap sama.',
            ]);
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

            if ($lockedBarang->detailPeminjamans()->exists()) {
                return 'Barang tidak dapat dihapus karena memiliki riwayat peminjaman.';
            }

            if ($lockedBarang->barangDipakais()->exists()) {
                return 'Barang tidak dapat dihapus karena memiliki riwayat pemakaian.';
            }

            if ($lockedBarang->logMutasiBarangs()->exists()) {
                return 'Barang tidak dapat dihapus karena memiliki riwayat mutasi.';
            }

            if ($lockedBarang->image_path && Storage::disk('public')->exists($lockedBarang->image_path)) {
                Storage::disk('public')->delete($lockedBarang->image_path);
            }

            $lockedBarang->lokasiBarangs()->delete();
            $lockedBarang->delete();

            return null;
        });

        if ($errorMessage !== null) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $errorMessage], 409);
            }

            return redirect()->route('admin.barang')
                ->with('error', $errorMessage);
        }

        if ($request->expectsJson()) {
            $request->session()->flash('success', 'Barang berhasil dihapus.');
            $request->session()->flash('barang_feedback_name', $barangName);
            $request->session()->flash('barang_feedback_action', 'dihapus');

            return response()->json(['message' => 'Barang berhasil dihapus.']);
        }

        return redirect()->route('admin.barang')
            ->with('success', 'Barang berhasil dihapus.')
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
                'kode_barang',
                'kode_ruangan',
                'ruangan',
                'kode_lemari',
                'lemari',
                'stok_tersedia',
                'kelayakan',
                'spesifikasi',
                'image',
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

    private function resolveKategori(string $name, array &$index): Kategori
    {
        $key = Str::lower(trim($name));
        $kategori = $index[$key] ?? Kategori::withTrashed()
            ->whereRaw('LOWER(TRIM(nama_kategori)) = ?', [$key])
            ->first();

        if (! $kategori instanceof Kategori) {
            $kategori = Kategori::create(['nama_kategori' => trim($name)]);
        } elseif ($kategori->trashed()) {
            $kategori->restore();
        }

        $index[$key] = $kategori;

        return $kategori;
    }

    private function resolveRuangan(string $name, ?string $code, array &$index): Ruangan
    {
        $key = Str::lower(trim($name));
        $ruangan = $index[$key] ?? Ruangan::withTrashed()
            ->whereRaw('LOWER(TRIM(nama_ruangan)) = ?', [$key])
            ->first();

        if (! $ruangan instanceof Ruangan) {
            $ruangan = Ruangan::create([
                'kode_ruangan' => trim((string) ($code ?? $this->nextLocationCode(
                    'RGN',
                    Ruangan::withTrashed()->lockForUpdate()->pluck('kode_ruangan')->all(),
                ))),
                'nama_ruangan' => trim($name),
            ]);
        } elseif ($ruangan->trashed()) {
            $ruangan->restore();
        }

        $index[$key] = $ruangan;

        return $ruangan;
    }

    private function resolveLemari(Ruangan $ruangan, string $name, ?string $code, array &$index): Lemari
    {
        $key = $ruangan->ruangan_id.'|'.Str::lower(trim($name));
        $lemari = $index[$key] ?? Lemari::withTrashed()
            ->where('ruangan_id', $ruangan->ruangan_id)
            ->whereRaw('LOWER(TRIM(nama_lemari)) = ?', [Str::lower(trim($name))])
            ->first();

        if (! $lemari instanceof Lemari) {
            $lemari = Lemari::create([
                'ruangan_id' => $ruangan->ruangan_id,
                'kode_lemari' => trim((string) ($code ?? $this->nextLocationCode(
                    'LMR',
                    Lemari::withTrashed()
                        ->where('ruangan_id', $ruangan->ruangan_id)
                        ->lockForUpdate()
                        ->pluck('kode_lemari')
                        ->all(),
                ))),
                'nama_lemari' => trim($name),
            ]);
        } elseif ($lemari->trashed()) {
            $lemari->restore();
        }

        $index[$key] = $lemari;

        return $lemari;
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
