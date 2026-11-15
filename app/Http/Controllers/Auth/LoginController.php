<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function index()
    {
        return view('pages.auth.login');
    }

    public function login(LoginRequest $request)
    {
        $user = User::query()
            ->where('username', $request->username)
            ->get()
            ->first(
                // ASVS 2.2.1: Preserve exact username matching even with a case-insensitive database collation.
                fn (User $candidate): bool => hash_equals($candidate->username, $request->username),
            );

        if (! $user || ! Hash::check($request->password, $user->password_hash)) {
            return back()->withErrors(['username' => 'Username atau password salah.'])->withInput($request->only('username'));
        }

        if (! $user->is_active) {
            return back()->withErrors(['username' => 'Akun Anda tidak aktif. Hubungi administrator.'])->withInput($request->only('username'));
        }

        // Validasi Role (Placeholder redirect sesuai request)
        $allowedRoles = ['admin', 'laboran', 'peminjam'];
        if (! in_array($user->role, $allowedRoles, true)) {
            return redirect()->route('login')->withErrors(['role' => 'Role Anda tidak memiliki akses.'])->withInput($request->only('username'));
        }

        // Update last login
        $user->last_login_at = Carbon::now();
        $user->save();

        // Login user
        Auth::login($user);
        $request->session()->regenerate();

        // ==========================================
        // SESSION "GACOR" : Simpan flag BOOLEAN
        // ==========================================
        $accessFlags = $user->getAccessFlags();

        $request->session()->put('user_id', $user->akun_id);
        $request->session()->put('user_role', $user->role);
        $request->session()->put('username', $user->username);

        // Inject boolean flags langsung ke session root
        $request->session()->put('can_melihat', $accessFlags['can_melihat']);
        $request->session()->put('can_manajemen', $accessFlags['can_manajemen']);
        $request->session()->put('can_meminjamkan', $accessFlags['can_meminjamkan']);
        $request->session()->put('is_laboran', $accessFlags['is_laboran']);

        $request->session()->forget('password_change_reauth_user_id');

        // Redirect logic
        if ($user->must_change_password) {
            $request->session()->put('password_change_reauth_user_id', $user->getAuthIdentifier());

            return redirect()->route('password.change');
        }

        // ASVS V8.2: Direct loan-only staff to a queue protected by server-side permission middleware.
        if (
            $user->role === 'laboran'
            && $accessFlags['can_meminjamkan']
            && ! $accessFlags['can_melihat']
            && ! $accessFlags['can_manajemen']
        ) {
            return redirect()->route('admin.peminjaman')->with('success', 'Login berhasil!');
        }

        if (in_array($user->role, ['admin', 'laboran'], true)) {
            return redirect()->route('admin.dashboard')->with('success', 'Login berhasil!');
        }

        return redirect()->route('user.beranda')->with('success', 'Login berhasil!');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('success', 'Berhasil logout.');
    }
}
