<?php

namespace App\Exports\Concerns;

use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Adds the shared professional formatting to a records sheet:
 * title rows, a bold green frozen header, and thin borders on the data.
 */
trait FormatsRecordSheet
{
    /**
     * Inserts the 5 title rows and styles the header. Returns the header row index.
     * (WithHeadings writes the header at row 1 and data from row 2; after inserting
     * 5 rows the header sits at row 6.)
     */
    protected function decorate(Worksheet $sheet, string $lastCol, string $subtitle, string $rangeLabel): int
    {
        $sheet->insertNewRowBefore(1, 5);

        $sheet->setCellValue('A1', 'ISABELA STATE UNIVERSITY — Supply Management System');
        $sheet->setCellValue('A2', $subtitle);
        $sheet->setCellValue('A3', $rangeLabel);
        $sheet->setCellValue('A4', 'Generated: ' . now()->format('M d, Y g:i A'));

        foreach (['1', '2', '3', '4'] as $r) {
            $sheet->mergeCells("A{$r}:{$lastCol}{$r}");
        }
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1B5E20');
        $sheet->getStyle('A2')->getFont()->setBold(true)->setSize(12);
        $sheet->getStyle("A3:{$lastCol}4")->getFont()->setSize(10)->getColor()->setRGB('6B7280');

        $headerRow = 6;
        $sheet->getStyle("A{$headerRow}:{$lastCol}{$headerRow}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2E7D32']],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_LEFT],
        ]);
        $sheet->getRowDimension($headerRow)->setRowHeight(22);

        // Freeze everything above the first data row so the header stays visible.
        $sheet->freezePane('A' . ($headerRow + 1));

        $lastRow = $sheet->getHighestRow();
        if ($lastRow >= $headerRow) {
            $sheet->getStyle("A{$headerRow}:{$lastCol}{$lastRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
        }

        return $headerRow;
    }
}
