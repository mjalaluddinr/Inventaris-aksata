<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        $allowed = match ($permission) {
            'admin-panel' => $user?->canAccessAdminPanel() ?? false,
            'inventory.view' => $user?->canViewInventory() ?? false,
            'inventory.manage' => $user?->canManageInventory() ?? false,
            'loans.process' => $user?->canProcessLoans() ?? false,
            'accounts.manage' => $user?->canManageLaboranAccounts() ?? false,
            'accounts.manage-all' => $user?->canManageAllAccounts() ?? false,
            default => throw new InvalidArgumentException("Unknown permission [{$permission}]."),
        };

        // ASVS V8.2/V8.3: Enforce authorization at the server-side route boundary.
        abort_unless($allowed, 403);

        return $next($request);
    }
}
