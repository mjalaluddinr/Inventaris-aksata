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
            // ASVS 2.2.1/2.2.2: Validate uploads at the request boundary before storing or queueing them.
            'file' => [
                'bail',
                'required_without:files',
                'prohibits:files',
                'file',
                'mimes:csv,txt,xls,xlsx',
                'extensions:csv,xls,xlsx',
                'max:5120', // Maksimal 5 MB untuk file Excel
            ],
            'files' => ['nullable', 'array', 'min:1', 'max:10'],
            'files.*' => [
                'bail',
                'required',
                'file',
                'mimes:csv,txt,xls,xlsx',
                'extensions:csv,xls,xlsx',
                'max:5120',
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
            'file.required_without' => 'Pilih minimal satu file CSV, XLS, atau XLSX untuk diimpor.',
            'file.prohibits' => 'Kirim file import melalui satu pilihan saja.',
            'file.mimes' => 'File import harus berupa CSV, XLS, atau XLSX.',
            'file.extensions' => 'Ekstensi file import harus .csv, .xls, atau .xlsx.',
            'file.max' => 'Ukuran file Excel maksimal 5 MB.',
            'files.array' => 'Daftar file import tidak valid.',
            'files.min' => 'Pilih minimal satu file CSV, XLS, atau XLSX untuk diimpor.',
            'files.max' => 'Maksimal 10 file Excel dapat diimpor sekaligus.',
            'files.*.mimes' => 'Setiap file import harus berupa CSV, XLS, atau XLSX.',
            'files.*.file' => 'Setiap file import harus berupa file yang valid.',
            'files.*.extensions' => 'Ekstensi setiap file import harus .csv, .xls, atau .xlsx.',
            'files.*.max' => 'Ukuran setiap file Excel maksimal 5 MB.',
            'images_zip.file' => 'Arsip gambar yang dipilih tidak valid.',
            'images_zip.mimes' => 'Arsip gambar harus berformat ZIP.',
            'images_zip.extensions' => 'Ekstensi arsip gambar harus .zip.',
            'images_zip.max' => 'Ukuran file ZIP gambar maksimal 20 MB.',
        ];
    }
}
