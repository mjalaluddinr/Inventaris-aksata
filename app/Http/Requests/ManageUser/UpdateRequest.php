<?php

namespace App\Http\Requests\ManageUser;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // ASVS 2.2.1, 2.2.2: Validate this security-sensitive choice at the trusted request boundary.
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'username' => [
                'required',
                'string',
                'max:50',
                Rule::unique('akun', 'username')->ignore($this->user->akun_id, 'akun_id')->whereNull('deleted_at'),
            ],
            'password' => 'nullable|string|min:6',
            'role' => 'required|in:admin,laboran,peminjam',
            'is_active' => 'nullable|boolean',
            'must_change_password' => 'nullable|boolean',
            'privileges' => [
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    if ($this->role === 'laboran' && (empty($value) || count($value) === 0)) {
                        $fail('Role Laboran wajib memiliki minimal 1 hak akses.');
                    }
                },
            ],
        ];
    }
}
