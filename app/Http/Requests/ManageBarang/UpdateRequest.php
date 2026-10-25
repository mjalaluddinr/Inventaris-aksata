<?php

namespace App\Http\Requests\ManageBarang;

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
        // ASVS 2.2.2/2.2.3: Validate location references and their room-cabinet relationship at the server boundary.
        $barangId = $this->route('barang')->barang_id;

        return [
            'barang_form' => ['required', Rule::in(['edit', 'status'])],
            'ruangan_id' => [
                'required_if:barang_form,edit',
                'nullable',
                'string',
                Rule::exists('ruangan', 'ruangan_id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'lemari_id' => ['sometimes', 'array', 'max:50'],
            'lemari_id.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('lemari', 'lemari_id')
                    ->where(fn ($query) => $query->whereNull('deleted_at')->where('ruangan_id', $this->input('ruangan_id'))),
            ],
            'kategori_id' => [
                'required',
                'string',
                Rule::exists('kategori', 'kategori_id')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'kode_barang' => [
                'required',
                'string',
                'max:30',
                Rule::unique('barang', 'kode_barang')->ignore($barangId, 'barang_id'),
            ],
            'nama_barang' => ['required', 'string', 'max:150'],
            'satuan' => ['required', 'string', 'max:20'],
            'stok_total' => ['required', 'integer', 'min:0'],
            'stok_tersedia' => ['required', 'integer', 'min:0', 'lte:stok_total'],
            'kondisi' => ['required', Rule::in(['baru', 'lama'])],
            'kelayakan' => ['required', Rule::in(['layak', 'tidak_layak'])],
            'spesifikasi' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'barang_form.required' => 'Jenis perubahan barang tidak valid.',
            'barang_form.in' => 'Jenis perubahan barang tidak valid.',
            'ruangan_id.required_if' => 'Ruangan wajib dipilih.',
            'ruangan_id.exists' => 'Ruangan tidak ditemukan atau sudah tidak aktif.',
            'lemari_id.array' => 'Pilihan lemari tidak valid.',
            'lemari_id.max' => 'Maksimal 50 lemari dapat dipilih.',
            'lemari_id.*.exists' => 'Lemari tidak ditemukan atau bukan bagian dari ruangan yang dipilih.',
            'lemari_id.*.distinct' => 'Lemari yang sama tidak dapat dipilih lebih dari sekali.',
            'kategori_id.required' => 'Kategori wajib dipilih.',
            'kategori_id.exists' => 'Kategori tidak ditemukan atau sudah tidak aktif.',
            'kode_barang.required' => 'Kode barang wajib diisi.',
            'kode_barang.unique' => 'Kode barang sudah digunakan.',
            'nama_barang.required' => 'Nama barang wajib diisi.',
            'satuan.required' => 'Satuan wajib diisi.',
            'stok_total.required' => 'Jumlah barang wajib diisi.',
            'stok_tersedia.required' => 'Stok tersedia wajib diisi.',
            'stok_tersedia.lte' => 'Stok tersedia tidak boleh melebihi jumlah barang.',
            'kondisi.in' => 'Kondisi barang tidak valid.',
            'kelayakan.in' => 'Status kelayakan tidak valid.',
        ];
    }
}
