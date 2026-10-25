<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MustChangePassword
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $user = Auth::user();

        // Jika user tidak login ATAU must_change_password bernilai false (0)
        if ($user->must_change_password == 0) {
            // Logout user untuk keamanan
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            // Fallback ke login dengan pesan yang diminta
            return redirect()->route('login')->withErrors([
                'username' => 'Silahkan konfirmasi ke admin sebelum merubah password.',
            ]);
        }

        return $next($request);
    }
}
