<?php

namespace App\Http\Requests\ManageUser;

use Illuminate\Foundation\Http\FormRequest;

class ImportRequest extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'file' => [
                'required',
                'file',
                'max:5120', // Max 5MB
                function ($attribute, $value, $fail) {
                    $extension = strtolower($value->getClientOriginalExtension());
                    $mimeType = $value->getMimeType();

                    $allowedExtensions = ['csv', 'xls', 'xlsx'];
                    $allowedMimeTypes = [
                        'text/csv',
                        'application/csv',
                        'text/plain',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/octet-stream',
                    ];

                    if (! in_array($extension, $allowedExtensions)) {
                        $fail('Format file harus berakhiran .csv, .xls, atau .xlsx');

                        return;
                    }

                    if ($extension === 'csv' && ! in_array($mimeType, $allowedMimeTypes)) {
                        // Izinkan CSV meski MIME type aneh
                    } elseif (! in_array($mimeType, $allowedMimeTypes)) {
                        $fail('Tipe file tidak dikenali atau file rusak.');
                    }
                },
            ],
        ];
    }

    public function messages()
    {
        return [
            'file.required' => 'File wajib diupload.',
            'file.max' => 'Ukuran file maksimal 5MB.',
        ];
    }
}
