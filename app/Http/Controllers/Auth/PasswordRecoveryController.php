<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\RedisNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PasswordRecoveryController extends Controller
{
    public function __construct(private RedisNotificationService $notifications) {}

    public function create(): View
    {
        return view('pages.auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        // ASVS 2.2.1: Validate the username at the trusted request boundary.
        $validated = $request->validate([
            'username' => ['required', 'string', 'max:50'],
        ]);

        $user = User::query()
            ->where('username', $validated['username'])
            ->where('is_active', true)
            ->first();

        if ($user instanceof User) {
            $user->update(['must_change_password' => true]);
            $this->notifications->notifyAdministrators(
                "Permintaan ganti password dari akun {$user->username}.",
            );
        }

        // ASVS 16.5.1: Return the same response whether or not the username exists.
        return redirect()
            ->route('password.request')
            ->with('status', 'Jika username terdaftar, permintaan reset password akan dicatat. Silakan hubungi admin untuk mendapatkan password sementara setelah diproses.');
    }
}
