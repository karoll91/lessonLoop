<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Empty spreadsheet with the expected columns and one sample row.
 */
class ImportTemplateExport implements FromArray, ShouldAutoSize, WithHeadings
{
    public const STUDENTS = 'students';

    public const QUESTIONS = 'questions';

    public function __construct(private string $type) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return $this->type === self::STUDENTS
            ? ['F.I.Sh.', 'Telefon']
            : ['Savol', "To'g'ri javob", 'Qiyinlik', 'A', 'B', 'C', 'D', "To'g'ri variant"];
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function array(): array
    {
        return $this->type === self::STUDENTS
            ? [['Akmal Karimov', '+998 90 123 45 67']]
            : [
                ["Jarayon va oqim o'rtasidagi farq nimada?", "Jarayon o'z xotirasiga ega, oqim jarayon xotirasini bo'lishadi.", "o'rta", '', '', '', '', ''],
                ['Qaysi chaqiruv yangi jarayon yaratadi?', '', 'oson', 'exec()', 'wait()', 'fork()', 'kill()', 'C'],
            ];
    }
}
