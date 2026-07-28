<?php

namespace App\Services\MasterDataExport;

use App\Services\MasterDataImport\MasterDataImportDefinition;

/** Expose export metadata while reusing the locked import column definitions. */
final readonly class MasterDataExportDefinition
{
    public function __construct(public MasterDataImportDefinition $import) {}

    public static function resolve(string $key): self
    {
        return new self(MasterDataImportDefinition::resolve($key));
    }

    public function key(): string { return $this->import->key; }
    public function label(): string { return $this->import->label(); }
    public function model(): string { return $this->import->model(); }
    public function headers(): array { return $this->import->headers(); }

    public function orientation(): string
    {
        return $this->key() === 'cadre-masters' ? 'landscape' : 'portrait';
    }

    public function columns(): array
    {
        return match ($this->key()) {
            'cadre-masters' => [
                'cadre_code' => 'Code',
                'cadre_abbr' => 'Abbreviation',
                'cadre_title' => 'English Title',
                'cadre_title_bn' => 'Bangla Title',
                'cadre_type' => 'Type',
                'display_order' => 'Display Order',
                'is_active' => 'Status',
            ],
            default => [
                'subject_code' => 'Code',
                'subject_name' => 'Name',
                'is_active' => 'Status',
            ],
        };
    }
}
