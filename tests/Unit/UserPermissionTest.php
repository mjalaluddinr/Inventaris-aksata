<?php

use App\Models\Privilege;
use App\Models\User;

it('allows laboran managers to manage inventory and laboran accounts', function () {
    $manager = new User(['role' => 'laboran']);
    $manager->setRelation('privileges', collect([
        new Privilege(['hak_akses' => 'manajemen']),
        new Privilege(['hak_akses' => 'meminjamkan']),
    ]));

    expect($manager->canManageInventory())->toBeTrue()
        ->and($manager->canManageLaboranAccounts())->toBeTrue()
        ->and($manager->canManageAllAccounts())->toBeFalse()
        ->and($manager->canViewInventory())->toBeTrue()
        ->and($manager->canProcessLoans())->toBeTrue();
});

it('does not grant management access to laboran with view-only privilege', function () {
    $viewer = new User(['role' => 'laboran']);
    $viewer->setRelation('privileges', collect([
        new Privilege(['hak_akses' => 'melihat']),
    ]));

    expect($viewer->canManageInventory())->toBeFalse()
        ->and($viewer->canManageLaboranAccounts())->toBeFalse()
        ->and($viewer->canViewInventory())->toBeTrue();
});

it('gives administrators access to all account management', function () {
    $admin = new User(['role' => 'admin']);
    $admin->setRelation('privileges', collect());

    expect($admin->canAccessAdminPanel())->toBeTrue()
        ->and($admin->canManageInventory())->toBeTrue()
        ->and($admin->canManageLaboranAccounts())->toBeTrue()
        ->and($admin->canManageAllAccounts())->toBeTrue();
});
