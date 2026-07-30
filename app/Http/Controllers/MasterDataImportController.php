<?php

namespace App\Http\Controllers;

use App\Http\Requests\PreviewMasterDataImportRequest;
use App\Services\MasterDataImport\MasterDataImportDefinition;
use App\Services\MasterDataImport\MasterDataImportPreviewService;
use App\Services\MasterDataImport\MasterDataImportService;
use App\Services\MasterDataImport\MasterDataTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/** Coordinate reusable preview-and-confirm imports for central master data. */
final class MasterDataImportController extends Controller
{
    public function create(string $type): View
    {
        $definition = MasterDataImportDefinition::resolve($type);
        $this->authorize('create', $definition->model());

        return view('master-data.imports.create', compact('definition'));
    }

    public function template(string $type, MasterDataTemplateService $templates): BinaryFileResponse
    {
        $definition = MasterDataImportDefinition::resolve($type);
        $this->authorize('create', $definition->model());

        return $templates->download($definition);
    }

    public function preview(
        string $type,
        PreviewMasterDataImportRequest $request,
        MasterDataImportPreviewService $previews,
    ): View|RedirectResponse {
        $definition = MasterDataImportDefinition::resolve($type);
        $this->authorize('create', $definition->model());

        $path = $request->file('file')->store('master-imports');

        try {
            $rows = $previews->preview($definition, Storage::disk('local')->path($path));
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($path);
            report($exception);

            return back()->withInput()->withErrors([
                'file' => $exception->getMessage(),
            ]);
        }

        if ($rows === []) {
            Storage::disk('local')->delete($path);

            return back()->withInput()->withErrors([
                'file' => 'The spreadsheet does not contain any data rows.',
            ]);
        }

        $token = bin2hex(random_bytes(20));

        cache()->put("master-import:{$token}", [
            'type' => $type,
            'mode' => $request->string('mode')->toString(),
            'rows' => $rows,
            'path' => $path,
        ], now()->addMinutes(30));

        return view('master-data.imports.preview', compact('definition', 'rows', 'token'));
    }

    public function store(string $type, Request $request, MasterDataImportService $imports): RedirectResponse
    {
        $definition = MasterDataImportDefinition::resolve($type);
        $this->authorize('create', $definition->model());
        $request->validate(['token' => ['required', 'string']]);

        $payload = cache()->pull('master-import:'.$request->string('token'));
        abort_unless(is_array($payload) && ($payload['type'] ?? null) === $type, 419, 'Import preview expired.');

        try {
            $summary = $imports->import($definition, $payload['rows'], $payload['mode']);
        } finally {
            Storage::disk('local')->delete($payload['path']);
        }

        return redirect()->route($definition->route(), $definition->routeParameters())->with(
            'success',
            "Import complete: {$summary['inserted']} inserted, {$summary['updated']} updated, {$summary['skipped']} skipped, {$summary['failed']} failed.",
        );
    }
}
