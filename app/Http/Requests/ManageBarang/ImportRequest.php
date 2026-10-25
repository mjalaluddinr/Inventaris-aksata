<?php

namespace App\Http\Requests\ManageBarang;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use ZipArchive;

class ImportRequest extends FormRequest
{
    private const MAX_ARCHIVE_BYTES = 20 * 1024 * 1024;

    private const MAX_ARCHIVE_FILES = 100;

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
                'max:5120',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $extension = strtolower($value->getClientOriginalExtension());
                    $mimeType = $value->getMimeType();

                    // ASVS V5.2.1/V5.2.2/V5.2.3: Bound upload size and validate type/content before parsing.
                    $allowedMimeTypes = [
                        'csv' => ['application/csv', 'text/csv', 'text/plain'],
                        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage'],
                        'xlsx' => [
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'application/zip',
                        ],
                    ];

                    if (! isset($allowedMimeTypes[$extension]) || ! in_array($mimeType, $allowedMimeTypes[$extension], true)) {
                        $fail('File harus berupa CSV, XLS, atau XLSX yang valid.');

                        return;
                    }

                    if ($extension === 'xlsx' && ! $this->hasBoundedXlsxArchive($value)) {
                        $fail('File Excel rusak atau melebihi batas pemrosesan.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file CSV, XLS, atau XLSX untuk diimpor.',
            'file.max' => 'Ukuran file maksimal 5 MB.',
        ];
    }

    private function hasBoundedXlsxArchive(UploadedFile $file): bool
    {
        $path = $file->getRealPath();
        if ($path === false) {
            return false;
        }

        $archive = new ZipArchive;
        if ($archive->open($path) !== true) {
            return false;
        }

        try {
            if ($archive->numFiles === 0 || $archive->numFiles > self::MAX_ARCHIVE_FILES) {
                return false;
            }

            $totalUncompressedBytes = 0;
            $hasContentTypes = false;
            $hasWorkbook = false;
            $hasWorksheet = false;

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $entry = $archive->statIndex($index);
                if (! is_array($entry) || ! is_int($entry['size'] ?? null)) {
                    return false;
                }

                $totalUncompressedBytes += $entry['size'];
                if ($totalUncompressedBytes > self::MAX_ARCHIVE_BYTES) {
                    return false;
                }

                $name = $entry['name'] ?? '';
                $hasContentTypes = $hasContentTypes || $name === '[Content_Types].xml';
                $hasWorkbook = $hasWorkbook || $name === 'xl/workbook.xml';
                $hasWorksheet = $hasWorksheet || preg_match('#^xl/worksheets/sheet\d+\.xml$#', $name) === 1;
            }

            return $hasContentTypes && $hasWorkbook && $hasWorksheet;
        } finally {
            $archive->close();
        }
    }
}
