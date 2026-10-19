<?php

namespace App\Services\Imports;

use App\Enums\Difficulty;
use App\Enums\QuestionType;
use App\Models\Topic;
use Illuminate\Support\Facades\DB;

/**
 * Imports questions into a topic.
 *
 * Spreadsheet columns: question | correct answer | difficulty | A | B | C | D | correct option.
 * When any of A-D is filled the row becomes a test question and the correct
 * option letter is required. Plain text gives one open question per line.
 */
class QuestionImporter
{
    private const HEADER_PATTERN = '/^(savol|savol matni|question|text|вопрос)$/iu';

    /**
     * @param  list<array{row: int, cells: list<string>}>  $rows
     */
    public function import(Topic $topic, array $rows, bool $dryRun = false): ImportReport
    {
        $report = new ImportReport($dryRun);

        if ($rows !== [] && preg_match(self::HEADER_PATTERN, $rows[0]['cells'][0] ?? '') === 1) {
            array_shift($rows);
        }

        $known = $topic->questions()->pluck('text')
            ->mapWithKeys(fn (string $text): array => [SheetReader::normalize($text) => true])
            ->all();

        foreach ($rows as ['row' => $row, 'cells' => $cells]) {
            $item = $this->parse($cells);

            if (is_string($item)) {
                $report->error($row, $cells[0] ?? '', $item);

                continue;
            }

            $key = SheetReader::normalize($item['text']);

            if (isset($known[$key])) {
                $report->duplicate($row, $item['text']);

                continue;
            }

            $known[$key] = true;
            $report->accept($row, $item);
        }

        if (! $dryRun && $report->accepted() !== []) {
            DB::transaction(function () use ($topic, $report): void {
                foreach ($report->accepted() as $item) {
                    $question = $topic->questions()->create([
                        'type' => $item['type'],
                        'text' => $item['text'],
                        'answer_text' => $item['answer_text'],
                        'difficulty' => $item['difficulty'],
                    ]);

                    $question->options()->createMany($item['options']);
                }
            });
        }

        return $report;
    }

    /**
     * @param  list<string>  $cells
     * @return array{type: string, text: string, answer_text: ?string, difficulty: string, options: list<array{label: string, text: string, is_correct: bool}>}|string
     */
    private function parse(array $cells): array|string
    {
        $text = $cells[0] ?? '';

        if ($text === '') {
            return 'Savol matni bo\'sh';
        }

        if (mb_strlen($text) > 2000) {
            return 'Savol matni juda uzun';
        }

        $difficulty = $this->difficulty($cells[2] ?? '');

        if ($difficulty === null) {
            return 'Qiyinlik noma\'lum (oson / o\'rta / qiyin)';
        }

        $options = [];
        foreach (['A', 'B', 'C', 'D'] as $offset => $label) {
            $optionText = $cells[3 + $offset] ?? '';

            if ($optionText !== '') {
                $options[] = ['label' => $label, 'text' => mb_substr($optionText, 0, 500), 'is_correct' => false];
            }
        }

        if ($options === []) {
            return [
                'type' => QuestionType::Open->value,
                'text' => $text,
                'answer_text' => ($cells[1] ?? '') !== '' ? $cells[1] : null,
                'difficulty' => $difficulty->value,
                'options' => [],
            ];
        }

        $correct = mb_strtoupper($cells[7] ?? '');
        $labels = array_column($options, 'label');

        if (count($options) < 2) {
            return 'Test savolida kamida 2 ta variant kerak';
        }

        if (! in_array($correct, $labels, true)) {
            return 'To\'g\'ri variant (A-D) ko\'rsatilmagan yoki bo\'sh variantga ishora qiladi';
        }

        foreach ($options as $index => $option) {
            $options[$index]['is_correct'] = $option['label'] === $correct;
        }

        return [
            'type' => QuestionType::Test->value,
            'text' => $text,
            'answer_text' => ($cells[1] ?? '') !== '' ? $cells[1] : null,
            'difficulty' => $difficulty->value,
            'options' => $options,
        ];
    }

    private function difficulty(string $value): ?Difficulty
    {
        return match (SheetReader::normalize($value)) {
            '', "o'rta", 'orta', 'medium', 'средний' => Difficulty::Medium,
            'oson', 'easy', 'лёгкий', 'легкий' => Difficulty::Easy,
            'qiyin', 'hard', 'сложный' => Difficulty::Hard,
            default => null,
        };
    }
}
