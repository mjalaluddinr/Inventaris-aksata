<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User; // Pastikan import ini ada
use Illuminate\Support\Facades\Auth;

class ChangePasswordController extends Controller
{
    public function index()
    {
        return view('pages.auth.change-password');
    }

    public function update(ChangePasswordRequest $request)
    {
        /** @var User $user */
        $user = Auth::user();

        // Update password
        $user->password_hash = $request->new_password;

        // Set must_change_password menjadi 0
        $user->must_change_password = false;
        $user->save();

        return redirect()->route('dashboard')
            ->with('success', 'Password berhasil diubah. Silahkan gunakan password baru Anda.');
    }
}
