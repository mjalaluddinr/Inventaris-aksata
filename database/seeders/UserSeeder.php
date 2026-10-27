<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['username' => 'admin'],
            [
                'first_name' => 'Admin',
                'last_name' => 'InvenTrack',
                // ASVS V6.2: Store the temporary password as a hash and require a change at first login.
                'password_hash' => Hash::make('12345678'),
                'role' => 'admin',
                'must_change_password' => false,
                'is_active' => true,
            ],
        );
    }
}
