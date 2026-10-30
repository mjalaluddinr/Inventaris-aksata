<?php

namespace App\Http\Requests\ManageBarang;

use Illuminate\Foundation\Http\FormRequest;

class ImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file' => [
                'bail',
                'required',
                'file',
                'mimes:csv,txt,xls,xlsx',
                'extensions:csv,xls,xlsx',
                'max:5120', // Maksimal 5 MB untuk file Excel
            ],
            'images_zip' => [
                'nullable',
                'file',
                'mimes:zip',
                'extensions:zip',
                'max:20480', // Maksimal 20 MB untuk arsip ZIP gambar
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file CSV, XLS, atau XLSX untuk diimpor.',
            'file.max' => 'Ukuran file Excel maksimal 5 MB.',
            'images_zip.mimes' => 'Arsip gambar harus berformat ZIP.',
            'images_zip.max' => 'Ukuran file ZIP gambar maksimal 20 MB.',
        ];
    }
}
