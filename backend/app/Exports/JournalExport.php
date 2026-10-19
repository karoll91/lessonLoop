<?php

namespace App\Exports;

use App\Services\JournalBuilder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;

/**
 * Journal as a sheet: student names down, lessons across, average last.
 * Absent is written as "K", not asked stays empty, and a score of 0 stays 0.
 */
class JournalExport implements FromArray, ShouldAutoSize, WithHeadings, WithStrictNullComparison
{
    /**
     * @param  array{lessons: list<array{id: int, sequence: int, date: ?string}>, rows: list<array{full_name: string, cells: list<array{status: string, score: ?int}>, average: ?int}>}  $journal
     */
    public function __construct(private array $journal) {}

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        $lessonHeadings = array_map(
            fn (array $lesson): string => $lesson['sequence'].'-dars'.($lesson['date'] !== null ? ' ('.date('d.m', strtotime($lesson['date'])).')' : ''),
            $this->journal['lessons'],
        );

        return ['F.I.Sh.', ...$lessonHeadings, "O'rtacha"];
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    public function array(): array
    {
        return array_map(fn (array $row): array => [
            $row['full_name'],
            ...array_map(fn (array $cell): int|string => match ($cell['status']) {
                JournalBuilder::CELL_GRADED, JournalBuilder::CELL_NO_ANSWER => (int) $cell['score'],
                JournalBuilder::CELL_ABSENT => 'K',
                default => '',
            }, $row['cells']),
            $row['average'] ?? '',
        ], $this->journal['rows']);
    }
}
