<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use ZipArchive;

/**
 * Dumps the MySQL database into a zip file and deletes old backups. The
 * classroom PC is not always on at night, so the scheduler calls it every
 * hour with --if-missing and it runs once a day.
 */
#[Signature('lessonloop:backup {--if-missing : Only if there is no backup from today yet}')]
#[Description('Back up the database (mysqldump → zip) and delete old backups')]
class BackupDatabase extends Command
{
    public function handle(): int
    {
        $directory = (string) config('lessonloop.backup.directory');
        File::ensureDirectoryExists($directory);

        // File names use the classroom's local time; the app itself runs in UTC.
        $now = now()->setTimezone((string) config('lessonloop.backup.timezone'));
        $today = 'lessonloop-'.$now->format('Y-m-d');
        if ($this->option('if-missing') && File::glob($directory.DIRECTORY_SEPARATOR.$today.'_*.zip') !== []) {
            return self::SUCCESS;
        }

        $connection = config('database.connections.'.config('database.default'));
        if (($connection['driver'] ?? null) !== 'mysql' && ($connection['driver'] ?? null) !== 'mariadb') {
            $this->error('Only MySQL/MariaDB databases can be backed up.');

            return self::FAILURE;
        }

        $name = $today.'_'.$now->format('Hi');
        $sql = $directory.DIRECTORY_SEPARATOR.$name.'.sql';

        $result = Process::timeout(600)
            ->env(['MYSQL_PWD' => (string) ($connection['password'] ?? '')])
            ->run([
                (string) config('lessonloop.backup.mysqldump'),
                '--host='.$connection['host'],
                '--port='.$connection['port'],
                '--user='.$connection['username'],
                '--single-transaction',
                '--routines',
                '--default-character-set=utf8mb4',
                '--result-file='.$sql,
                $connection['database'],
            ]);

        if ($result->failed() || ! $this->waitForFile($sql)) {
            File::delete($sql);
            $this->error('mysqldump failed: '.trim($result->errorOutput() ?: $result->output()));

            return self::FAILURE;
        }

        $zipPath = $directory.DIRECTORY_SEPARATOR.$name.'.zip';
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($sql, $name.'.sql');
        $zip->close();
        File::delete($sql);

        $deleted = $this->prune($directory, (int) config('lessonloop.backup.keep_days'));

        $this->info("Backup saved: {$zipPath}".($deleted > 0 ? " ({$deleted} old removed)" : ''));

        return self::SUCCESS;
    }

    /**
     * Some mysqldump builds (OpenServer's among them) return before the
     * result file is written, so wait until it exists and stops growing.
     */
    private function waitForFile(string $path, int $timeoutSeconds = 300): bool
    {
        $deadline = microtime(true) + $timeoutSeconds;
        $lastSize = -1;

        while (microtime(true) < $deadline) {
            clearstatcache(true, $path);
            $size = File::exists($path) ? File::size($path) : -1;
            if ($size > 0 && $size === $lastSize) {
                return true;
            }
            $lastSize = $size;
            usleep(500_000);
        }

        return false;
    }

    private function prune(string $directory, int $keepDays): int
    {
        $before = now()->subDays($keepDays)->getTimestamp();
        $deleted = 0;

        foreach (File::glob($directory.DIRECTORY_SEPARATOR.'lessonloop-*.zip') as $file) {
            if (File::lastModified($file) < $before) {
                File::delete($file);
                $deleted++;
            }
        }

        return $deleted;
    }
}
