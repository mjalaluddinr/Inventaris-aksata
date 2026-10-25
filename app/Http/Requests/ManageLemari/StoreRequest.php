<?php

namespace App\Http\Requests\ManageLemari;

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
        return [
            'ruangan_id' => ['required', 'string', 'exists:ruangan,ruangan_id'],
            'kode_lemari' => [
                'required',
                'string',
                'max:20',
                Rule::unique('lemari')->where(function ($query) {
                    return $query->where('ruangan_id', $this->ruangan_id)->whereNull('deleted_at');
                }),
            ],
            'nama_lemari' => ['required', 'string', 'max:100'],
            'keterangan' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'ruangan_id.required' => 'Ruangan wajib dipilih.',
            'ruangan_id.exists' => 'Ruangan tidak ditemukan.',
            'kode_lemari.required' => 'Kode lemari wajib diisi.',
            'kode_lemari.unique' => 'Kode lemari ini sudah digunakan di ruangan yang sama.',
            'nama_lemari.required' => 'Nama lemari wajib diisi.',
        ];
    }
}
