<?php

namespace Tests\Feature\Console;

use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;
use ZipArchive;

class BackupDatabaseTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = storage_path('framework/testing/backups');
        File::deleteDirectory($this->directory);
        config([
            'lessonloop.backup.directory' => $this->directory,
            'lessonloop.backup.mysqldump' => 'mysqldump',
            'database.default' => 'mysql',
            'database.connections.mysql.password' => 'secret',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_backup_zips_the_dump_without_putting_the_password_on_the_command_line(): void
    {
        Process::fake(fn (PendingProcess $process) => $this->writeDump($process));

        $this->artisan('lessonloop:backup')->assertSuccessful();

        $zips = File::glob($this->directory.'/lessonloop-'.now('Asia/Tashkent')->format('Y-m-d').'_*.zip');
        $this->assertCount(1, $zips);
        $zip = new ZipArchive;
        $zip->open($zips[0]);
        $this->assertStringContainsString('CREATE TABLE', (string) $zip->getFromIndex(0));
        $zip->close();
        $this->assertSame([], File::glob($this->directory.'/*.sql'));

        Process::assertRan(fn (PendingProcess $process): bool => $process->environment['MYSQL_PWD'] === 'secret'
            && ! in_array('secret', $process->command, true)
            && in_array('--single-transaction', $process->command, true));
    }

    public function test_if_missing_skips_when_today_is_already_backed_up_and_old_backups_are_deleted(): void
    {
        File::ensureDirectoryExists($this->directory);
        $old = $this->directory.'/lessonloop-2020-01-01_0900.zip';
        File::put($old, 'old');
        touch($old, now()->subDays(40)->getTimestamp());
        Process::fake(fn (PendingProcess $process) => $this->writeDump($process));

        $this->artisan('lessonloop:backup', ['--if-missing' => true])->assertSuccessful();
        $this->artisan('lessonloop:backup', ['--if-missing' => true])->assertSuccessful();

        Process::assertRanTimes(fn (): bool => true, 1);
        $this->assertFileDoesNotExist($old);
    }

    public function test_a_failed_dump_leaves_no_file_and_fails(): void
    {
        Process::fake(fn () => Process::result(errorOutput: 'Access denied', exitCode: 2));

        $this->artisan('lessonloop:backup')->assertFailed();

        $this->assertSame([], File::glob($this->directory.'/*'));
    }

    private function writeDump(PendingProcess $process): mixed
    {
        foreach ($process->command as $argument) {
            if (str_starts_with($argument, '--result-file=')) {
                File::put(substr($argument, strlen('--result-file=')), "CREATE TABLE `users` (id int);\n");
            }
        }

        return Process::result();
    }
}
