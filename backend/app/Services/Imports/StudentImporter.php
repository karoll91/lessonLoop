<?php

namespace App\Services\Imports;

use App\Models\StudyGroup;
use Illuminate\Support\Facades\DB;

/**
 * Imports students into a group. Columns: full name, optional phone.
 * A header row is detected and skipped; names already in the group or
 * repeated in the file are reported as duplicates.
 */
class StudentImporter
{
    private const HEADER_PATTERN = '/^(f\\.?\\s*i\\.?\\s*sh\\.?|ism(\\s+familiya)?|familiya(\\s+ism)?|full[_ ]?name|name|talaba(lar)?|ф\\.?\\s*и\\.?\\s*о\\.?)$/iu';

    /**
     * @param  list<array{row: int, cells: list<string>}>  $rows
     */
    public function import(StudyGroup $group, array $rows, bool $dryRun = false): ImportReport
    {
        $report = new ImportReport($dryRun);

        if ($rows !== [] && preg_match(self::HEADER_PATTERN, $rows[0]['cells'][0] ?? '') === 1) {
            array_shift($rows);
        }

        $known = $group->students()->pluck('full_name')
            ->mapWithKeys(fn (string $name): array => [SheetReader::normalize($name) => true])
            ->all();

        foreach ($rows as ['row' => $row, 'cells' => $cells]) {
            $name = $cells[0] ?? '';
            $phone = $this->phone($cells[1] ?? '');

            if ($name === '') {
                $report->error($row, '', 'F.I.Sh. bo\'sh');

                continue;
            }

            $letterCount = preg_match_all('/\p{L}/u', $name);

            if ($letterCount < 3 || mb_strlen($name) > 255) {
                $report->error($row, $name, $letterCount < 3 ? 'Juda qisqa' : 'Juda uzun');

                continue;
            }

            $key = SheetReader::normalize($name);

            if (isset($known[$key])) {
                $report->duplicate($row, $name);

                continue;
            }

            $known[$key] = true;
            $report->accept($row, ['full_name' => $name, 'phone' => $phone]);
        }

        if (! $dryRun && $report->accepted() !== []) {
            DB::transaction(fn () => $group->students()->createMany(array_map(
                fn (array $item): array => ['full_name' => $item['full_name'], 'phone' => $item['phone']],
                $report->accepted(),
            )));
        }

        return $report;
    }

    /**
     * Spreadsheets store "+998 90 ..." as a number and drop the plus sign;
     * Uzbek numbers get it back.
     */
    private function phone(string $value): ?string
    {
        $value = str_replace(' ', '', $value);

        if ($value === '') {
            return null;
        }

        if (preg_match('/^998\d{9}$/', $value) === 1) {
            $value = '+'.$value;
        }

        return mb_substr($value, 0, 32);
    }
}
