<?php

namespace App\Services\MasterDataImport;

use InvalidArgumentException;

/** Resolve and expose a whitelisted master-data import definition. */
final readonly class MasterDataImportDefinition
{
    public function __construct(public string $key, public array $config) {}

    public static function resolve(string $key): self
    {
        $config = config("master-data-imports.{$key}");

        if (! is_array($config)) {
            throw new InvalidArgumentException('Unsupported master data import type.');
        }

        return new self($key, $config);
    }

    public function model(): string { return $this->config['model']; }
    public function headers(): array { return $this->config['headers']; }
    public function required(): array { return $this->config['required']; }
    public function uniqueBy(): string { return $this->config['unique_by']; }
    public function additionalUniqueBy(): array { return $this->config['additional_unique_by'] ?? []; }
    public function allUniqueColumns(): array { return [$this->uniqueBy(), ...$this->additionalUniqueBy()]; }
    public function label(): string { return $this->config['label']; }
    public function route(): string { return $this->config['route']; }
    public function routeParameters(): array { return $this->config['route_parameters'] ?? []; }
}
