<?php

namespace App\Services\Imports;

use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Excel as ExcelType;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Reads the first sheet of an uploaded spreadsheet, or plain text, into
 * numbered rows of trimmed string cells. Empty rows are dropped.
 */
class SheetReader
{
    /**
     * @return list<array{row: int, cells: list<string>}>
     */
    public function fromFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'txt') {
            return $this->fromText((string) file_get_contents($file->getRealPath()));
        }

        $readerType = match ($extension) {
            'xls' => ExcelType::XLS,
            'csv' => ExcelType::CSV,
            default => ExcelType::XLSX,
        };

        $sheet = Excel::toCollection(null, $file, null, $readerType)->first() ?? collect();

        $rows = [];
        foreach ($sheet->values() as $index => $row) {
            $cells = $row->map(fn ($cell): string => $this->clean((string) $cell))->values()->all();

            if (implode('', $cells) !== '') {
                $rows[] = ['row' => $index + 1, 'cells' => $cells];
            }
        }

        return $rows;
    }

    /**
     * One value per line, for names or questions pasted from a document.
     *
     * @return list<array{row: int, cells: list<string>}>
     */
    public function fromText(string $text): array
    {
        $text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $index => $line) {
            $line = $this->clean($line);

            if ($line !== '') {
                $rows[] = ['row' => $index + 1, 'cells' => [$line]];
            }
        }

        return $rows;
    }

    /**
     * Comparable form of a name or text: lower case, single spaces, one apostrophe kind.
     */
    public static function normalize(string $value): string
    {
        $value = str_replace(['’', '‘', 'ʻ', 'ʼ', '`', '´'], "'", $value);

        return mb_strtolower(preg_replace('/\s+/u', ' ', trim($value)) ?? $value);
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
