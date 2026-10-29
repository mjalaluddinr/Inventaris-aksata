<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageBarang\ImportRequest;
use App\Http\Requests\ManageBarang\StoreRequest;
use App\Http\Requests\ManageBarang\UpdateRequest;
use App\Models\Barang;
use App\Models\BarangDipakai;
use App\Models\Kategori;
use App\Models\Lemari;
use App\Models\LogInput;
use App\Models\LogMutasiBarang;
use App\Models\LokasiBarang;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Exceptions\NoSheetsFoundException;
use Maatwebsite\Excel\Exceptions\NoTypeDetectedException;
use Maatwebsite\Excel\Exceptions\UnreadableFileException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Exception as SpreadsheetException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader2;

class ManageBarang extends Controller
{
    private const MAX_IMPORT_ROWS = 1000;

    private const MAX_IMPORT_COLUMNS = 32;

    private const MAX_IMPORT_SHEETS = 10;

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
            'usageLocations',
        ));

        return $renderedView->fragmentIf($request->ajax(), 'barang-results');
    }

    public function recordUsage(Request $request, Barang $barang): RedirectResponse
    {
        // ASVS V2.2.1 / V8.2: validate submitted usage details and the selected admin account server-side.
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
            // ASVS V2.3: lock stock and location rows before checking and applying the quantity change.
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

        return redirect()->route('admin.barang', [
            'barang_id' => $barang->barang_id,
            'panel' => 'usage',
        ])->with('success', 'Pemakaian barang berhasil dicatat.');
    }

    public function withdrawUsage(Request $request, Barang $barang, BarangDipakai $usage): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        DB::transaction(function () use ($actor, $barang, $usage): void {
            // ASVS V2.3: serialize returns and reject duplicate withdrawals before restoring stock.
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

        return redirect()->route('admin.barang', [
            'barang_id' => $barang->barang_id,
            'panel' => 'history',
        ])->with('success', 'Barang berhasil ditarik kembali.');
    }

    public function import(ImportRequest $request): JsonResponse
    {
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            return response()->json(['message' => 'Pilih file yang akan diimpor.'], 422);
        }

        $fileName = Str::of(preg_replace('/[\x00-\x1F\x7F]/', '', basename(str_replace('\\', '/', $file->getClientOriginalName()))) ?: 'barang-import')
            ->limit(255, '')
            ->toString();

        $log = LogInput::create([
            'jenis_input' => 'barang',
            'nama_file' => $fileName,
            'total_baris' => 0,
            'baris_berhasil' => 0,
            'baris_gagal' => 0,
            'status' => 'proses',
            'mulai_at' => now(),
        ]);

        try {
            $path = $file->getRealPath();
            if ($path === false) {
                return $this->completeImport($log, $fileName, 0, 0, 0, [], 'File tidak dapat dibaca.');
            }

            $reader = IOFactory::createReaderForFile($path);
            // ASVS V5.2.1/V5.2.3: Enforce worksheet and archive bounds before loading spreadsheet data.
            if (! $reader instanceof IReader2 || ! $this->hasBoundedWorksheetInfo($reader->listWorksheetInfo($path))) {
                return $this->completeImport(
                    $log,
                    $fileName,
                    0,
                    0,
                    0,
                    [],
                    'File melebihi batas pemrosesan. Maksimal 1.000 baris data dan 32 kolom.',
                );
            }

            $emptyImport = new class implements ToArray
            {
                public function array(array $array): void {}
            };

            $sheets = Excel::toArray($emptyImport, $file);
        } catch (NoSheetsFoundException|NoTypeDetectedException|UnreadableFileException|SpreadsheetException) {
            // ASVS v5.0.0-16.5.1: Keep parser details out of the response.
            return $this->completeImport(
                $log,
                $fileName,
                0,
                0,
                0,
                [],
                'File tidak dapat dibaca. Pastikan file CSV atau Excel tidak rusak.',
            );
        }

        $rows = $sheets[0] ?? [];
        if ($rows === []) {
            return $this->completeImport($log, $fileName, 0, 0, 0, [], 'File tidak memiliki data untuk diimpor.');
        }

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
            return $this->completeImport(
                $log,
                $fileName,
                0,
                0,
                0,
                [],
                'Kolom wajib tidak ditemukan. Gunakan susunan kolom yang ditampilkan pada formulir impor.',
            );
        }
        $hasSequenceColumn = isset($mapping['kode_barang'], $mapping['nama_barang'])
            && $mapping['kode_barang'] < $mapping['nama_barang'];
        $sequenceColumnIndex = $mapping['kode_barang'] ?? null;
        if ($hasSequenceColumn) {
            $mapping['nama_barang'] = 1;
            unset($mapping['kode_barang']);
        }
        $legacyCategoryBeforeSpecification = isset($mapping['spesifikasi'])
            && $mapping['kategori'] < $mapping['spesifikasi'];
        $dataRows = array_slice($rows, $headerRowIndex + 1);
        $nonEmptyRows = array_filter($dataRows, fn (array $row): bool => ! $this->isEmptyImportRow($row));
        if ($nonEmptyRows === []) {
            return $this->completeImport($log, $fileName, 0, 0, 0, [], 'File tidak memiliki baris data untuk diimpor.');
        }

        if (count($nonEmptyRows) > self::MAX_IMPORT_ROWS) {
            return $this->completeImport(
                $log,
                $fileName,
                count($nonEmptyRows),
                0,
                count($nonEmptyRows),
                [],
                'File melebihi batas 1.000 baris data per impor.',
            );
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
            } elseif ($lemariByRoomAndName[$key] instanceof Lemari) {
                if ($lemariByRoomAndName[$key]->getAttribute('deleted_at') !== null && $lemari->getAttribute('deleted_at') === null) {
                    $lemariByRoomAndName[$key] = $lemari;
                } elseif (($lemariByRoomAndName[$key]->getAttribute('deleted_at') !== null) === ($lemari->getAttribute('deleted_at') !== null)) {
                    $lemariByRoomAndName[$key] = null;
                }
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

            $previousCategory = $previousValues['kategori'] ?? null;
            $isContinuationRow = $hasSequenceColumn
                && ! filled($row[$mapping['nama_barang']] ?? null)
                && filled($row[$mapping['spesifikasi']] ?? null)
                && ! is_numeric($row[$sequenceColumnIndex ?? 0] ?? null);

            if ($isContinuationRow) {
                $mappedRow['nama_barang'] = $this->importCell($row[$mapping['spesifikasi']] ?? null);
                $mappedRow['spesifikasi'] = null;
                $mappedRow['kategori'] = $mappedRow['kategori'] ?? $previousCategory;
            }

            if (
                $legacyCategoryBeforeSpecification
                && ! filled($mappedRow['nama_barang'] ?? null)
                && filled($mappedRow['kategori'] ?? null)
                && $previousCategory !== null
                && ! filled($mappedRow['spesifikasi'] ?? null)
            ) {
                $legacySpecification = $mappedRow['kategori'];
                $mappedRow['nama_barang'] = $mappedRow['kategori'];
                $mappedRow['kategori'] = $previousCategory;
                $mappedRow['spesifikasi'] = $legacySpecification;
            }
            foreach (array_keys($emptyLocationFields) as $field) {
                unset($previousValues[$field]);
                if ($field === 'ruangan') {
                    unset($previousValues['lemari']);
                    $mappedRow['lemari'] = null;
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
            if (preg_match('/^(baik|tidak\s+baik)\s*\(\s*(baru|lama)\s*\)$/u', $sourceCondition, $conditionParts) === 1) {
                $sourceCondition = $conditionParts[2];
                $eligibility = $conditionParts[1] === 'baik' ? 'layak' : 'tidak_layak';
            }

            $condition = match ($sourceCondition) {
                'baru' => 'baru',
                'lama' => 'lama',
                default => $sourceCondition,
            };
            $eligibility = match ($eligibility) {
                'baik', 'layak' => 'layak',
                'tidak baik', 'tidak layak', 'tidak_layak' => 'tidak_layak',
                default => str_replace(' ', '_', $eligibility),
            };
            $roomName = $mappedRow['ruangan'] ?? null;
            $roomKey = filled($roomName) ? Str::lower(trim($roomName)) : null;
            $room = $roomKey !== null && ($ruanganByName[$roomKey] ?? null) instanceof Ruangan
                ? $ruanganByName[$roomKey]
                : null;
            $cabinetName = $mappedRow['lemari'] ?? null;
            $roomId = $room instanceof Ruangan ? (string) $room->getAttribute('ruangan_id') : null;
            $cabinetKey = $roomId !== null && filled($cabinetName)
                ? $roomId.'|'.Str::lower(trim($cabinetName))
                : null;
            $cabinet = $cabinetKey !== null && ($lemariByRoomAndName[$cabinetKey] ?? null) instanceof Lemari
                ? $lemariByRoomAndName[$cabinetKey]
                : null;

            $code = $mappedRow['kode_barang'] ?? null;
            if ($code === null) {
                do {
                    $code = sprintf('BRG-%03d', ++$nextCodeNumber);
                } while (isset($usedCodes[Str::upper($code)]));
            }

            $data = [
                'kategori' => $mappedRow['kategori'] ?? null,
                'kode_barang' => $code,
                'nama_barang' => $mappedRow['nama_barang'] ?? null,
                'satuan' => $mappedRow['satuan'] ?? null,
                'stok_total' => $mappedRow['stok_total'] ?? null,
                'stok_tersedia' => $mappedRow['stok_tersedia'] ?? $mappedRow['stok_total'] ?? null,
                'kondisi' => $condition,
                'kelayakan' => $eligibility,
                'spesifikasi' => $mappedRow['spesifikasi'] ?? null,
                'ruangan' => $roomName,
                'kode_ruangan' => $mappedRow['kode_ruangan'] ?? null,
                'lemari' => $cabinetName,
                'kode_lemari' => $mappedRow['kode_lemari'] ?? null,
            ];

            $groupKey = Str::upper(trim($data['kode_barang']));
            $existingImportedItem = $importedItems[$groupKey] ?? null;
            $groupMetadata = [
                'kategori' => Str::lower(trim((string) $data['kategori'])),
                'nama_barang' => Str::lower(trim((string) $data['nama_barang'])),
                'satuan' => Str::lower(trim((string) $data['satuan'])),
                'kondisi' => $data['kondisi'],
                'kelayakan' => $data['kelayakan'],
                'spesifikasi' => Str::lower(trim((string) ($data['spesifikasi'] ?? ''))),
            ];
            $locationKey = filled($roomName)
                ? Str::lower(trim($roomName)).'|'.Str::lower(trim((string) ($cabinetName ?? '')))
                : null;
            $codeRules = ['required', 'string', 'max:30'];
            if ($existingImportedItem === null) {
                $codeRules[] = Rule::unique('barang', 'kode_barang');
            }

            // ASVS V2.2.1/V2.2.2/V2.2.3: Validate spreadsheet values and related stock/location fields server-side.
            $validator = Validator::make($data, [
                'kategori' => ['required', 'string', 'max:100'],
                'kode_barang' => $codeRules,
                'nama_barang' => ['required', 'string', 'max:150'],
                'satuan' => ['required', 'string', 'max:20'],
                'stok_total' => ['required', 'integer', 'min:0'],
                'stok_tersedia' => ['required', 'integer', 'min:0', 'lte:stok_total'],
                'kondisi' => ['required', Rule::in(['baru', 'lama'])],
                'kelayakan' => ['required', Rule::in(['layak', 'tidak_layak'])],
                'spesifikasi' => ['nullable', 'string', 'max:255'],
                'ruangan' => [
                    filled($cabinetName) ? 'required' : 'nullable',
                    'string',
                    'max:100',
                ],
                'kode_ruangan' => filled($roomName)
                        ? ['nullable', 'string', 'max:20']
                        : ['prohibited'],
                'lemari' => filled($roomName) ? ['nullable', 'string', 'max:100'] : ['prohibited'],
                'kode_lemari' => filled($cabinetName)
                        ? ['nullable', 'string', 'max:20']
                        : ['prohibited'],
            ], [
                'kategori.required' => 'Kategori wajib diisi.',
                'kategori.max' => 'Nama kategori maksimal 100 karakter.',
                'kode_barang.required' => 'Kode barang wajib diisi.',
                'kode_barang.max' => 'Kode barang maksimal 30 karakter.',
                'kode_barang.unique' => 'Kode barang sudah digunakan.',
                'nama_barang.required' => 'Nama barang wajib diisi.',
                'nama_barang.max' => 'Nama barang maksimal 150 karakter.',
                'satuan.required' => 'Satuan wajib diisi.',
                'satuan.max' => 'Satuan maksimal 20 karakter.',
                'stok_total.required' => 'Stok total wajib diisi.',
                'stok_total.integer' => 'Stok total harus berupa bilangan bulat.',
                'stok_total.min' => 'Stok total tidak boleh kurang dari nol.',
                'stok_tersedia.required' => 'Stok tersedia wajib diisi.',
                'stok_tersedia.integer' => 'Stok tersedia harus berupa bilangan bulat.',
                'stok_tersedia.min' => 'Stok tersedia tidak boleh kurang dari nol.',
                'stok_tersedia.lte' => 'Stok tersedia tidak boleh melebihi stok total.',
                'kondisi.required' => 'Kondisi wajib diisi.',
                'kondisi.in' => 'Kondisi harus Baru atau Lama.',
                'kelayakan.required' => 'Kelayakan wajib diisi.',
                'kelayakan.in' => 'Kelayakan harus Layak atau Tidak layak.',
                'spesifikasi.max' => 'Spesifikasi maksimal 255 karakter.',
                'ruangan.required' => 'Ruangan wajib diisi jika lemari dicantumkan.',
                'ruangan.max' => 'Nama ruangan maksimal 100 karakter.',
                'kode_ruangan.max' => 'Kode ruangan maksimal 20 karakter.',
                'lemari.prohibited' => 'Ruangan wajib diisi jika lemari dicantumkan.',
                'lemari.max' => 'Nama lemari maksimal 100 karakter.',
                'kode_lemari.max' => 'Kode lemari maksimal 20 karakter.',
            ]);

            $validator->after(function ($validator) use (
                $data,
                $kategoriByName,
                $ruanganByName,
                $lemariByRoomAndName,
                $room,
                $cabinet,
                $existingImportedItem,
                $groupMetadata,
                $locationKey,
                $roomName,
            ): void {
                $categoryKey = Str::lower(trim((string) $data['kategori']));
                if (array_key_exists($categoryKey, $kategoriByName) && $kategoriByName[$categoryKey] === null) {
                    $validator->errors()->add('kategori', 'Nama kategori cocok dengan lebih dari satu data.');
                }

                $roomKey = filled($data['ruangan']) ? Str::lower(trim($data['ruangan'])) : null;
                if ($roomKey !== null && array_key_exists($roomKey, $ruanganByName) && $ruanganByName[$roomKey] === null) {
                    $validator->errors()->add('ruangan', 'Nama ruangan cocok dengan lebih dari satu data.');
                }

                if ($room === null && filled($data['kode_ruangan']) && Ruangan::withTrashed()->where('kode_ruangan', $data['kode_ruangan'])->exists()) {
                    $validator->errors()->add('kode_ruangan', 'Kode ruangan sudah digunakan.');
                }

                $roomId = $room instanceof Ruangan ? (string) $room->getAttribute('ruangan_id') : null;
                $cabinetKey = $roomId !== null && filled($data['lemari'])
                    ? $roomId.'|'.Str::lower(trim($data['lemari']))
                    : null;
                if ($cabinetKey !== null && array_key_exists($cabinetKey, $lemariByRoomAndName) && $lemariByRoomAndName[$cabinetKey] === null) {
                    $validator->errors()->add('lemari', 'Nama lemari cocok dengan lebih dari satu data.');
                }

                if ($room !== null && $cabinet === null && filled($data['kode_lemari']) && Lemari::withTrashed()
                    ->where('ruangan_id', $roomId)
                    ->where('kode_lemari', $data['kode_lemari'])
                    ->exists()) {
                    $validator->errors()->add('kode_lemari', 'Kode lemari sudah digunakan di ruangan tersebut.');
                }

                if ($existingImportedItem !== null) {
                    if ($existingImportedItem['metadata'] !== $groupMetadata) {
                        $validator->errors()->add('kode_barang', 'Data barang dengan kode yang sama harus memiliki nama, kategori, satuan, kondisi, kelayakan, dan spesifikasi yang sama.');
                    }

                    if ($locationKey === null || $existingImportedItem['locations'] === []) {
                        $validator->errors()->add('ruangan', 'Setiap baris dengan kode barang yang sama harus memiliki lokasi penyimpanan.');
                    } elseif (isset($existingImportedItem['locations'][$locationKey])) {
                        $validator->errors()->add('ruangan', 'Kode barang yang sama harus menggunakan lokasi penyimpanan yang berbeda di setiap baris.');
                    }
                }
            });

            if ($validator->fails()) {
                $failedRows++;
                $rowErrors[] = ['row' => $rowNumber, 'message' => $validator->errors()->first()];

                continue;
            }

            try {
                $operator = $request->user();
                abort_unless($operator instanceof User, 403);

                DB::transaction(function () use (
                    $validator,
                    &$kategoriByName,
                    &$ruanganByName,
                    &$lemariByRoomAndName,
                    &$importedItems,
                    $operator,
                    $log,
                    $fileName,
                    $groupKey,
                    $existingImportedItem,
                    $groupMetadata,
                    $locationKey,
                ): void {
                    $validated = $validator->validated();
                    $kategori = $this->resolveKategori($validated['kategori'], $kategoriByName);
                    $ruangan = filled($validated['ruangan'] ?? null)
                        ? $this->resolveRuangan($validated['ruangan'], $validated['kode_ruangan'] ?? null, $ruanganByName)
                        : null;
                    $lemari = $ruangan instanceof Ruangan && filled($validated['lemari'] ?? null)
                        ? $this->resolveLemari(
                            $ruangan,
                            $validated['lemari'],
                            $validated['kode_lemari'] ?? null,
                            $lemariByRoomAndName,
                        )
                        : null;

                    if ($existingImportedItem !== null) {
                        $barang = Barang::query()
                            ->whereKey($existingImportedItem['barang_id'])
                            ->lockForUpdate()
                            ->firstOrFail();
                        $stockBefore = $barang->stok_tersedia;
                        $barang->stok_total += (int) $validated['stok_total'];
                        $barang->stok_tersedia += (int) $validated['stok_tersedia'];
                        $barang->save();
                        $barang->lokasiBarangs()->create([
                            'ruangan_id' => $ruangan->ruangan_id,
                            'lemari_id' => $lemari?->lemari_id,
                            'stok_total' => (int) $validated['stok_total'],
                            'stok_tersedia' => (int) $validated['stok_tersedia'],
                        ]);
                    } else {
                        $barang = Barang::create([
                            'kategori_id' => $kategori->kategori_id,
                            'kode_barang' => $validated['kode_barang'],
                            'nama_barang' => $validated['nama_barang'],
                            'satuan' => $validated['satuan'],
                            'stok_total' => $validated['stok_total'],
                            'stok_tersedia' => $validated['stok_tersedia'],
                            'kondisi' => $validated['kondisi'],
                            'kelayakan' => $validated['kelayakan'],
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

                        $stockBefore = 0;
                    }

                    $this->recordInboundMutation(
                        $barang,
                        $operator,
                        $ruangan?->ruangan_id,
                        $lemari?->lemari_id,
                        (int) $validated['stok_total'],
                        $stockBefore,
                        $barang->stok_tersedia,
                        $log->log_input_id,
                        "Barang diimpor dari {$fileName}.",
                    );

                    $groupLocations = $existingImportedItem['locations'] ?? [];
                    if ($locationKey !== null) {
                        $groupLocations[$locationKey] = true;
                    }

                    $importedItems[$groupKey] = [
                        'barang_id' => $barang->barang_id,
                        'metadata' => $groupMetadata,
                        'locations' => $groupLocations,
                    ];
                });
            } catch (UniqueConstraintViolationException) {
                $failedRows++;
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'message' => 'Kode atau referensi sudah digunakan. Periksa data lalu coba lagi.',
                ];

                continue;
            }

            $usedCodes[$groupKey] = true;
            $successRows++;
        }

        return $this->completeImport($log, $fileName, $totalRows, $successRows, $failedRows, $rowErrors);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $actor = $request->user();
        abort_unless($actor instanceof User, 403);

        $barang = DB::transaction(function () use ($request, $actor): Barang {
            $attributes = $request->validated();
            $locations = $attributes['locations'];
            unset($attributes['locations']);
            $attributes['kode_barang'] = $this->nextBarangCode();

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

        DB::transaction(function () use ($request, $barang, $actor): void {
            $attributes = $request->validated();
            $isEdit = $attributes['barang_form'] === 'edit';
            $locations = $attributes['locations'] ?? [];
            unset($attributes['barang_form'], $attributes['locations']);

            $lockedBarang = Barang::query()
                ->whereKey($barang->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if ($isEdit) {
                $this->syncStockLocations($lockedBarang, $locations, $actor);
            }

            $lockedBarang->update($attributes);
        });

        return redirect()->route('admin.barang')
            ->with('success', 'Barang berhasil diperbarui.')
            ->with('barang_feedback_name', $barang->nama_barang)
            ->with('barang_feedback_action', 'diperbarui');
    }

    /**
     * @param  array<int, array{ruangan_id: string, lemari_id: string|null, stok_total: int, stok_tersedia: int}>  $allocations
     */
    private function syncStockLocations(Barang $barang, array $allocations, User $operator): void
    {
        $existingLocations = $barang->lokasiBarangs()
            ->with(['ruangan', 'lemari'])
            ->lockForUpdate()
            ->get()
            ->keyBy(fn (LokasiBarang $location): string => $this->stockLocationKey(
                $location->ruangan_id,
                $location->lemari_id,
            ));
        $desiredLocations = [];

        foreach ($allocations as $attributes) {
            $key = $this->stockLocationKey($attributes['ruangan_id'], $attributes['lemari_id'] ?? null);
            $desiredLocations[$key] = [
                'ruangan_id' => $attributes['ruangan_id'],
                'lemari_id' => $attributes['lemari_id'] ?: null,
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
        $roomNames = Ruangan::query()->whereIn('ruangan_id', $roomIds)->pluck('nama_ruangan', 'ruangan_id');
        $cabinetNames = Lemari::query()->whereIn('lemari_id', $cabinetIds)->pluck('nama_lemari', 'lemari_id');
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
                    : $this->stockLocationLabel($sourceRoomId, $sourceCabinetId, $roomNames, $cabinetNames);
                $destinationLabel = $this->stockLocationLabel(
                    $destination['ruangan_id'],
                    $destination['lemari_id'],
                    $roomNames,
                    $cabinetNames,
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

    private function stockLocationKey(string $roomId, ?string $cabinetId): string
    {
        return $roomId.'|'.($cabinetId ?? '');
    }

    /**
     * @param  Collection<string, string>  $roomNames
     * @param  Collection<string, string>  $cabinetNames
     */
    private function stockLocationLabel(
        string $roomId,
        ?string $cabinetId,
        Collection $roomNames,
        Collection $cabinetNames,
    ): string {
        $roomName = $roomNames->get($roomId, 'Ruangan tidak tersedia');
        $cabinetName = $cabinetId === null ? null : $cabinetNames->get($cabinetId, 'Lemari tidak tersedia');

        return $cabinetName === null ? $roomName : "{$roomName} · {$cabinetName}";
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

            $lockedBarang->lokasiBarangs()->delete();
            $lockedBarang->delete();

            return null;
        });

        if ($errorMessage !== null) {
            if ($request->expectsJson()) {
                // ASVS 16.5.1: Return a clear domain error without exposing database or exception details.
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
    ): JsonResponse {
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

        $message = $fileError
            ?? "Import selesai. Berhasil: {$successRows}, gagal: {$failedRows}.";

        return response()->json([
            'success' => $fileError === null,
            'status' => $status,
            'total_rows' => $totalRows,
            'success_rows' => $successRows,
            'failed_rows' => $failedRows,
            'message' => $message,
            'errors' => array_slice($rowErrors, 0, 20),
            'errors_truncated' => count($rowErrors) > 20,
        ], $fileError === null ? 200 : 422);
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
            ],
        );

        return array_diff($requiredFields, array_keys($mapping)) === [] ? $mapping : null;
    }

    private function hasBoundedWorksheetInfo(array $worksheets): bool
    {
        // ASVS v5.0.0-5.2.1: Reject oversized dimensions before loading worksheet cells into memory.
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

    /**
     * @param  iterable<int|string, Model>  $records
     * @return array<string, Model|null>
     */
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

    /**
     * @param  array<string, Kategori|null>  $index
     */
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

    /**
     * @param  array<string, Ruangan|null>  $index
     */
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

    /**
     * @param  array<string, Lemari|null>  $index
     */
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

    /**
     * @param  array<int, string>  $usedCodes
     */
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
