<?php

use App\Models\User;
use App\Services\RedisNotificationService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;

uses(LazilyRefreshDatabase::class);

it('renders admin notifications without a hero and displays their time in Jakarta timezone', function () {
    $admin = User::query()->create([
        'username' => 'notification-detail-admin',
        'first_name' => 'Notification',
        'last_name' => 'Admin',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);
    $notification = [
        'id' => 'notification-timezone',
        'message' => 'Peminjaman sudah dikonfirmasi.',
        'created_at' => '2026-11-12T16:11:00+00:00',
        'read' => true,
    ];

    $this->mock(RedisNotificationService::class, function (MockInterface $mock) use ($admin, $notification): void {
        $mock->shouldReceive('readAndClear')->once()->with($admin)->andReturn([]);
        $mock->shouldReceive('activeNotifications')->once()->with($admin)->andReturn([$notification]);
        $mock->shouldReceive('sanitizeNotificationMessage')
            ->once()
            ->with($notification['message'])
            ->andReturn($notification['message']);
    });

    $this->actingAs($admin)
        ->get(route('admin.notifications.detail'))
        ->assertOk()
        ->assertSeeText('12 Nov 2026')
        ->assertSeeText('23:11')
        ->assertSeeText('Admin')
        ->assertSeeText('Detail Notifications')
        ->assertSee('datetime="2026-11-12T23:11:00+07:00"', false)
        ->assertDontSeeText('Pantau seluruh pembaruan aktivitas dan transaksi.');
});

it('redirects administrators from the legacy notification URL to the admin detail slug', function () {
    $admin = User::query()->create([
        'username' => 'notification-detail-legacy',
        'first_name' => 'Notification',
        'last_name' => 'Admin',
        'password_hash' => 'test-password',
        'role' => 'admin',
        'is_active' => true,
    ]);

    $this->actingAs($admin)
        ->get(route('notifications.detail'))
        ->assertRedirectToRoute('admin.notifications.detail');
});

it('forbids borrowers from opening the admin notification detail route', function () {
    $borrower = User::query()->create([
        'username' => 'borrower-notification-detail',
        'first_name' => 'Borrower',
        'last_name' => 'Notification',
        'password_hash' => 'test-password',
        'role' => 'peminjam',
        'is_active' => true,
    ]);

    $this->actingAs($borrower)
        ->get(route('admin.notifications.detail'))
        ->assertForbidden();
});
