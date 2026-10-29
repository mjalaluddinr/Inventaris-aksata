<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Table;
use PhpOffice\PhpSpreadsheet\Worksheet\Table\TableStyle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PeminjamanExport implements FromCollection, ShouldAutoSize, WithEvents, WithHeadings, WithStyles
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

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'color' => ['rgb' => 'FFFFFF'],
                ],
                'fill' => [
                    'fillType' => 'solid',
                    'color' => ['rgb' => '007F70'],
                ],
                'alignment' => [
                    'vertical' => 'center',
                ],
            ],
        ];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => static function (AfterSheet $event): void {
                $sheet = $event->sheet->getDelegate();
                $lastRow = $sheet->getHighestDataRow();

                $sheet->freezePane('A2');

                if ($lastRow < 2) {
                    return;
                }

                $table = new Table('A1:H'.$lastRow, 'PeminjamanTable');
                $tableStyle = new TableStyle(TableStyle::TABLE_STYLE_MEDIUM4);
                $tableStyle->setShowRowStripes(true);
                $table->setStyle($tableStyle);
                $sheet->addTable($table);
            },
        ];
    }
}
