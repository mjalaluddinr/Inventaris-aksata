<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ManageUser\ImportRequest;
use App\Http\Requests\ManageUser\StoreRequest;
use App\Http\Requests\ManageUser\UpdateRequest;
use App\Models\LogInput;
use App\Models\User;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\ToArray;
use Maatwebsite\Excel\Facades\Excel;

class ManageUser extends Controller
{
    public function index(Request $request): View|string
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
            ->orderByDesc('must_change_password')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        /** @var View $renderedView */
        $renderedView = view('pages.admin.akun', compact('users', 'filters', 'passwordResetUser'));

        return $renderedView->fragmentIf($request->ajax(), 'account-results');
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

    public function destroy(User $user)
    {
        $this->authorizeAccountManagement($user);
        $user->privileges()->delete();
        $user->delete();

        return redirect()->route('admin.akun.index')
            ->with('success', "Akun {$user->username} berhasil dihapus.");
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

    public function import(ImportRequest $request)
    {
        $file = $request->file('file');
        $fileName = $file->getClientOriginalName();

        $log = LogInput::create([
            'jenis_input' => 'akun',
            'nama_file' => $fileName,
            'total_baris' => 0,
            'baris_berhasil' => 0,
            'baris_gagal' => 0,
            'status' => 'proses',
            'mulai_at' => now(),
        ]);

        $fileError = null;
        $rowErrors = [];
        $totalRows = 0;
        $successRows = 0;
        $failedRows = 0;
        $importCreatedAt = now();

        try {
            $dummyImport = new class implements ToArray
            {
                public function array(array $array): void {}
            };
            $spreadsheet = Excel::toArray($dummyImport, $file);
            $rows = $spreadsheet[0] ?? [];

            if (empty($rows)) {
                throw new Exception('File kosong atau tidak memiliki data.');
            }

            $mapping = null;
            $headerRowIndex = -1;
            for ($i = 0; $i < min(50, count($rows)); $i++) {
                $headers = array_map(fn ($h) => strtolower(trim($h ?? '')), $rows[$i]);
                $tempMapping = $this->detectColumns($headers);
                if (isset($tempMapping['username']) && isset($tempMapping['password'])) {
                    $mapping = $tempMapping;
                    $headerRowIndex = $i;
                    break;
                }
            }

            if (! $mapping) {
                throw new Exception("Kolom 'username' dan 'password' tidak ditemukan.");
            }

            $totalRows = count($rows) - ($headerRowIndex + 1);

            for ($i = $headerRowIndex + 1; $i < count($rows); $i++) {
                $rowNumber = $i + 1;
                $rowData = $rows[$i];

                if (empty(array_filter($rowData, fn ($v) => trim($v ?? '') !== ''))) {
                    $totalRows--;

                    continue;
                }

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
                        throw new Exception('Role laboran wajib memiliki minimal 1 hak akses di kolom privilege.');
                    }

                    $validator = Validator::make($data, [
                        'username' => ['required', 'string', 'max:50', Rule::unique('akun', 'username')->whereNull('deleted_at')],
                        'password' => 'required|string|min:6',
                        'role' => 'required|in:admin,laboran,peminjam',
                        'privileges' => ['nullable', 'array'],
                        'privileges.*' => ['string', Rule::in(['melihat', 'meminjamkan', 'manajemen'])],
                    ]);

                    if ($validator->fails()) {
                        throw new Exception($validator->errors()->first());
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
                    $rowErrors[] = ["baris ke-{$rowNumber}", $e->getMessage()];
                }
            }
        } catch (Exception $e) {
            $fileError = $e->getMessage();
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
            'detail_error' => [$fileName, $fileError, $rowErrors],
            'selesai_at' => now(),
        ]);

        return redirect()->route('admin.akun.index')->with([
            'success' => $fileError ? 'Import gagal. Periksa format file dan susunan kolom.' : "Import selesai. Berhasil: {$successRows}, Gagal: {$failedRows}.",
            'import_status' => $status,
            'import_log_id' => $log->log_input_id,
        ]);
    }

    public function showLogDetail($id)
    {
        $log = LogInput::findOrFail($id);
        $detailError = is_array($log->detail_error) ? $log->detail_error : [];

        // ASVS V16.5.1: Do not expose raw import exceptions in browser responses.
        $detailError[1] = filled($detailError[1] ?? null)
            ? 'File import gagal diproses. Periksa format file dan susunan kolom.'
            : null;
        $detailError[2] = array_map(
            static fn (mixed $error): array => [
                is_array($error) && is_string($error[0] ?? null) ? $error[0] : 'Baris tidak diketahui',
                'Baris gagal diproses. Periksa format data dan username belum digunakan.',
            ],
            is_array($detailError[2] ?? null) ? $detailError[2] : [],
        );

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
