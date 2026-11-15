<?php

namespace App\Http\Requests\ManagePeminjaman;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RejectPeminjamanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $user->canProcessLoans();
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            // ASVS 2.2.1/2.2.2: bound and require the reason used to decide and audit a rejection.
            'reason' => ['required', 'string', 'max:255'],
            'tab' => ['nullable', 'in:pending,borrowed,history'],
        ];
    }
}
