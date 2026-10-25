<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'current_password.required' => 'Password saat ini wajib diisi.',
            'new_password.required' => 'Password baru wajib diisi.',
            'new_password.min' => 'Password baru minimal harus 8 karakter.',
            'new_password.confirmed' => 'Konfirmasi password baru tidak cocok.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $user = Auth::user();

            // Cek apakah password saat ini benar (menggunakan kolom password_hash sesuai model Anda)
            if (! Hash::check($this->current_password, $user->password_hash)) {
                $validator->errors()->add('current_password', 'Password saat ini tidak sesuai.');
            }

            // Opsional: Mencegah user menggunakan password yang sama dengan sebelumnya
            if ($this->current_password === $this->new_password) {
                $validator->errors()->add('new_password', 'Password baru tidak boleh sama dengan password saat ini.');
            }
        });
    }
}
