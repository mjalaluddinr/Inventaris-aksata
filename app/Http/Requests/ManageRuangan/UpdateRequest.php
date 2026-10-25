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
        $ruanganId = $this->route('ruangan')->ruangan_id;

        return [
            'kode_ruangan' => ['required', 'string', 'max:50', Rule::unique('ruangan', 'kode_ruangan')->whereNull('deleted_at')->ignore($ruanganId, 'ruangan_id')],
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
