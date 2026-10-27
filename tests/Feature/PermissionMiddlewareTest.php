<?php

use App\Models\Privilege;
use App\Models\User;
use Illuminate\Support\Facades\Route;

function laboranWithPermissions(string ...$permissions): User
{
    $user = new User(['role' => 'laboran']);
    $user->setAttribute('akun_id', 'test-laboran');
    $user->setRelation(
        'privileges',
        collect(array_map(
            static fn (string $permission): Privilege => new Privilege(['hak_akses' => $permission]),
            $permissions,
        )),
    );

    return $user;
}

it('allows a laboran manager through the account management middleware', function () {
    Route::get('/test-account-management', fn () => response('allowed'))
        ->middleware(['auth', 'permission:accounts.manage']);

    $this->actingAs(laboranWithPermissions('manajemen', 'meminjamkan'))
        ->get('/test-account-management')
        ->assertOk()
        ->assertSeeText('allowed');
});

it('forbids a laboran without management privilege from account management', function () {
    Route::get('/test-account-management', fn () => response('allowed'))
        ->middleware(['auth', 'permission:accounts.manage']);

    $this->actingAs(laboranWithPermissions('melihat'))
        ->get('/test-account-management')
        ->assertForbidden();
});

it('keeps account imports restricted to administrators', function () {
    $this->actingAs(laboranWithPermissions('manajemen', 'meminjamkan'))
        ->post('/admin/akun/import')
        ->assertForbidden();
});
