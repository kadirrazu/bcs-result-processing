<?php

namespace App\Console\Commands;

use App\Models\Examination;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Console\Command;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ResetExaminationModule extends Command
{
    protected $signature = 'examination:reset-module
        {examination? : Examination ID, slug, BCS number, or database name}';

    protected $description = 'Reset one processing module in a selected examination database (development only)';

    public function handle(ExaminationConnectionManager $manager): int
    {
        if (! $this->environmentIsAllowed()) {
            $this->components->error(
                sprintf(
                    'Module reset is disabled in the [%s] environment. This command is development-only.',
                    app()->environment()
                )
            );

            return self::FAILURE;
        }

        $examination = $this->resolveExamination();
        if ($examination === null) {
            $this->error('No enabled examination could be resolved.');
            return self::FAILURE;
        }

        $modules = config('development-module-reset.modules', []);
        if (! is_array($modules) || $modules === []) {
            $this->error('No resettable modules are configured.');
            return self::FAILURE;
        }

        $moduleKey = $this->selectModule($modules);
        if ($moduleKey === null) {
            $this->warn('Reset cancelled.');
            return self::SUCCESS;
        }

        $definition = $modules[$moduleKey];
        $label = (string) ($definition['label'] ?? $moduleKey);

        $this->newLine();
        $this->components->warn('DEVELOPMENT DATA RESET');
        $this->line("Examination : {$examination->name}");
        $this->line("Database    : {$examination->database_name}");
        $this->line("Module      : {$label}");
        $this->newLine();

        $downstream = array_values(array_filter((array) ($definition['downstream'] ?? [])));
        if ($downstream !== []) {
            $this->warn(
                'Dependency warning: data in '.implode(', ', $downstream)
                .' may depend on this module. Those modules will NOT be changed automatically.'
            );
        }

        $this->warn('Only the selected module data will be deleted. Other module tables will remain unchanged.');
        $this->line('After confirmation, a row-based progress bar will show reset progress.');
        $typed = (string) $this->ask('Type RESET exactly to continue');

        if ($typed !== 'RESET') {
            $this->warn('Confirmation did not match RESET. Nothing was changed.');
            return self::SUCCESS;
        }

        $connection = $manager->configure($examination);

        try {
            $summary = $this->resetModule($connection, $definition);
        } catch (Throwable $exception) {
            Log::error('Development module reset failed.', [
                'examination_id' => $examination->id,
                'database' => $examination->database_name,
                'module' => $moduleKey,
                'environment' => app()->environment(),
                'error' => $exception->getMessage(),
            ]);

            $this->components->error('Module reset failed: '.$exception->getMessage());
            return self::FAILURE;
        } finally {
            $manager->disconnect();
        }

        Log::warning('Development module data reset completed.', [
            'examination_id' => $examination->id,
            'examination' => $examination->name,
            'database' => $examination->database_name,
            'module' => $moduleKey,
            'environment' => app()->environment(),
            'summary' => $summary,
        ]);

        $this->newLine();
        $this->components->info("{$label} module reset completed.");
        $this->table(['Table / scope', 'Before', 'After'], $summary);
        $this->line('Other module tables were not changed.');

        return self::SUCCESS;
    }

    private function environmentIsAllowed(): bool
    {
        $allowed = array_map(
            static fn ($value): string => strtolower(trim((string) $value)),
            (array) config('development-module-reset.allowed_environments', ['local', 'development'])
        );

        return in_array(strtolower((string) app()->environment()), $allowed, true);
    }

    /**
     * @param array<string, array<string, mixed>> $modules
     */
    private function selectModule(array $modules): ?string
    {
        $keys = array_keys($modules);
        $choices = array_map(
            static fn (string $key): string => (string) ($modules[$key]['label'] ?? $key),
            $keys
        );

        $selected = $this->choice('Select the module to reset', $choices);
        $index = array_search($selected, $choices, true);

        return $index === false ? null : $keys[$index];
    }

    private function resolveExamination(): ?Examination
    {
        $identifier = $this->argument('examination');

        if (is_string($identifier) && trim($identifier) !== '') {
            return $this->findByIdentifier(trim($identifier));
        }

        $examinations = Examination::query()
            ->where('is_enabled', true)
            ->orderByDesc('bcs_number')
            ->get()
            ->values();

        if ($examinations->isEmpty()) {
            return null;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Pass an examination identifier when running non-interactively.');
            return null;
        }

        $choices = $examinations->map(
            static fn (Examination $examination): string => sprintf(
                '%s — DB: %s',
                $examination->name,
                $examination->database_name
            )
        )->all();

        $selected = $this->choice('Select the examination database to reset', $choices);
        $index = array_search($selected, $choices, true);

        return $index === false ? null : $examinations->get($index);
    }

    private function findByIdentifier(string $identifier): ?Examination
    {
        return Examination::query()
            ->where('is_enabled', true)
            ->where(function ($query) use ($identifier): void {
                $query
                    ->where('slug', $identifier)
                    ->orWhere('database_name', $identifier);

                if (ctype_digit($identifier)) {
                    $query
                        ->orWhereKey((int) $identifier)
                        ->orWhere('bcs_number', (int) $identifier);
                }
            })
            ->first();
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<int, array{0:string,1:int,2:int}>
     */
    private function resetModule(ConnectionInterface $connection, array $definition): array
    {
        $schema = $connection->getSchemaBuilder();
        $tables = array_values(array_unique((array) ($definition['tables'] ?? [])));
        $scopedDeletes = (array) ($definition['scoped_deletes'] ?? []);
        $chunkSize = max(
            1000,
            (int) config('development-module-reset.delete_chunk_size', 10000)
        );

        // Fail before deleting anything if the registry contains a missing table.
        foreach ($tables as $table) {
            if (! $schema->hasTable((string) $table)) {
                throw new \RuntimeException("Configured reset table [{$table}] does not exist.");
            }
        }

        foreach ($scopedDeletes as $scope) {
            $table = (string) ($scope['table'] ?? '');
            if ($table !== '' && ! $schema->hasTable($table)) {
                throw new \RuntimeException("Configured shared reset table [{$table}] does not exist.");
            }
        }

        $this->components->info(
            'Preparing reset plan and counting rows. Large tables will be deleted in chunks of '
            .number_format($chunkSize).'.'
        );

        $plan = [];
        $totalRows = 0;

        foreach ($scopedDeletes as $scope) {
            $table = (string) ($scope['table'] ?? '');
            $column = (string) ($scope['column'] ?? '');
            $values = array_values((array) ($scope['values'] ?? []));

            if ($table === '' || $column === '' || $values === []) {
                continue;
            }

            $before = (int) $connection->table($table)
                ->whereIn($column, $values)
                ->count();

            $plan[] = [
                'type' => 'scope',
                'table' => $table,
                'column' => $column,
                'values' => $values,
                'label' => "{$table} ({$column}: ".implode('|', $values).')',
                'before' => $before,
            ];
            $totalRows += $before;
        }

        foreach ($tables as $table) {
            $table = (string) $table;
            $before = (int) $connection->table($table)->count();

            $plan[] = [
                'type' => 'table',
                'table' => $table,
                'label' => $table,
                'before' => $before,
            ];
            $totalRows += $before;
        }

        $summary = [];
        $progress = $this->output->createProgressBar(max(1, $totalRows));
        $progress->setFormat(
            ' %current%/%max% [%bar%] %percent:3s%% | %elapsed:6s% | %message%'
        );
        $progress->setMessage('Starting reset...');
        $progress->start();

        if ($totalRows === 0) {
            $progress->advance();
        }

        try {
            foreach ($plan as $item) {
                $progress->setMessage('Deleting '.$item['label']);

                $deleted = 0;

                do {
                    $query = $connection->table($item['table']);

                    if ($item['type'] === 'scope') {
                        $query->whereIn($item['column'], $item['values']);
                    }

                    /*
                     * Each chunk is intentionally committed independently.
                     * Compared with one huge module-wide transaction, this keeps
                     * InnoDB undo/redo pressure bounded and makes large local
                     * development resets much more responsive.
                     *
                     * We deliberately do NOT use TRUNCATE or disable FK checks.
                     */
                    $affected = (int) $query
                        ->limit($chunkSize)
                        ->delete();

                    if ($affected > 0) {
                        $deleted += $affected;
                        $progress->advance($affected);
                    }
                } while ($affected === $chunkSize);

                $afterQuery = $connection->table($item['table']);
                if ($item['type'] === 'scope') {
                    $afterQuery->whereIn($item['column'], $item['values']);
                }

                $after = (int) $afterQuery->count();
                $summary[] = [$item['label'], (int) $item['before'], $after];

                if ($after !== 0) {
                    throw new \RuntimeException(
                        "Reset verification failed for [{$item['label']}]: {$after} row(s) remain."
                    );
                }
            }
        } finally {
            $progress->setMessage('Reset finished');
            $progress->finish();
            $this->newLine(2);
        }

        return $summary;
    }

}
