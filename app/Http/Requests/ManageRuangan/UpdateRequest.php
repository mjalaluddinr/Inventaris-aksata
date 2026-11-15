<?php

namespace App\Http\Requests\ManageRuangan;

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
            'kode_ruangan' => [
                'required',
                'string',
                'max:20',
                Rule::unique('ruangan', 'kode_ruangan')->ignore(
                    $this->route('ruangan')->ruangan_id,
                    'ruangan_id',
                ),
            ],
            'nama_ruangan' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'kode_ruangan.required' => 'Kode ruangan wajib diisi.',
            'kode_ruangan.unique' => 'Kode ruangan sudah digunakan.',
            'nama_ruangan.required' => 'Nama ruangan wajib diisi.',
        ];
    }
}
