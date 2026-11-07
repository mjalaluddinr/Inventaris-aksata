<?php

namespace App\Http\Requests\ManageToolbox;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // ASVS V2.2.2: Validate toolbox updates at the trusted request boundary.
        return [
            'nama_toolbox' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_toolbox.required' => 'Nama wadah wajib diisi.',
        ];
    }
}
