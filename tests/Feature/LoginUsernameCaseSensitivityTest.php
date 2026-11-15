<?php

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

test('login rejects a username with different character casing', function (string $storedUsername, string $submittedUsername) {
    User::query()->create([
        'username' => $storedUsername,
        'first_name' => 'Login',
        'last_name' => 'Test',
        'password_hash' => 'correct-password',
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->from(route('login.index'))
        ->post(route('login'), [
            'username' => $submittedUsername,
            'password' => 'correct-password',
        ])
        ->assertRedirect(route('login.index'))
        ->assertSessionHasErrors(['username' => 'Username atau password salah.']);

    $this->assertGuest();
})->with([
    'lowercase username entered in uppercase' => ['admin', 'ADMIN'],
    'uppercase username entered in lowercase' => ['ADMIN', 'admin'],
]);

test('login accepts an exactly matching username regardless of its case', function (string $username) {
    $user = User::query()->create([
        'username' => $username,
        'first_name' => 'Login',
        'last_name' => 'Test',
        'password_hash' => 'correct-password',
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->from(route('login.index'))
        ->post(route('login'), [
            'username' => $username,
            'password' => 'correct-password',
        ])
        ->assertRedirectToRoute('admin.dashboard');

    $this->assertAuthenticatedAs($user);
})->with([
    'lowercase username' => 'admin',
    'uppercase username' => 'ADMIN',
]);
