<?php

namespace App\Http\Requests\ManageToolbox;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // ASVS 2.2.1/2.2.2: Validate toolbox fields at the trusted request boundary.
        return [
            'nama_toolbox' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:255'],
            'return_to' => ['nullable', Rule::in(['barang', 'toolbox'])],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_toolbox.required' => 'Nama wadah wajib diisi.',
        ];
    }
}
