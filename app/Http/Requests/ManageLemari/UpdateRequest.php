<?php

namespace App\Http\Requests\ManageLemari;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ruangan_id' => ['required', 'string', 'exists:ruangan,ruangan_id'],
            'nama_lemari' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'ruangan_id.required' => 'Ruangan wajib dipilih.',
            'ruangan_id.exists' => 'Ruangan tidak ditemukan.',
            'nama_lemari.required' => 'Nama lemari wajib diisi.',
        ];
    }
}
