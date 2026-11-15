<?php

use App\Models\Lemari;
use App\Models\Ruangan;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

it('shows a required manual code field in the cabinet form', function () {
    $admin = User::query()->create([
        'username' => 'admin-cabinet-code-form',
        'first_name' => 'Admin',
        'last_name' => 'Form Lemari',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.rak.index'))
        ->assertOk()
        ->assertSeeText('Kode Lemari')
        ->assertSee('name="kode_lemari"', false)
        ->assertSee('name="kode_lemari" x-model="currentLemari.kode_lemari" required', false);
});

it('creates a cabinet with its manually entered code', function () {
    $admin = User::query()->create([
        'username' => 'admin-cabinet-manual-code',
        'first_name' => 'Admin',
        'last_name' => 'Lemari',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'ROOM-MANUAL',
        'nama_ruangan' => 'Ruang Manual',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.rak.store'), [
            'ruangan_id' => $room->ruangan_id,
            'kode_lemari' => 'CAB-MANUAL',
            'nama_lemari' => 'Lemari Manual',
        ])
        ->assertRedirectToRoute('admin.rak.index');

    $this->assertDatabaseHas('lemari', [
        'ruangan_id' => $room->ruangan_id,
        'kode_lemari' => 'CAB-MANUAL',
        'nama_lemari' => 'Lemari Manual',
    ]);
});

it('requires a cabinet code when creating a cabinet', function () {
    $admin = User::query()->create([
        'username' => 'admin-cabinet-code-validation',
        'first_name' => 'Admin',
        'last_name' => 'Validasi Lemari',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'ROOM-DUPLICATE',
        'nama_ruangan' => 'Ruang Duplikat',
    ]);
    $this->actingAs($admin)
        ->post(route('admin.rak.store'), [
            'ruangan_id' => $room->ruangan_id,
            'nama_lemari' => 'Lemari Baru',
        ])
        ->assertRedirectBackWithErrors([
            'kode_lemari' => 'Kode lemari wajib diisi.',
        ]);
});

it('rejects a cabinet code already used in the same room', function () {
    $admin = User::query()->create([
        'username' => 'admin-cabinet-code-duplicate',
        'first_name' => 'Admin',
        'last_name' => 'Kode Ganda',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'ROOM-DUPLICATE',
        'nama_ruangan' => 'Ruang Duplikat',
    ]);
    Lemari::query()->create([
        'ruangan_id' => $room->ruangan_id,
        'kode_lemari' => 'CAB-USED',
        'nama_lemari' => 'Lemari Lama',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.rak.store'), [
            'ruangan_id' => $room->ruangan_id,
            'kode_lemari' => 'CAB-USED',
            'nama_lemari' => 'Lemari Baru',
        ])
        ->assertRedirectBackWithErrors([
            'kode_lemari' => 'Kode lemari sudah digunakan pada ruangan ini.',
        ]);
});

it('allows the same cabinet code in a different room', function () {
    $admin = User::query()->create([
        'username' => 'admin-cabinet-code-update',
        'first_name' => 'Admin',
        'last_name' => 'Update Lemari',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $firstRoom = Ruangan::query()->create([
        'kode_ruangan' => 'ROOM-FIRST',
        'nama_ruangan' => 'Ruang Pertama',
    ]);
    $secondRoom = Ruangan::query()->create([
        'kode_ruangan' => 'ROOM-SECOND',
        'nama_ruangan' => 'Ruang Kedua',
    ]);
    Lemari::query()->create([
        'ruangan_id' => $firstRoom->ruangan_id,
        'kode_lemari' => 'CAB-SHARED',
        'nama_lemari' => 'Lemari Pertama',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.rak.store'), [
            'ruangan_id' => $secondRoom->ruangan_id,
            'kode_lemari' => 'CAB-SHARED',
            'nama_lemari' => 'Lemari Kedua',
        ])
        ->assertRedirectToRoute('admin.rak.index');

    $this->assertDatabaseHas('lemari', [
        'ruangan_id' => $secondRoom->ruangan_id,
        'kode_lemari' => 'CAB-SHARED',
        'nama_lemari' => 'Lemari Kedua',
    ]);
});

it('allows a cabinet to keep its manually assigned code during an update', function () {
    $admin = User::query()->create([
        'username' => 'admin-cabinet-code-update',
        'first_name' => 'Admin',
        'last_name' => 'Update Lemari',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $room = Ruangan::query()->create([
        'kode_ruangan' => 'ROOM-UPDATE',
        'nama_ruangan' => 'Ruang Update',
    ]);
    $cabinet = Lemari::query()->create([
        'ruangan_id' => $room->ruangan_id,
        'kode_lemari' => 'CAB-UPDATE',
        'nama_lemari' => 'Lemari Sebelum',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.rak.update', $cabinet), [
            'ruangan_id' => $room->ruangan_id,
            'kode_lemari' => 'CAB-UPDATE',
            'nama_lemari' => 'Lemari Sesudah',
        ])
        ->assertRedirectToRoute('admin.rak.index');

    $this->assertDatabaseHas('lemari', [
        'lemari_id' => $cabinet->lemari_id,
        'kode_lemari' => 'CAB-UPDATE',
        'nama_lemari' => 'Lemari Sesudah',
    ]);
});
