<?php

use App\Http\Controllers\Admin\ManageBarang;
use App\Http\Controllers\Admin\ManageKategori;
use App\Http\Controllers\Admin\ManageLemari;
use App\Http\Controllers\Admin\ManagePeminjaman;
use App\Http\Controllers\Admin\ManageRuangan;
use App\Http\Controllers\Admin\ManageUser;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\User\PeminjamanController;
use App\Http\Middleware\MustChangePassword;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.auth.login');
});
Route::get('/login', [LoginController::class, 'index'])->name('login.index');
Route::get('/forgot-password', function () {
    return view('pages.auth.login', ['showForgotModal' => true]);
})->name('password.request');
Route::post('/login', [LoginController::class, 'login'])->name('login');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

// ASVS V8.2: Enforce authentication and allowed admin roles at the server-side route boundary.
Route::prefix('admin')->name('admin.')->middleware(['auth', 'permission:admin-panel'])->group(function () {
    Route::get('/', function () {
        return view('pages.admin.dashboard');
    })->name('dashboard');
    Route::redirect('/dashboard', '/admin', 302)->name('dashboard.legacy');
    Route::middleware('permission:inventory.view')->group(function () {
        Route::get('/barang', [ManageBarang::class, 'index'])->name('barang');
        Route::get('/kategori', [ManageKategori::class, 'index'])->name('kategori');

        Route::prefix('ruangan')->name('ruangan.')->group(function () {
            Route::get('/', [ManageRuangan::class, 'index'])->name('index');
        });

        Route::prefix('rak')->name('rak.')->group(function () {
            Route::get('/', [ManageLemari::class, 'index'])->name('index');
            Route::get('/by-ruangan/{ruanganId}', [ManageLemari::class, 'getByRuangan'])->name('by-ruangan');
        });
    });

    Route::middleware('permission:inventory.manage')->group(function () {
        Route::post('/barang', [ManageBarang::class, 'store'])->name('barang.store');
        Route::post('/barang/import', [ManageBarang::class, 'import'])->name('barang.import');
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

        Route::patch('/{user}/toggle-status', [ManageUser::class, 'toggleStatus'])->name('toggle-status');

        Route::middleware('permission:accounts.manage-all')->group(function () {
            Route::post('/import', [ManageUser::class, 'import'])->name('import');
            Route::get('/log/{id}/detail', [ManageUser::class, 'showLogDetail'])->name('log.detail');
        });
    });

    Route::get('/log-import', function () {
        return view('pages.admin.log_import');
    })->middleware('permission:accounts.manage-all')->name('log_import');

    Route::get('/log-mutasi', function () {
        return view('pages.admin.log_mutasi');
    })->middleware('permission:accounts.manage-all')->name('log_mutasi');
});

// ASVS V8.2.2/V8.3.1: protect borrower pages and actions at the server-side boundary.
Route::prefix('user')->name('user.')->middleware(['auth', 'can:access-user-portal'])->group(function () {
    Route::get('/beranda', function () {
        return view('pages.user.beranda');
    })->name('beranda');

    Route::post('/peminjaman', [PeminjamanController::class, 'store'])
        ->middleware('throttle:10,1')
        ->name('peminjaman.store');
    Route::post('/peminjaman/{peminjamanId}/pembatalan', [PeminjamanController::class, 'requestCancellation'])
        ->middleware('throttle:5,1')
        ->name('peminjaman.cancellation.store');
});

Route::middleware(['auth', MustChangePassword::class])->group(function () {
    Route::get('/change-password', [ChangePasswordController::class, 'index'])->name('password.change');
    Route::put('/change-password', [ChangePasswordController::class, 'update'])->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return match (auth()->user()->role) {
            'admin', 'laboran' => redirect()->route('admin.dashboard'),
            'user', 'dosen', 'peminjam' => redirect()->route('user.beranda'),
            default => abort(403),
        };
    })->name('dashboard');
});
