<?php

use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('creates a room with the code entered by the administrator', function () {
    $admin = User::query()->create([
        'username' => 'admin-room-code',
        'first_name' => 'Admin',
        'last_name' => 'Ruangan',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.ruangan.index'))
        ->assertOk()
        ->assertSee('Kode Ruangan')
        ->assertSee('name="kode_ruangan"', false);

    $this->actingAs($admin)
        ->post(route('admin.ruangan.store'), [
            'kode_ruangan' => 'LAB-MANUAL',
            'nama_ruangan' => 'Laboratorium Manual',
        ])
        ->assertRedirectToRoute('admin.ruangan.index');

    $this->assertDatabaseHas('ruangan', [
        'kode_ruangan' => 'LAB-MANUAL',
        'nama_ruangan' => 'Laboratorium Manual',
    ]);
});

it('requires a unique room code', function () {
    $admin = User::query()->create([
        'username' => 'admin-room-code-validation',
        'first_name' => 'Admin',
        'last_name' => 'Validasi Ruangan',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    Ruangan::query()->create([
        'kode_ruangan' => 'LAB-EXISTING',
        'nama_ruangan' => 'Laboratorium Lama',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.ruangan.store'), [
            'nama_ruangan' => 'Laboratorium Baru',
        ])
        ->assertRedirectBackWithErrors([
            'kode_ruangan' => 'Kode ruangan wajib diisi.',
        ]);

    $this->actingAs($admin)
        ->post(route('admin.ruangan.store'), [
            'kode_ruangan' => 'LAB-EXISTING',
            'nama_ruangan' => 'Laboratorium Baru',
        ])
        ->assertRedirectBackWithErrors([
            'kode_ruangan' => 'Kode ruangan sudah digunakan.',
        ]);
});
