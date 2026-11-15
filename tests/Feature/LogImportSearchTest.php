<?php

use App\Models\LogInput;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('import log search includes text stored in error details', function () {
    $admin = User::query()->create([
        'username' => 'admin-import-search',
        'first_name' => 'Import',
        'last_name' => 'Admin',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);

    LogInput::query()->create([
        'jenis_input' => 'akun',
        'nama_file' => 'account-errors.xlsx',
        'total_baris' => 2,
        'baris_berhasil' => 1,
        'baris_gagal' => 1,
        'status' => 'sebagian',
        'detail_error' => ['account-errors.xlsx', null, [['row' => 2, 'message' => 'missing-email-column-token']]],
        'mulai_at' => now(),
    ]);
    LogInput::query()->create([
        'jenis_input' => 'barang',
        'nama_file' => 'inventory-ok.xlsx',
        'total_baris' => 1,
        'baris_berhasil' => 1,
        'baris_gagal' => 0,
        'status' => 'selesai',
        'detail_error' => null,
        'mulai_at' => now()->subMinute(),
    ]);

    $this->actingAs($admin)
        ->get(route('admin.log_import', ['q' => 'missing-email-column-token']))
        ->assertOk()
        ->assertSeeText('account-errors.xlsx')
        ->assertDontSeeText('inventory-ok.xlsx');
});

test('import log search returns an AJAX fragment without reloading the page', function () {
    $admin = User::query()->create([
        'username' => 'admin-import-search-ajax',
        'first_name' => 'Import',
        'last_name' => 'AJAX',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    LogInput::query()->create([
        'jenis_input' => 'barang',
        'nama_file' => 'ajax-search.xlsx',
        'total_baris' => 1,
        'baris_berhasil' => 1,
        'baris_gagal' => 0,
        'status' => 'selesai',
        'mulai_at' => now(),
    ]);

    $this->actingAs($admin)
        ->withHeaders([
            'Accept' => 'text/html',
            'X-Requested-With' => 'XMLHttpRequest',
        ])
        ->get(route('admin.log_import', ['search' => 'ajax-search']))
        ->assertOk()
        ->assertDontSee('<!DOCTYPE html>', false)
        ->assertSeeText('ajax-search.xlsx');
});

test('import log search rejects queries longer than the supported limit', function () {
    $admin = User::query()->create([
        'username' => 'admin-import-search-length',
        'first_name' => 'Import',
        'last_name' => 'Admin',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.log_import', ['q' => str_repeat('a', 101)]))
        ->assertSessionHasErrors('q');
});
