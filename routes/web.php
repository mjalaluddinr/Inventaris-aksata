<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LogMutasiController;
use App\Http\Controllers\Admin\ManageBarang;
use App\Http\Controllers\Admin\ManageKategori;
use App\Http\Controllers\Admin\ManageLemari;
use App\Http\Controllers\Admin\ManagePeminjaman;
use App\Http\Controllers\Admin\ManageRuangan;
use App\Http\Controllers\Admin\ManageToolbox;
use App\Http\Controllers\Admin\ManageUser;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\User\BerandaController;
use App\Http\Controllers\User\PeminjamanController;
use App\Http\Middleware\MustChangePassword;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.auth.login');
});
Route::get('/login', [LoginController::class, 'index'])->name('login.index');
Route::get('/forgot-password', [PasswordRecoveryController::class, 'create'])->name('password.request');
Route::post('/forgot-password', [PasswordRecoveryController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('password.request.store');
Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ASVS V8.2: Enforce authentication and allowed admin roles at the server-side route boundary.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission:admin-panel'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::redirect('/dashboard', '/admin', 302)->name('dashboard.legacy');
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/barang', [ManageBarang::class, 'index'])->name('barang');
        Route::get('/kategori', [ManageKategori::class, 'index'])->name('kategori');
        Route::get('/toolbox', [ManageToolbox::class, 'index'])->name('toolbox.index');

        Route::prefix('ruangan')->name('ruangan.')->group(function () {
            Route::get('/', [ManageRuangan::class, 'index'])->name('index');
            Route::get('/{ruangan}', [ManageRuangan::class, 'show'])->name('show');
        });

        Route::prefix('rak')->name('rak.')->group(function () {
            Route::get('/', [ManageLemari::class, 'index'])->name('index');
            Route::get('/by-ruangan/{ruanganId}', [ManageLemari::class, 'getByRuangan'])->name('by-ruangan');
        });
    });

    // ASVS V8.2.2/V8.3.1: Keep inventory changes behind server-side manage permission checks.
    Route::middleware('permission:inventory.manage')->group(function () {
        Route::post('/barang', [ManageBarang::class, 'store'])->name('barang.store');
        Route::post('/toolbox', [ManageToolbox::class, 'store'])->name('toolbox.store');
        Route::put('/toolbox/{toolbox}', [ManageToolbox::class, 'update'])->name('toolbox.update');
        Route::delete('/toolbox/{toolbox}', [ManageToolbox::class, 'destroy'])->name('toolbox.destroy');
        Route::post('/barang/import', [ManageBarang::class, 'import'])->name('barang.import');
        Route::get('/barang/import/{logInput}/status', [ManageBarang::class, 'importStatus'])
            ->name('barang.import.status');
        Route::put('/barang/{barang}', [ManageBarang::class, 'update'])->name('barang.update');
        Route::delete('/barang/{barang}', [ManageBarang::class, 'destroy'])->name('barang.destroy');
        Route::post('/kategori', [ManageKategori::class, 'store'])->name('kategori.store');
        Route::put('/kategori/{kategori}', [ManageKategori::class, 'update'])->name('kategori.update');
        Route::delete('/kategori/{kategori}', [ManageKategori::class, 'destroy'])->name('kategori.destroy');

        Route::prefix('ruangan')->name('ruangan.')->group(function () {
            Route::post('/', [ManageRuangan::class, 'store'])->name('store');
            Route::put('/{ruangan}', [ManageRuangan::class, 'update'])->name('update');
            Route::delete('/{ruangan}', [ManageRuangan::class, 'destroy'])->name('destroy');
        });

        Route::prefix('rak')->name('rak.')->group(function () {
            Route::post('/', [ManageLemari::class, 'store'])->name('store');
            Route::put('/{lemari}', [ManageLemari::class, 'update'])->name('update');
            Route::delete('/{lemari}', [ManageLemari::class, 'destroy'])->name('destroy');
        });
    });

    Route::middleware('permission:loans.process')->group(function () {
        Route::get('/peminjaman', [ManagePeminjaman::class, 'index'])->name('peminjaman');
        Route::get('/peminjaman/print', [ManagePeminjaman::class, 'printReport'])->name('peminjaman.print');
        Route::get('/peminjaman/export', [ManagePeminjaman::class, 'export'])->name('peminjaman.export');
        Route::post('/peminjaman/scan', [ManagePeminjaman::class, 'scan'])
            ->middleware('throttle:10,1')
            ->name('peminjaman.scan');
        Route::post('/peminjaman/{peminjaman}/confirm', [ManagePeminjaman::class, 'confirm'])
            ->name('peminjaman.confirm');
        Route::post('/peminjaman/{peminjaman}/reject', [ManagePeminjaman::class, 'reject'])
            ->name('peminjaman.reject');
        Route::post('/peminjaman/{peminjaman}/return', [ManagePeminjaman::class, 'returnItems'])
            ->name('peminjaman.return');
    });

    // ================= ROUTE AKUN (CRUD + IMPORT) =================
    Route::prefix('akun')->name('akun.')->middleware('permission:accounts.manage')->group(function () {
        Route::get('/', [ManageUser::class, 'index'])->name('index');
        Route::post('/', [ManageUser::class, 'store'])->name('store');
        Route::put('/{user}', [ManageUser::class, 'update'])->name('update');
        Route::delete('/{user}', [ManageUser::class, 'destroy'])->name('destroy');
        Route::get('/{user}/password', [ManageUser::class, 'editPassword'])->name('password.edit');
        Route::put('/{user}/password', [ManageUser::class, 'resetPassword'])->name('password.update');
        Route::post('/{user}/password-request/approve', [ManageUser::class, 'approvePasswordRecovery'])
            ->name('password-request.approve');
        Route::post('/{user}/password-request/reject', [ManageUser::class, 'rejectPasswordRecovery'])
            ->name('password-request.reject');

        Route::patch('/{user}/toggle-status', [ManageUser::class, 'toggleStatus'])->name('toggle-status');

        Route::middleware('permission:accounts.manage-all')->group(function () {
            Route::post('/import', [ManageUser::class, 'import'])->name('import');
            Route::get('/import/{logInput}/status', [ManageUser::class, 'importStatus'])
                ->name('import.status');
            Route::get('/log/{id}/detail', [ManageUser::class, 'showLogDetail'])->name('log.detail');
        });
    });

    Route::get('/log-import', function () {
        return view('pages.admin.log_import');
    })->middleware('permission:accounts.manage-all')->name('log_import');

    Route::get('/log-mutasi', [LogMutasiController::class, 'index'])
        ->middleware('permission:accounts.manage-all')
        ->name('log_mutasi');

    // ASVS V8.2/V8.3: only administrators may view stock history or register item usage.
    Route::middleware('permission:accounts.manage-all')->group(function () {
        Route::post('/barang/{barang}/pemakaian', [ManageBarang::class, 'recordUsage'])
            ->name('barang.pemakaian.store');
        Route::post('/barang/{barang}/pemakaian/{usage}/tarik', [ManageBarang::class, 'withdrawUsage'])
            ->name('barang.pemakaian.tarik');
    });
});

// ASVS V8.2.2/V8.3.1: protect borrower pages and actions at the server-side boundary.
Route::prefix('user')->name('user.')->middleware(['auth', 'can:access-user-portal'])->group(function () {
    Route::get('/beranda', BerandaController::class)->name('beranda');
    Route::get('/katalog', [BerandaController::class, 'catalog'])->name('catalog');
    Route::get('/ajukan-peminjaman', [BerandaController::class, 'apply'])->name('apply');
    Route::get('/riwayat', [BerandaController::class, 'history'])->name('history');

    Route::post('/peminjaman', [PeminjamanController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('peminjaman.store');
    Route::post('/peminjaman/{peminjamanId}/cancel', [PeminjamanController::class, 'cancel'])
        ->middleware('throttle:5,1')
        ->name('peminjaman.cancel');
});

Route::middleware(['auth', MustChangePassword::class])->group(function () {
    Route::get('/change-password', [ChangePasswordController::class, 'index'])->name('password.change');
    Route::put('/change-password', [ChangePasswordController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/notifications/count', [NotificationController::class, 'count'])
        ->name('notifications.count');
    Route::post('/notifications/read', [NotificationController::class, 'read'])
        ->name('notifications.read');
    Route::get('/notifications/detail', [NotificationController::class, 'index'])
        ->name('notifications.detail');

    Route::get('/dashboard', function (Request $request) {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return match ($user->role) {
            'admin' => redirect()->route('admin.dashboard'),
            'laboran' => $user->canProcessLoans() && ! $user->canViewInventory()
                ? redirect()->route('admin.peminjaman')
                : redirect()->route('admin.dashboard'),
            'user', 'dosen', 'peminjam' => redirect()->route('user.beranda'),
            default => abort(403),
        };
    })->name('dashboard');
});
