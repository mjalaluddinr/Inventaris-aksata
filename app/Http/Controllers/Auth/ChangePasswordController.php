<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ChangePasswordController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User|null $user */
        $user = Auth::user();
        abort_unless(
            $user instanceof User
                && (string) $request->session()->get('password_change_reauth_user_id') === (string) $user->getAuthIdentifier(),
            403,
        );

        return view('pages.auth.change-password');
    }

    public function update(ChangePasswordRequest $request): RedirectResponse
    {
        /** @var User $user */
        $user = Auth::user();

        $user->password_hash = $request->new_password;
        $user->must_change_password = false;
        $user->save();
        $request->session()->forget('password_change_reauth_user_id');
        $request->session()->regenerate();

        return redirect()->route('dashboard')
            ->with('success', 'Password berhasil diubah. Silahkan gunakan password baru Anda.');
    }
}
