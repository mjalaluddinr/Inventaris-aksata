<?php

use App\Models\Privilege;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(LazilyRefreshDatabase::class);

test('laboran access flags load privileges with one query', function () {
    $laboran = User::query()->create([
        'username' => 'laboran-performance',
        'first_name' => 'Laboran',
        'last_name' => 'Performance',
        'password_hash' => 'test-password',
        'role' => 'laboran',
        'is_active' => true,
    ]);

    Privilege::query()->create([
        'akun_id' => $laboran->getKey(),
        'hak_akses' => 'melihat',
    ]);
    Privilege::query()->create([
        'akun_id' => $laboran->getKey(),
        'hak_akses' => 'meminjamkan',
    ]);

    $privilegeQueryCount = 0;
    DB::listen(static function (QueryExecuted $query) use (&$privilegeQueryCount): void {
        if (str_contains(strtolower($query->sql), 'privilege')) {
            $privilegeQueryCount++;
        }
    });

    $accessFlags = $laboran->getAccessFlags();

    expect($accessFlags)->toBe([
        'can_melihat' => true,
        'can_manajemen' => false,
        'can_meminjamkan' => true,
        'is_laboran' => true,
    ]);
    expect($privilegeQueryCount)->toBe(1);
});
