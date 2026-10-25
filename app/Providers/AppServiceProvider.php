<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // ASVS V8.2/V8.3: Enforce privilege-based permissions at the server-side boundary.
        Gate::define('access-admin-panel', static fn (User $user): bool => $user->role === 'admin'
            || ($user->role === 'laboran'
                && ($user->canMelihat() || $user->canManajemen() || $user->canMeminjamkan())));
        Gate::define('view-inventory', static fn (User $user): bool => $user->role === 'admin'
            || (! $user->canMeminjamkan() && ($user->canMelihat() || $user->canManajemen())));
        Gate::define('manage-inventory', static fn (User $user): bool => $user->role === 'admin'
            || (! $user->canMeminjamkan() && $user->canManajemen()));
        Gate::define('process-loans', static fn (User $user): bool => $user->role === 'admin'
            || $user->canMeminjamkan());
        Gate::define('manage-accounts', static fn (User $user): bool => $user->role === 'admin');
    }
}
