<?php

declare(strict_types=1);

namespace App\Services;

final class BackupRetentionService
{
    /** @return list<string> */
    public function prune(string $directory, int $keep = 5): array
    {
        $archives = glob(rtrim($directory, '/\\') . '/muscu-*.sql.gz') ?: [];
        usort($archives, static function (string $left, string $right): int {
            $modified = filemtime($right) <=> filemtime($left);
            return $modified !== 0 ? $modified : strcmp($right, $left);
        });

        $deleted = [];
        foreach (array_slice($archives, max(0, $keep)) as $archive) {
            if (unlink($archive)) {
                $deleted[] = $archive;
            }
        }

        return $deleted;
    }
}
