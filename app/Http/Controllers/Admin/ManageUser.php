<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageUser\ImportRequest;
use App\Http\Requests\ManageUser\StoreRequest;
use App\Http\Requests\ManageUser\UpdateRequest;
use App\Jobs\ImportAkunJob;
use App\Models\LogInput;
use App\Models\User;
use App\Services\PasswordRecoveryRequests;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReader2;
use Throwable;

class ManageUser extends Controller
{
    private const MAX_IMPORT_ROWS = 1000;

    private const MAX_IMPORT_COLUMNS = 32;

    private const MAX_IMPORT_SHEETS = 10;

    public function index(Request $request, PasswordRecoveryRequests $recoveryRequests): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'password_reset' => ['nullable', 'uuid'],
        ]);
        $search = trim($filters['search'] ?? '');
        $searchTerms = preg_split('/\s+/', $search, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $passwordResetUser = null;

        if (filled($filters['password_reset'] ?? null)) {
            $passwordResetUser = User::query()->findOrFail($filters['password_reset']);
            $this->authorizeAccountManagement($passwordResetUser);
            abort_unless($passwordResetUser->must_change_password, 404);
        }

        $pendingPasswordRequests = $request->user()->canManageAllAccounts()
            ? $recoveryRequests->all()
            : [];
        $users = User::query()
            ->with('privileges')
            ->when(
                $request->user()->role === 'laboran',
                fn ($query) => $query
                    ->where('role', 'laboran')
                    ->where('akun_id', '!=', $request->user()->getAuthIdentifier()),
            )
            // ASVS 2.2.1, 1.2.4: each validated term is bound to the supported account fields.
            ->when($searchTerms !== [], function ($query) use ($searchTerms): void {
                foreach ($searchTerms as $term) {
                    $query->where(function ($query) use ($term): void {
                        $query->where('username', 'like', '%'.$term.'%')
                            ->orWhere('first_name', 'like', '%'.$term.'%')
                            ->orWhere('last_name', 'like', '%'.$term.'%');
                    });
                }
            })
            ->when($pendingPasswordRequests !== [], function ($query) use ($pendingPasswordRequests): void {
                $accountIds = array_keys($pendingPasswordRequests);
                $placeholders = implode(', ', array_fill(0, count($accountIds), '?'));

                // ASVS 1.2.4: Bind request IDs while prioritizing accounts awaiting admin review.
                $query->orderByRaw("CASE WHEN akun_id IN ({$placeholders}) THEN 0 ELSE 1 END", $accountIds);
            })
            ->latest()
            ->orderByDesc('must_change_password')
            ->paginate(10)
            ->withQueryString();

        /** @var View $renderedView */
        $renderedView = view('pages.admin.akun', compact(
            'users',
            'filters',
            'passwordResetUser',
            'pendingPasswordRequests',
        ));

        $responseContent = $renderedView->fragmentIf($request->ajax(), 'account-results');

        // ASVS 14.3.2: Prevent account data and one-time recovery credentials from being cached.
        return response($responseContent)->header('Cache-Control', 'no-store, private');
    }

    public function store(StoreRequest $request)
    {
        $validated = $request->validated();

        $user = User::create([
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'username' => $validated['username'],
            'password_hash' => bcrypt($validated['password']),
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
            'must_change_password' => $validated['role'] === 'peminjam'
                && (bool) ($validated['must_change_password'] ?? false),
        ]);

        $this->syncPrivilege($user, $user->role, $validated['privileges'] ?? []);

        return redirect()->route('admin.akun.index')
            ->with('success', "Akun {$user->username} berhasil ditambahkan.");
    }

    public function update(UpdateRequest $request, User $user)
    {
        $this->authorizeAccountManagement($user);
        $validated = $request->validated();

        $updateData = [
            'first_name' => $validated['first_name'] ?? null,
            'last_name' => $validated['last_name'] ?? null,
            'username' => $validated['username'],
            'role' => $validated['role'],
            'is_active' => $validated['is_active'] ?? true,
            'must_change_password' => $validated['role'] === 'peminjam'
                && (bool) ($validated['must_change_password'] ?? false),
        ];

        if (! empty($validated['password'])) {
            $updateData['password_hash'] = bcrypt($validated['password']);
        }

        $user->update($updateData);
        $this->syncPrivilege($user, $user->role, $validated['privileges'] ?? []);

        return redirect()->route('admin.akun.index')
            ->with('success', "Akun {$user->username} berhasil diperbarui.");
    }

    public function editPassword(User $user): RedirectResponse
    {
        $this->authorizeAccountManagement($user);
        abort_unless($user->must_change_password, 404);

        return redirect()->route('admin.akun.index', ['password_reset' => $user->getKey()]);
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAccountManagement($user);
        $validator = Validator::make($request->all(), [
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if ($validator->fails()) {
            return redirect()
                ->route('admin.akun.index', ['password_reset' => $user->getKey()])
                ->withErrors($validator)
                ->withInput($request->except(['new_password', 'new_password_confirmation']));
        }

        $validated = $validator->validated();

        DB::transaction(function () use ($user, $validated): void {
            $lockedUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            abort_unless($lockedUser->must_change_password, 404);

            $lockedUser->password_hash = $validated['new_password'];
            $lockedUser->must_change_password = false;
            $lockedUser->save();
        });

        return redirect()
            ->route('admin.akun.index')
            ->with('success', "Akun {$user->username} berhasil memperbarui password dan menyelesaikan reset.");
    }

    public function destroy(User $user, PasswordRecoveryRequests $recoveryRequests)
    {
        $this->authorizeAccountManagement($user);
        $recoveryRequests->reject($user);
        $user->privileges()->delete();
        $user->delete();

        return redirect()->route('admin.akun.index')
            ->with('success', "Akun {$user->username} berhasil dihapus.");
    }

    public function approvePasswordRecovery(User $user, PasswordRecoveryRequests $recoveryRequests): RedirectResponse
    {
        $this->authorizeAccountManagement($user);
        $temporaryPassword = $recoveryRequests->approve($user);
        abort_unless($temporaryPassword !== null, 404);

        return redirect()
            ->route('admin.akun.index')
            ->with('success', "Permintaan ganti password {$user->username} diterima.")
            ->with('recovery_temporary_password', $temporaryPassword);
    }

    public function rejectPasswordRecovery(User $user, PasswordRecoveryRequests $recoveryRequests): RedirectResponse
    {
        $this->authorizeAccountManagement($user);
        abort_unless($recoveryRequests->reject($user), 404);

        return redirect()->route('admin.akun.index')
            ->with('success', "Permintaan ganti password {$user->username} ditolak.");
    }

    // ✅ METHOD BARU: AJAX Toggle Status
    public function toggleStatus(User $user)
    {
        $this->authorizeAccountManagement($user);
        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $user->is_active,
            'message' => "Akun {$user->username} berhasil ".($user->is_active ? 'diaktifkan.' : 'dinonaktifkan.'),
        ]);
    }

    public function import(ImportRequest $request): JsonResponse
    {
        $file = $request->file('file');
        $fileName = Str::of(
            preg_replace(
                '/[\x00-\x1F\x7F]/',
                '',
                basename(str_replace('\\', '/', $file->getClientOriginalName())),
            ) ?: 'account-import',
        )->limit(255, '')->toString();

        $log = LogInput::create([
            'jenis_input' => 'akun',
            'nama_file' => $fileName,
            'total_baris' => 0,
            'baris_berhasil' => 0,
            'baris_gagal' => 0,
            'status' => 'proses',
            'mulai_at' => now(),
        ]);

        $uploadDirectory = 'temp/akun-import/'.$log->log_input_id;
        $filePath = null;

        try {
            $filePath = $file->storeAs(
                $uploadDirectory,
                'data.'.strtolower($file->getClientOriginalExtension()),
                'local',
            );

            if (! is_string($filePath)) {
                throw new \RuntimeException('File impor akun gagal disimpan.');
            }

            // ASVS 5.3.2: Store the validated upload under a server-generated path.
            $queueConnection = PHP_OS_FAMILY === 'Windows' ? 'deferred' : 'background';
            ImportAkunJob::dispatch($log->log_input_id, $uploadDirectory, $filePath)
                ->onConnection($queueConnection);
        } catch (Throwable $exception) {
            Storage::disk('local')->deleteDirectory($uploadDirectory);
            report($exception);
            $log->update([
                'status' => 'gagal',
                'detail_error' => [
                    $fileName,
                    'queue_failed',
                    [],
                ],
                'selesai_at' => now(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'File gagal dimasukkan ke antrean. Silakan coba kembali.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'status' => 'proses',
            'log_id' => $log->log_input_id,
            'file_name' => $fileName,
            'message' => 'File berhasil masuk antrean import akun.',
        ], 202);
    }

    public function processImport(string $logInputId, string $filePath): void
    {
        $log = LogInput::query()
            ->where('jenis_input', 'akun')
            ->findOrFail($logInputId);
        $fileName = $log->nama_file;
        $fileError = null;
        $rowErrors = [];
        $fileDiagnostics = null;
        $totalRows = 0;
        $successRows = 0;
        $failedRows = 0;
        $importCreatedAt = now();

        try {
            $dummyImport = new class implements ToArray
            {
                public function array(array $array): void {}
            };
            $path = Storage::disk('local')->path($filePath);
            // ASVS 5.2.1: Bound worksheet dimensions before loading spreadsheet data.
            $reader = IOFactory::createReaderForFile($path);
            if (! $reader instanceof IReader2 || ! $this->hasBoundedWorksheetInfo($reader->listWorksheetInfo($path))) {
                $fileError = 'file_limit_exceeded';
                throw new Exception('ACCOUNT_IMPORT_FILE_LIMIT_EXCEEDED');
            }

            $spreadsheet = Excel::toArray($dummyImport, $path);
            $rows = $spreadsheet[0] ?? [];

            if (empty($rows)) {
                throw new Exception('ACCOUNT_IMPORT_FILE_EMPTY');
            }

            $mapping = null;
            $headerRowIndex = -1;
            $bestHeaderRowIndex = 0;
            $bestHeaderScore = -1;
            $bestDetectedFields = [];
            for ($i = 0; $i < min(50, count($rows)); $i++) {
                $headers = array_map(fn ($header) => strtolower(trim((string) ($header ?? ''))), $rows[$i]);
                $tempMapping = $this->detectColumns($headers);
                $headerScore = count($tempMapping) * 100 + count(array_filter($headers));
                if ($headerScore > $bestHeaderScore) {
                    $bestHeaderRowIndex = $i;
                    $bestHeaderScore = $headerScore;
                    $bestDetectedFields = array_keys($tempMapping);
                }
                if (isset($tempMapping['username']) && isset($tempMapping['password'])) {
                    $mapping = $tempMapping;
                    $headerRowIndex = $i;
                    break;
                }
            }

            if (! $mapping) {
                $headerRowIndex = $bestHeaderRowIndex;
                $detectedHeaders = array_values(array_filter(
                    array_map(
                        static fn ($header): string => mb_substr(trim((string) ($header ?? '')), 0, 60),
                        $rows[$headerRowIndex] ?? [],
                    ),
                    static fn (string $header): bool => $header !== '',
                ));
                $fileDiagnostics = [
                    'row' => $headerRowIndex + 1,
                    'columns' => array_slice($detectedHeaders, 0, 20),
                    'required_columns' => ['username', 'password'],
                    'missing_columns' => array_values(array_diff(
                        ['username', 'password'],
                        $bestDetectedFields,
                    )),
                ];
                $totalRows = count(array_filter(
                    array_slice($rows, $headerRowIndex + 1),
                    static fn (mixed $row): bool => is_array($row) && array_filter(
                        $row,
                        static fn ($value): bool => trim((string) ($value ?? '')) !== '',
                    ) !== [],
                ));
                $failedRows = $totalRows;

                throw new Exception('ACCOUNT_IMPORT_HEADERS_MISSING');
            }

            $totalRows = count($rows) - ($headerRowIndex + 1);
            $dataRows = array_slice($rows, $headerRowIndex + 1);
            $nonEmptyDataRows = array_filter(
                $dataRows,
                static fn (mixed $row): bool => is_array($row) && array_filter(
                    $row,
                    static fn (mixed $value): bool => trim((string) ($value ?? '')) !== '',
                ) !== [],
            );
            if (count($nonEmptyDataRows) > self::MAX_IMPORT_ROWS) {
                $totalRows = count($nonEmptyDataRows);
                $failedRows = $totalRows;
                $fileError = 'file_limit_exceeded';
                throw new Exception('ACCOUNT_IMPORT_FILE_LIMIT_EXCEEDED');
            }

            $dataRowNumber = 0;
            for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
                $rowNumber = $i + 1;
                $rowData = $rows[$i];

                if (empty(array_filter($rowData, fn ($v) => trim($v ?? '') !== ''))) {
                    $totalRows--;

                    continue;
                }

                $dataRowNumber++;
                $failedColumns = [];
                $safeErrorMessage = null;
                try {
                    $data = $this->mapRowData($rowData, $mapping);
                    $data['must_change_password'] = false;
                    // ASVS V2.2.1: Canonicalize role and privilege values before allow-list validation.
                    $data['role'] = strtolower(trim((string) ($data['role'] ?? '')));
                    $data['privileges'] = array_values(array_unique(array_filter(
                        array_map(
                            static fn (string $privilege): string => strtolower(trim($privilege)),
                            $data['privileges'] ?? [],
                        ),
                        static fn (string $privilege): bool => $privilege !== '',
                    )));

                    if (($data['role'] ?? '') === 'laboran' && empty($data['privileges'])) {
                        $failedColumns = ['role', 'privileges'];
                        $safeErrorMessage = 'Role laboran wajib memiliki minimal 1 hak akses di kolom privilege.';
                        throw new Exception($safeErrorMessage);
                    }

                    $validator = Validator::make($data, [
                        'username' => ['required', 'string', 'max:50', Rule::unique('akun', 'username')->whereNull('deleted_at')],
                        'password' => 'required|string|min:6',
                        'role' => 'required|in:admin,laboran,peminjam',
                        'privileges' => ['nullable', 'array'],
                        'privileges.*' => ['string', Rule::in(['melihat', 'meminjamkan', 'manajemen'])],
                    ], [
                        'username.required' => 'Username wajib diisi.',
                        'username.unique' => 'Username sudah digunakan.',
                        'password.required' => 'Password wajib diisi.',
                        'password.min' => 'Password minimal 6 karakter.',
                        'role.required' => 'Role wajib diisi.',
                        'role.in' => 'Role harus admin, laboran, atau peminjam.',
                        'privileges.*.in' => 'Hak akses berisi nilai yang tidak dikenal.',
                    ]);

                    if ($validator->fails()) {
                        $failedColumns = array_values(array_unique(array_map(
                            static fn (string $field): string => explode('.', $field, 2)[0],
                            array_keys($validator->errors()->toArray()),
                        )));
                        $safeErrorMessage = $validator->errors()->first();
                        throw new Exception($safeErrorMessage);
                    }

                    DB::transaction(function () use ($data, $successRows, $importCreatedAt) {
                        $newUser = User::create([
                            'first_name' => $data['first_name'] ?? null,
                            'last_name' => $data['last_name'] ?? null,
                            'username' => $data['username'],
                            'password_hash' => bcrypt($data['password']),
                            'role' => $data['role'],
                            'is_active' => $data['is_active'] ?? true,
                            'must_change_password' => false,
                        ]);
                        $newUser->forceFill([
                            'created_at' => $importCreatedAt->copy()->subSeconds($successRows),
                        ])->saveQuietly();

                        if ($newUser->role === 'laboran' && ! empty($data['privileges'])) {
                            foreach ($data['privileges'] as $hak) {
                                $newUser->privileges()->create(['hak_akses' => $hak, 'created_at' => now()]);
                            }
                        }
                    });
                    $successRows++;
                } catch (Exception $e) {
                    $failedRows++;
                    $rowErrors[] = [
                        (string) $rowNumber,
                        $e->getMessage(),
                        $failedColumns ?: array_keys($mapping),
                        $dataRowNumber,
                        $safeErrorMessage,
                    ];
                }
            }
        } catch (Throwable $exception) {
            $fileError = match ($exception->getMessage()) {
                'ACCOUNT_IMPORT_FILE_EMPTY' => 'empty_file',
                'ACCOUNT_IMPORT_HEADERS_MISSING' => 'missing_headers',
                'ACCOUNT_IMPORT_FILE_LIMIT_EXCEEDED' => 'file_limit_exceeded',
                default => 'unreadable_file',
            };

            if (! in_array($fileError, ['empty_file', 'missing_headers'], true)) {
                report($exception);
            }
        }

        $status = 'selesai';
        if ($fileError) {
            $status = 'gagal';
        } elseif ($failedRows > 0 && $successRows > 0) {
            $status = 'sebagian';
        } elseif ($failedRows > 0 && $successRows == 0) {
            $status = 'gagal';
        }

        $log->update([
            'total_baris' => $totalRows,
            'baris_berhasil' => $successRows,
            'baris_gagal' => $failedRows,
            'status' => $status,
            'detail_error' => [$fileName, $fileError, $rowErrors, $fileDiagnostics],
            'selesai_at' => now(),
        ]);
    }

    public function importStatus(LogInput $logInput): JsonResponse
    {
        abort_unless($logInput->jenis_input === 'akun', 404);

        $rowErrors = is_array($logInput->detail_error[2] ?? null)
            ? $logInput->detail_error[2]
            : [];
        $formattedRowErrors = $this->formatAccountImportRowErrors($rowErrors);
        $message = match ($logInput->status) {
            'proses' => 'Import akun sedang diproses.',
            'selesai' => "Import selesai. Berhasil: {$logInput->baris_berhasil}, gagal: {$logInput->baris_gagal}.",
            'sebagian' => "Import selesai sebagian. Berhasil: {$logInput->baris_berhasil}, gagal: {$logInput->baris_gagal}.",
            default => 'Import akun gagal diproses. Buka detail untuk melihat penyebabnya.',
        };

        if (in_array($logInput->status, ['sebagian', 'gagal'], true) && $formattedRowErrors !== []) {
            $failureSummaries = array_map(
                static fn (array $error): string => "{$error[0]}: {$error[1]}",
                array_slice($formattedRowErrors, 0, 3),
            );
            $message .= ' '.implode(' ', $failureSummaries);

            if (count($formattedRowErrors) > count($failureSummaries)) {
                $message .= ' Dan '.(count($formattedRowErrors) - count($failureSummaries)).' kegagalan lainnya; buka Log Import untuk detail lengkap.';
            }
        } elseif ($logInput->status === 'gagal') {
            $message .= ' '.match ($logInput->detail_error[1] ?? null) {
                'empty_file' => 'File tidak berisi data.',
                'missing_headers' => 'Kolom username dan password tidak ditemukan pada baris judul.',
                'unreadable_file' => 'File tidak dapat dibaca atau formatnya tidak valid.',
                'file_limit_exceeded' => 'File melebihi batas 1.000 baris data, 32 kolom, atau 10 sheet.',
                'queue_failed' => 'File gagal masuk antrean. Silakan coba kembali.',
                'processing_failed' => 'Antrean gagal memproses file. Silakan coba import kembali.',
                default => 'Periksa format file dan gunakan template import akun.',
            };
        }

        return response()->json([
            'success' => true,
            'log_id' => $logInput->log_input_id,
            'file_name' => $logInput->nama_file,
            'status' => $logInput->status,
            'total_rows' => $logInput->total_baris,
            'success_rows' => $logInput->baris_berhasil,
            'failed_rows' => $logInput->baris_gagal,
            'message' => $message,
        ]);
    }

    public function showLogDetail(string $id): JsonResponse
    {
        $log = LogInput::findOrFail($id);
        $detailError = is_array($log->detail_error) ? $log->detail_error : [];

        if ($log->jenis_input === 'akun') {
            // ASVS V16.5.1: Do not expose raw import exceptions in browser responses.
            $detailError[1] = match ($detailError[1] ?? null) {
                'empty_file' => 'File tidak berisi data. Tambahkan baris judul dan data akun sebelum mengimpor.',
                'missing_headers' => 'Baris judul belum sesuai format. Pastikan kolom username dan password tersedia, atau unduh template import akun.',
                'unreadable_file' => 'File tidak dapat dibaca. Pastikan file tidak rusak dan berformat .csv, .xls, atau .xlsx.',
                'file_limit_exceeded' => 'File melebihi batas pemrosesan. Maksimal 1.000 baris data, 32 kolom, dan 10 sheet.',
                'queue_failed' => 'File gagal masuk antrean. Silakan coba kembali.',
                'processing_failed' => 'Antrean gagal memproses file. Silakan coba import kembali.',
                default => filled($detailError[1] ?? null)
                    ? 'File gagal diproses. Periksa formatnya dan gunakan template import akun.'
                    : null,
            };
            $detailError[2] = $this->formatAccountImportRowErrors(
                is_array($detailError[2] ?? null) ? $detailError[2] : [],
            );
            $detailError[3] = is_array($detailError[3] ?? null) ? [
                'row' => is_numeric($detailError[3]['row'] ?? null) ? (int) $detailError[3]['row'] : null,
                'columns' => array_values(array_filter(
                    is_array($detailError[3]['columns'] ?? null) ? $detailError[3]['columns'] : [],
                    static fn (mixed $column): bool => is_string($column) && $column !== '',
                )),
                'required_columns' => array_values(array_filter(
                    is_array($detailError[3]['required_columns'] ?? null) ? $detailError[3]['required_columns'] : [],
                    static fn (mixed $column): bool => is_string($column) && in_array($column, ['username', 'password'], true),
                )),
                'missing_columns' => array_values(array_filter(
                    is_array($detailError[3]['missing_columns'] ?? null) ? $detailError[3]['missing_columns'] : [],
                    static fn (mixed $column): bool => is_string($column) && in_array($column, ['username', 'password'], true),
                )),
            ] : null;
        } else {
            $detailError[1] = is_string($detailError[1] ?? null) ? $detailError[1] : null;
            $detailError[2] = is_array($detailError[2] ?? null) ? $detailError[2] : [];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'nama_file' => $log->nama_file,
                'status' => $log->status,
                'total_baris' => $log->total_baris,
                'baris_berhasil' => $log->baris_berhasil,
                'baris_gagal' => $log->baris_gagal,
                'detail_error' => $detailError,
            ],
        ]);
    }

    /**
     * @param  array<int, mixed>  $rowErrors
     * @return array<int, array{0: string, 1: string}>
     */
    private function formatAccountImportRowErrors(array $rowErrors): array
    {
        $allowedColumns = ['first_name', 'last_name', 'username', 'password', 'role', 'is_active', 'privileges'];

        return array_map(
            static function (mixed $error) use ($allowedColumns): array {
                $sourceLabel = is_array($error) && is_string($error[0] ?? null) ? $error[0] : '';
                preg_match('/\d+/', $sourceLabel, $matches);
                $excelRow = isset($matches[0]) ? (int) $matches[0] : null;
                $dataRow = is_array($error) && is_numeric($error[3] ?? null) ? (int) $error[3] : null;
                $rowLabel = $dataRow !== null && $excelRow !== null
                    ? "Data ke-{$dataRow} (baris Excel ke-{$excelRow})"
                    : ($excelRow !== null ? "Baris Excel ke-{$excelRow}" : 'Baris tidak diketahui');
                $storedColumns = is_array($error) && is_array($error[2] ?? null) ? $error[2] : [];
                $columns = array_values(array_filter(
                    $storedColumns,
                    static fn (mixed $column): bool => is_string($column) && in_array($column, $allowedColumns, true),
                ));
                $message = is_array($error) && is_string($error[4] ?? null)
                    ? $error[4]
                    : ($columns === []
                        ? 'Baris gagal diproses. Periksa format data dan username belum digunakan.'
                        : 'Data gagal diproses. Periksa kolom: '.implode(', ', $columns).'.');

                return [$rowLabel, $message];
            },
            $rowErrors,
        );
    }

    private function syncPrivilege(User $user, string $role, array $requestedPrivileges = [])
    {
        $user->privileges()->delete();
        if ($role === 'laboran' && empty($requestedPrivileges)) {
            $requestedPrivileges = ['manage_barang'];
        }

        if ($role === 'laboran' || ! empty($requestedPrivileges)) {
            $privilegeData = [];
            foreach ($requestedPrivileges as $hakAkses) {
                $privilegeData[] = ['hak_akses' => $hakAkses, 'created_at' => now()];
            }

            if (! empty($privilegeData)) {
                $user->privileges()->createMany($privilegeData);
            }
        }
    }

    private function authorizeAccountManagement(User $target): void
    {
        $actor = request()->user();

        abort_unless($actor?->canManageLaboranAccounts(), 403);

        if (! $actor->canManageAllAccounts()) {
            abort_unless($target->role === 'laboran' && $target->isNot($actor), 403);
        }
    }

    private function detectColumns(array $headers)
    {
        $map = [
            'first_name' => ['first_name', 'nama depan', 'nama awal'],
            'last_name' => ['last_name', 'nama belakang', 'nama akhir'],
            'username' => ['username', 'user', 'nama pengguna', 'email', 'login', 'akun', 'nama'],
            'password' => ['password', 'pass', 'kata sandi', 'pw', 'sand', 'passw'],
            'role' => ['role', 'peran', 'level', 'tipe', 'jabatan', 'roles'],
            'is_active' => ['is_active', 'active', 'aktif', 'status aktif', 'status'],
            'privileges' => ['hak_akses', 'hak akses', 'privilege', 'hak', 'akses'],
        ];

        $detected = [];
        foreach ($headers as $index => $header) {
            foreach ($map as $dbField => $keywords) {
                if (in_array($header, $keywords)) {
                    $detected[$dbField] = $index;
                    break;
                }
            }
        }

        return $detected;
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

    private function mapRowData(array $row, array $mapping)
    {
        $data = [];
        foreach ($mapping as $dbField => $index) {
            if (isset($row[$index])) {
                $val = trim($row[$index] ?? '');
                if ($dbField === 'is_active') {
                    $data[$dbField] = in_array(strtolower($val), ['1', 'true', 'yes', 'ya', 'aktif', 'y', 'on']);
                } elseif ($dbField === 'privileges') {
                    $data[$dbField] = array_map('trim', explode(',', $val));
                } else {
                    $data[$dbField] = $val;
                }
            }
        }
        $data['is_active'] = $data['is_active'] ?? true;
        $data['role'] = $data['role'] ?? 'peminjam';

        return $data;
    }
}
