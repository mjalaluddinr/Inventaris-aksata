<?php

namespace App\Http\Requests\ManageUser;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $actor = $this->user();
        $target = $this->route('user');

        return $actor?->canManageLaboranAccounts()
            && ($actor->canManageAllAccounts()
                || ($target instanceof User && $target->role === 'laboran' && $target->isNot($actor)));
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
                Rule::unique('akun', 'username')->ignore(
                    $this->route('user')->akun_id,
                    'akun_id',
                )->whereNull('deleted_at'),
            ],
            'password' => 'nullable|string|min:6',
            'role' => ['required', Rule::in($this->user()?->canManageAllAccounts()
                ? ['admin', 'laboran', 'peminjam']
                : ['laboran'])],
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
            'privileges.*' => ['string', Rule::in(['melihat', 'meminjamkan', 'manajemen'])],
        ];
    }
}
