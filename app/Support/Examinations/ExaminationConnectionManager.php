<?php

namespace App\Support\Examinations;

use App\Data\ExaminationConnectionHealth;
use App\Models\Examination;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Filesystem\Filesystem;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Builds and refreshes the shared runtime connection for one examination.
 */
class ExaminationConnectionManager
{
    public const CONNECTION = 'exam';

    public function __construct(
        private readonly DatabaseManager $database,
        private readonly Filesystem $files,
    ) {}

    /**
     * Configure the runtime connection for the supplied examination.
     */
    public function configure(Examination $examination): ConnectionInterface
    {
        abort_unless($examination->isSelectable(), 422, 'This examination cannot be used.');

        $connectionName = $this->connectionName();
        $this->database->purge($connectionName);
        config(["database.connections.{$connectionName}" => $this->buildConfiguration($examination)]);

        return $this->database->connection($connectionName);
    }

    /**
     * Remove the current runtime connection and its cached PDO instance.
     */
    public function disconnect(): void
    {
        $connectionName = $this->connectionName();
        $this->database->purge($connectionName);
        config()->offsetUnset("database.connections.{$connectionName}");
    }

    /**
     * Test connectivity and read the latest examination migration batch.
     */
    public function check(Examination $examination): ExaminationConnectionHealth
    {
        try {
            $connection = $this->configure($examination);
            $connection->getPdo();

            $migrationBatch = $connection->getSchemaBuilder()->hasTable('migrations')
                ? (int) $connection->table('migrations')->max('batch')
                : null;

            return ExaminationConnectionHealth::success($examination->database_name, $migrationBatch);
        } catch (Throwable $exception) {
            return ExaminationConnectionHealth::failure($examination->database_name, $exception);
        } finally {
            $this->disconnect();
        }
    }

    /**
     * Return the runtime connection name used by examination models.
     */
    public function connectionName(): string
    {
        return (string) config('examinations.connection', self::CONNECTION);
    }

    /**
     * Build a connection by cloning the configured central/base connection.
     *
     * @return array<string, mixed>
     */
    public function buildConfiguration(Examination $examination): array
    {
        $baseName = (string) config('examinations.base_connection', config('database.default'));
        $base = config("database.connections.{$baseName}");

        if (! is_array($base)) {
            throw new RuntimeException("Base database connection [{$baseName}] is not configured.");
        }

        $driver = (string) ($base['driver'] ?? '');

        if ($driver === 'sqlite') {
            return $this->buildSqliteConfiguration($base, $examination);
        }

        $databaseName = $this->validatedDatabaseName($examination->database_name);
        $base['database'] = $databaseName;
        $base['url'] = null;

        return $base;
    }

    /**
     * Resolve a safe SQLite file for local development and automated tests.
     *
     * @param array<string, mixed> $base
     * @return array<string, mixed>
     */
    private function buildSqliteConfiguration(array $base, Examination $examination): array
    {
        $databaseName = $this->validatedDatabaseName($examination->database_name);
        $directory = (string) config('examinations.sqlite_directory', database_path('examinations'));

        if (! $this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }

        $base['database'] = rtrim($directory, DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR.$databaseName.'.sqlite';
        $base['url'] = null;

        return $base;
    }

    /**
     * Reject database identifiers that could escape the intended server/schema.
     */
    private function validatedDatabaseName(string $databaseName): string
    {
        $pattern = (string) config('examinations.database_name_pattern', '/\A[a-zA-Z0-9_]+\z/');

        if ($databaseName === '' || preg_match($pattern, $databaseName) !== 1) {
            throw new InvalidArgumentException('The examination database name is invalid.');
        }

        return $databaseName;
    }
}
