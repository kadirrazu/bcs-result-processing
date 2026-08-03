<?php

namespace App\Services\Exports;

use DateTimeInterface;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class AdministrativeExportCacheService
{
    public function path(
        string $module,
        string $examinationKey,
        DateTimeInterface $finalizedAt,
        string $scope,
        string $order,
        string $direction,
    ): string {
        $directory = storage_path('app/private/administrative-export-cache/'.Str::slug($module));
        File::ensureDirectoryExists($directory);

        $key = implode('-', [
            Str::slug($examinationKey),
            $finalizedAt->format('YmdHis'),
            Str::slug($scope),
            Str::slug($order),
            Str::slug($direction),
        ]);

        return $directory.DIRECTORY_SEPARATOR.$key.'.xlsx';
    }

    public function isReady(string $path): bool
    {
        return File::exists($path) && File::size($path) > 0;
    }

    /** Keep stale export files from accumulating forever. */
    public function prune(string $path, int $days = 14): void
    {
        $directory = dirname($path);
        if (! File::isDirectory($directory)) {
            return;
        }

        $cutoff = now()->subDays($days)->getTimestamp();
        foreach (File::files($directory) as $file) {
            if ($file->getPathname() === $path) {
                continue;
            }

            if ($file->getMTime() < $cutoff) {
                File::delete($file->getPathname());
            }
        }
    }
}
