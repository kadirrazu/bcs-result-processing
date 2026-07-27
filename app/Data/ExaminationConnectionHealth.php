<?php

namespace App\Data;

use Throwable;

/**
 * Immutable result returned by an examination database connectivity check.
 */
final readonly class ExaminationConnectionHealth
{
    public function __construct(
        public bool $connected,
        public ?string $databaseName,
        public ?int $migrationBatch,
        public ?string $error,
    ) {}

    /**
     * Build a successful health result.
     */
    public static function success(string $databaseName, ?int $migrationBatch): self
    {
        return new self(true, $databaseName, $migrationBatch, null);
    }

    /**
     * Build a failed health result without exposing credentials or SQL details.
     */
    public static function failure(string $databaseName, Throwable $exception): self
    {
        return new self(
            connected: false,
            databaseName: $databaseName,
            migrationBatch: null,
            error: $exception->getMessage(),
        );
    }
}
