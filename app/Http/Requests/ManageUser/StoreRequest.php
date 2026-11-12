<?php

namespace App\Http\Requests\ManageUser;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canManageLaboranAccounts() ?? false;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'username' => ['required', 'string', 'max:50', Rule::unique('akun', 'username')->whereNull('deleted_at')],
            'password' => 'required|string|min:6',
            'role' => ['required', Rule::in($this->user()?->canManageAllAccounts()
                ? ['admin', 'laboran', 'peminjam']
                : ['laboran'])],
            'is_active' => 'nullable|boolean',
            'privileges' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    // ✅ VALIDASI: Wajib punya hak akses jika laboran
                    if ($this->role === 'laboran' && (empty($value) || count($value) === 0)) {
                        $fail('Role Laboran wajib memiliki minimal 1 hak akses.');
                    }
                },
            ],
            'privileges.*' => ['string', Rule::in(['melihat', 'meminjamkan', 'manajemen'])],
        ];
    }
}
