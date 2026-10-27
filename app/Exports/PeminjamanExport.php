<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class PeminjamanExport implements FromCollection, WithHeadings
{
    public function __construct(private Collection $rows) {}

    public function collection(): Collection
    {
        // ASVS 1.2.10: neutralize spreadsheet formulas from user-controlled text fields.
        return $this->rows->map(static fn (array $row): array => array_map(
            static function (mixed $value): mixed {
                if (is_string($value) && preg_match('/\A[=+\-@\t\0]/', $value) === 1) {
                    return "'".$value;
                }

                return $value;
            },
            $row,
        ));
    }

    public function headings(): array
    {
        return [
            'Kode Peminjaman',
            'Peminjam',
            'Barang',
            'Jumlah',
            'Keperluan',
            'Status',
            'Tanggal Pengajuan',
            'Rencana Kembali',
        ];
    }
}
