<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageBarang\ImportRequest;
use App\Http\Requests\ManageBarang\StoreRequest;
use App\Http\Requests\ManageBarang\UpdateRequest;
use App\Models\Barang;
use App\Models\Kategori;
use App\Models\Lemari;
use App\Models\LogInput;
use App\Models\LokasiBarang;
use App\Models\Ruangan;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

        /** @var View $renderedView */
        $renderedView = view('pages.admin.barang', compact('barangs', 'kategoris', 'ruangans', 'lemaris', 'filters'));

        return $renderedView->fragmentIf($request->ajax(), 'barang-results');
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

        $usedCodes = array_fill_keys(Barang::withTrashed()->pluck('kode_barang')->all(), true);
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
                } while (isset($usedCodes[$code]));
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

            // ASVS V2.2.1/V2.2.2/V2.2.3: Validate spreadsheet values and related stock/location fields server-side.
            $validator = Validator::make($data, [
                'kategori' => ['required', 'string', 'max:100'],
                'kode_barang' => ['required', 'string', 'max:30', Rule::unique('barang', 'kode_barang')],
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
            });

            if ($validator->fails()) {
                $failedRows++;
                $rowErrors[] = ['row' => $rowNumber, 'message' => $validator->errors()->first()];

                continue;
            }

            try {
                DB::transaction(function () use (
                    $validator,
                    &$kategoriByName,
                    &$ruanganByName,
                    &$lemariByRoomAndName,
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
                        LokasiBarang::create([
                            'barang_id' => $barang->barang_id,
                            'ruangan_id' => $ruangan->ruangan_id,
                            'lemari_id' => $lemari?->lemari_id,
                            'stok_total' => $barang->stok_total,
                            'stok_tersedia' => $barang->stok_tersedia,
                        ]);
                    }
                });
            } catch (UniqueConstraintViolationException) {
                $failedRows++;
                $rowErrors[] = [
                    'row' => $rowNumber,
                    'message' => 'Kode atau referensi sudah digunakan. Periksa data lalu coba lagi.',
                ];

                continue;
            }

            $usedCodes[$data['kode_barang']] = true;
            $successRows++;
        }

        return $this->completeImport($log, $fileName, $totalRows, $successRows, $failedRows, $rowErrors);
    }

    public function store(StoreRequest $request): RedirectResponse
    {
        $barang = DB::transaction(function () use ($request): Barang {
            $attributes = $request->validated();
            $ruanganId = $attributes['ruangan_id'];
            $lemariIds = $attributes['lemari_id'] ?? [];
            unset($attributes['ruangan_id'], $attributes['lemari_id']);
            $attributes['kode_barang'] = $this->nextBarangCode();

            $barang = Barang::create($attributes);
            $this->syncBarangLocations($barang, $ruanganId, $lemariIds);

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
        DB::transaction(function () use ($request, $barang): void {
            $attributes = $request->validated();
            $isEdit = $attributes['barang_form'] === 'edit';
            $ruanganId = $attributes['ruangan_id'] ?? null;
            $lemariIds = $attributes['lemari_id'] ?? [];
            unset($attributes['barang_form'], $attributes['ruangan_id'], $attributes['lemari_id']);

            $barang->update($attributes);

            if ($isEdit) {
                $this->syncBarangLocations($barang, $ruanganId, $lemariIds);
            }
        });

        return redirect()->route('admin.barang')
            ->with('success', 'Barang berhasil diperbarui.')
            ->with('barang_feedback_name', $barang->nama_barang)
            ->with('barang_feedback_action', 'diperbarui');
    }

    /**
     * @param  array<int, string>  $lemariIds
     */
    private function syncBarangLocations(Barang $barang, string $ruanganId, array $lemariIds): void
    {
        $lemariIds = array_values(array_unique($lemariIds));
        $existingLocations = $barang->lokasiBarangs()
            ->where('ruangan_id', $ruanganId)
            ->get()
            ->keyBy(fn (LokasiBarang $location): string => (string) $location->lemari_id);
        $selectedKeys = $lemariIds === [] ? [''] : $lemariIds;
        $reusableLocation = $existingLocations->isEmpty()
            ? $barang->lokasiBarangs()
                ->orderBy('created_at')
                ->orderBy('lokasi_barang_id')
                ->first()
            : null;

        foreach ($selectedKeys as $index => $lemariId) {
            $location = $existingLocations->get((string) $lemariId);
            $attributes = [
                'ruangan_id' => $ruanganId,
                'lemari_id' => $lemariId === '' ? null : $lemariId,
                'stok_total' => 0,
                'stok_tersedia' => 0,
            ];

            if (! $location instanceof LokasiBarang && $index === 0 && $reusableLocation instanceof LokasiBarang) {
                $location = $reusableLocation;
            }

            if ($location instanceof LokasiBarang) {
                $location->update($attributes);
            } else {
                $barang->lokasiBarangs()->create($attributes);
            }
        }

        $barang->lokasiBarangs()
            ->where('ruangan_id', $ruanganId)
            ->when(
                $lemariIds === [],
                fn ($query) => $query->whereNotNull('lemari_id'),
                fn ($query) => $query->where(function ($query) use ($lemariIds): void {
                    $query->whereNull('lemari_id')
                        ->orWhereNotIn('lemari_id', $lemariIds);
                }),
            )
            ->delete();
    }

    public function destroy(Barang $barang): RedirectResponse
    {
        $barangName = $barang->nama_barang;

        $deleted = DB::transaction(function () use ($barang): bool {
            $lockedBarang = Barang::query()
                ->whereKey($barang->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $hasHistory = $lockedBarang->lokasiBarangs()->exists()
                || $lockedBarang->detailPeminjamans()->exists()
                || $lockedBarang->barangDipakais()->exists()
                || $lockedBarang->logMutasiBarangs()->exists();

            if ($hasHistory) {
                return false;
            }

            $lockedBarang->delete();

            return true;
        });

        if (! $deleted) {
            return redirect()->route('admin.barang')
                ->with('error', 'Barang tidak dapat dihapus karena memiliki riwayat lokasi atau transaksi.');
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
