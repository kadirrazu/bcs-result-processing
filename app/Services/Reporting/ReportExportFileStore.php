<?php

namespace App\Services\Reporting;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Shared private artifact storage for queued report/export jobs.
 * Module services remain responsible for business-specific file contents.
 */
final class ReportExportFileStore
{
    public function outputPath(string $module, int $runId, string $extension): string
    {
        $directory = $this->directory($module, $runId);
        $extension = ltrim(strtolower($extension), '.');

        return $directory.DIRECTORY_SEPARATOR.'output.'.$extension;
    }

    public function storeUploadedSource(string $module, int $runId, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: 'bin');
        $name = 'source-'.Str::random(12).'.'.$extension;
        $path = $this->directory($module, $runId).DIRECTORY_SEPARATOR.$name;
        File::copy($file->getRealPath(), $path);

        return $path;
    }

    public function forget(?string $path): void
    {
        if ($path && File::isFile($path)) {
            File::delete($path);
        }
    }

    private function directory(string $module, int $runId): string
    {
        $safeModule = Str::slug($module) ?: 'reporting';
        $directory = storage_path('app/private/reporting-exports/'.$safeModule.'/'.$runId);
        File::ensureDirectoryExists($directory);

        return $directory;
    }
}
