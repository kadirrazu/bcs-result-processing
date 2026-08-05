<?php

namespace Tests\Feature\Development;

use Illuminate\Support\Str;
use Tests\TestCase;

final class DevelopmentModuleResetRegistryTest extends TestCase
{
    public function test_every_module_owned_examination_table_is_registered_for_reset(): void
    {
        $registry = config('development-module-reset.modules');

        self::assertIsArray($registry);
        self::assertNotEmpty($registry);

        $createdTables = $this->discoverExaminationTables();

        foreach (array_keys($registry) as $module) {
            $expected = array_values(array_filter(
                $createdTables,
                fn (string $table): bool => $this->belongsToModule($table, $module),
            ));

            sort($expected);

            $configured = array_values(array_unique(array_map(
                'strval',
                (array) ($registry[$module]['tables'] ?? []),
            )));
            sort($configured);

            self::assertSame(
                $expected,
                $configured,
                sprintf(
                    'Reset registry for [%s] is out of sync with examination migrations. '
                    .'When a module-owned table is added or removed, update config/development-module-reset.php in the same change.',
                    $module,
                ),
            );
        }
    }

    public function test_shared_import_correction_scopes_are_registered_for_reset(): void
    {
        $registry = config('development-module-reset.modules');
        $configuredScopes = [];

        foreach ($registry as $module => $definition) {
            foreach ((array) ($definition['scoped_deletes'] ?? []) as $scope) {
                if (($scope['table'] ?? null) !== 'import_correction_entries'
                    || ($scope['column'] ?? null) !== 'module') {
                    continue;
                }

                foreach ((array) ($scope['values'] ?? []) as $value) {
                    $configuredScopes[(string) $value] = (string) $module;
                }
            }
        }

        $usedScopes = $this->discoverImportCorrectionModuleValues();

        foreach ($usedScopes as $scope) {
            $owner = $this->ownerForCorrectionScope($scope);

            self::assertArrayHasKey(
                $scope,
                $configuredScopes,
                "Shared correction scope [{$scope}] is used by the application but is missing from the module reset registry.",
            );

            self::assertSame(
                $owner,
                $configuredScopes[$scope],
                "Shared correction scope [{$scope}] is assigned to the wrong reset module.",
            );
        }
    }

    /**
     * @return list<string>
     */
    private function discoverExaminationTables(): array
    {
        $tables = [];
        $directory = database_path('examination-migrations');

        foreach (glob($directory.DIRECTORY_SEPARATOR.'*.php') ?: [] as $file) {
            $contents = (string) file_get_contents($file);

            preg_match_all(
                '/(?:Schema::connection\([^)]+\)|\$schema)->create\s*\(\s*[\'"]([^\'"]+)[\'"]/s',
                $contents,
                $matches,
            );

            foreach ($matches[1] ?? [] as $table) {
                $tables[] = (string) $table;
            }
        }

        $tables = array_values(array_unique($tables));
        sort($tables);

        return $tables;
    }

    private function belongsToModule(string $table, string $module): bool
    {
        if ($module === 'registration') {
            return $table === 'registrations' || Str::startsWith($table, 'registration_');
        }

        return Str::startsWith($table, $module.'_');
    }

    /**
     * @return list<string>
     */
    private function discoverImportCorrectionModuleValues(): array
    {
        $values = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            $contents = (string) file_get_contents($file);

            preg_match_all(
                '/where\(\s*[\'"]module[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/',
                $contents,
                $matches,
            );

            foreach ($matches[1] ?? [] as $value) {
                $values[] = (string) $value;
            }
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    private function ownerForCorrectionScope(string $scope): string
    {
        if (Str::startsWith($scope, 'viva_')) {
            return 'viva';
        }

        return $scope;
    }

    /**
     * @return \Generator<int, string>
     */
    private function phpFiles(string $directory): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                yield $file->getPathname();
            }
        }
    }
}
