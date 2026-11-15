<?php

namespace App\Http\Requests\ManageLemari;

use Illuminate\Database\Query\Builder as QueryBuilder;
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
                Rule::unique('lemari', 'kode_lemari')
                    ->where(fn (QueryBuilder $query) => $query->where('ruangan_id', $this->input('ruangan_id'))),
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
            'kode_lemari.unique' => 'Kode lemari sudah digunakan pada ruangan ini.',
            'nama_lemari.required' => 'Nama lemari wajib diisi.',
        ];
    }
}
