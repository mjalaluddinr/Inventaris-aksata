<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // ASVS V8.3.1: enforce user-portal access on the trusted server side.
        Gate::define('access-user-portal', static fn (User $user): bool => $user->role === 'peminjam'
            && $user->is_active);
    }
}
