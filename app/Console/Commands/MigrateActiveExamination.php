<?php

namespace App\Console\Commands;

use App\Models\Examination;
use App\Support\Examinations\ExaminationConnectionManager;
use Illuminate\Console\Command;

/**
 * Run shared examination migrations against one explicitly resolved database.
 *
 * Browser session state is not available to Artisan. The command therefore
 * accepts an examination ID, slug, BCS number, or database name and can also
 * display an interactive selector when executed in a terminal.
 */
final class MigrateActiveExamination extends Command
{
    /** @var string */
    protected $signature = 'examination:migrate
        {examination? : Examination ID, slug, BCS number, or database name}
        {--force : Run migrations in production without confirmation}';

    /** @var string */
    protected $description = 'Migrate a selected examination database';

    public function handle(ExaminationConnectionManager $manager): int
    {
        $examination = $this->resolveExamination();

        if ($examination === null) {
            $this->error('No enabled examination could be resolved.');

            return self::FAILURE;
        }

        $this->components->info(
            "Migrating {$examination->name} [{$examination->database_name}]..."
        );

        $manager->configure($examination);

        try {
            $exitCode = $this->call('migrate', [
                '--database' => $manager->connectionName(),
                '--path' => 'database/examination-migrations',
                '--force' => (bool) $this->option('force'),
            ]);
        } finally {
            $manager->disconnect();
        }

        if ($exitCode !== self::SUCCESS) {
            return $exitCode;
        }

        $this->components->info('Examination database migration completed.');

        return self::SUCCESS;
    }

    /**
     * Resolve the command argument or ask the operator to select an examination.
     */
    private function resolveExamination(): ?Examination
    {
        $identifier = $this->argument('examination');

        if (is_string($identifier) && trim($identifier) !== '') {
            return $this->findByIdentifier(trim($identifier));
        }

        $examinations = Examination::query()
            ->where('is_enabled', true)
            ->orderByDesc('bcs_number')
            ->get();

        if ($examinations->isEmpty()) {
            return null;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Pass an examination identifier when running non-interactively.');

            return null;
        }

        /*
         * Laravel's choice() helper returns the selected option value (the
         * visible label), not the associative-array key. Keep the options and
         * examination collection in the same zero-based order so the selected
         * label can be resolved safely back to its Examination model.
         */
        $examinations = $examinations->values();

        $choices = $examinations
            ->map(
                static fn (Examination $examination): string => sprintf(
                    '%s — DB: %s',
                    $examination->name,
                    $examination->database_name,
                )
            )
            ->all();

        $selectedChoice = $this->choice(
            'Select the examination database to migrate',
            $choices,
        );

        $selectedIndex = array_search($selectedChoice, $choices, true);

        if ($selectedIndex === false) {
            return null;
        }

        return $examinations->get($selectedIndex);
    }

    /**
     * Match a human-friendly identifier against the central examination registry.
     */
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
}
