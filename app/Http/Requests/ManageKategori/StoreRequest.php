<?php

namespace App\Http\Requests\ManageKategori;

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
        // ASVS 2.2.2: Validate category input at the trusted server boundary.
        return [
            'nama_kategori' => ['required', 'string', 'max:255', Rule::unique('kategori', 'nama_kategori')],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'nama_kategori.required' => 'Nama kategori wajib diisi.',
            'nama_kategori.unique' => 'Nama kategori sudah digunakan.',
        ];
    }
}
