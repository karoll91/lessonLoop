<?php

namespace App\Services\Imports;

/**
 * Outcome of an import: what was (or, in a dry run, would be) created, which
 * rows were skipped as duplicates and which rows have errors.
 */
final class ImportReport
{
    /** @var list<array<string, mixed>> */
    private array $accepted = [];

    /** @var list<array{row: int, value: string}> */
    private array $duplicates = [];

    /** @var list<array{row: int, value: string, message: string}> */
    private array $errors = [];

    public function __construct(public readonly bool $dryRun) {}

    /**
     * @param  array<string, mixed>  $item
     */
    public function accept(int $row, array $item): void
    {
        $this->accepted[] = ['row' => $row] + $item;
    }

    public function duplicate(int $row, string $value): void
    {
        $this->duplicates[] = ['row' => $row, 'value' => $value];
    }

    public function error(int $row, string $value, string $message): void
    {
        $this->errors[] = ['row' => $row, 'value' => $value, 'message' => $message];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function accepted(): array
    {
        return $this->accepted;
    }

    /**
     * @return array{dry_run: bool, created_count: int, duplicate_count: int, error_count: int, created: list<array<string, mixed>>, duplicates: list<array{row: int, value: string}>, errors: list<array{row: int, value: string, message: string}>}
     */
    public function toArray(): array
    {
        return [
            'dry_run' => $this->dryRun,
            'created_count' => count($this->accepted),
            'duplicate_count' => count($this->duplicates),
            'error_count' => count($this->errors),
            'created' => $this->accepted,
            'duplicates' => $this->duplicates,
            'errors' => $this->errors,
        ];
    }
}
