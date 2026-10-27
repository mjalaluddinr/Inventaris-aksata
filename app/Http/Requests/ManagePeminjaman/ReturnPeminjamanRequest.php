<?php

namespace App\Http\Requests\ManagePeminjaman;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnPeminjamanRequest extends FormRequest
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
            // ASVS 2.2.1/2.2.2: constrain each returned condition to the persisted domain values.
            'quantities' => ['required', 'array', 'min:1'],
            'quantities.*' => ['required', 'integer', 'min:0'],
            'conditions' => ['required', 'array', 'min:1'],
            'conditions.*' => ['required', 'string', Rule::in(['baik', 'rusak_ringan', 'rusak_berat', 'hilang'])],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
