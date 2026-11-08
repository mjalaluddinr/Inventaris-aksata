<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user instanceof User
            && (string) $this->session()->get('password_change_reauth_user_id') === (string) $user->getAuthIdentifier();
    }

    public function rules(): array
    {
        return [
            // ASVS 6.2.1: Enforce the minimum password length at the request boundary.
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'new_password.required' => 'Password baru wajib diisi.',
            'new_password.min' => 'Password baru minimal harus 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('new_password')) {
                return;
            }

            /** @var User|null $user */
            $user = Auth::user();

            if (! $user instanceof User) {
                return;
            }

            // ASVS 6.2.3: Login has just verified the temporary current credential before this change.
            if (Hash::check($this->new_password, $user->password_hash)) {
                $validator->errors()->add('new_password', 'Password baru harus berbeda dari password sementara.');
            }
        });
    }
}
