<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Full MySQL/MariaDB backup with `mysqldump`, written to storage/app/backups as
 * `<prefix>-<database>-<YYYY-MM-DD_HH-MM-SS>.sql.gz`. Used before destructive
 * maintenance commands. Credentials go through a temporary private option file
 * (never on the command line) that is deleted afterwards. Throws when the dump
 * fails or is incomplete, so callers can refuse to continue.
 */
class DatabaseBackupService
{
    /**
     * @return string Absolute path of the written .sql.gz file.
     */
    public function backup(string $prefix, string $mysqldump = 'mysqldump'): string
    {
        $connection = config('database.default');
        $cfg = config("database.connections.{$connection}");
        if (! in_array($cfg['driver'] ?? null, ['mysql', 'mariadb'], true)) {
            throw new RuntimeException("Backup needs a MySQL/MariaDB connection (current: {$connection}).");
        }

        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        $database = (string) $cfg['database'];
        $base = $dir.DIRECTORY_SEPARATOR.$prefix.'-'.$database.'-'.now()->format('Y-m-d_H-i-s');
        $sqlFile = $base.'.sql';
        $gzFile = $base.'.sql.gz';

        // Credentials via a private option file — keeps the password out of the
        // process list and shell history.
        $optionFile = tempnam(sys_get_temp_dir(), 'shfdump');
        file_put_contents($optionFile, implode("\n", [
            '[client]',
            'user="'.addcslashes((string) $cfg['username'], '"\\').'"',
            'password="'.addcslashes((string) $cfg['password'], '"\\').'"',
            'host="'.addcslashes((string) $cfg['host'], '"\\').'"',
            'port='.(int) $cfg['port'],
        ])."\n");
        @chmod($optionFile, 0600);

        try {
            $result = Process::timeout(3600)->run([
                $mysqldump,
                '--defaults-extra-file='.$optionFile, // must be the first option
                '--single-transaction',
                '--routines',
                '--triggers',
                '--no-tablespaces',
                '--result-file='.$sqlFile,
                $database,
            ]);
        } finally {
            @unlink($optionFile);
        }

        if (! $result->successful()) {
            @unlink($sqlFile);
            throw new RuntimeException('mysqldump failed: '.trim($result->errorOutput() ?: $result->output()));
        }

        // A complete dump ends with mysqldump's "Dump completed" trailer.
        $size = is_file($sqlFile) ? filesize($sqlFile) : 0;
        if ($size === 0 || ! $this->endsWithDumpCompleted($sqlFile)) {
            @unlink($sqlFile);
            throw new RuntimeException('mysqldump produced an empty or incomplete backup.');
        }

        $this->gzip($sqlFile, $gzFile);
        @unlink($sqlFile);

        return $gzFile;
    }

    private function endsWithDumpCompleted(string $file): bool
    {
        $handle = fopen($file, 'rb');
        fseek($handle, max(0, filesize($file) - 512));
        $tail = (string) stream_get_contents($handle);
        fclose($handle);

        return str_contains($tail, 'Dump completed');
    }

    /** Stream-compress so large dumps never load into memory. */
    private function gzip(string $source, string $target): void
    {
        $in = fopen($source, 'rb');
        $out = gzopen($target, 'wb6');
        if (! $in || ! $out) {
            throw new RuntimeException('Could not write the compressed backup file.');
        }
        while (! feof($in)) {
            gzwrite($out, (string) fread($in, 1024 * 512));
        }
        fclose($in);
        gzclose($out);

        if (! is_file($target) || filesize($target) === 0) {
            throw new RuntimeException('Compressed backup file is empty.');
        }
    }
}
