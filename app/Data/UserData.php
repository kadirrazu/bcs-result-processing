<?php

namespace App\Data;

use App\Enums\UserRole;

/**
 * Immutable application data required to create or update a user.
 */
final readonly class UserData
{
    public function __construct(
        public string $name,
        public string $email,
        public int $designationId,
        public UserRole $role,
        public bool $isActive,
        public ?string $password = null,
    ) {}

    /**
     * Build the DTO from validated request data.
     *
     * @param  array<string, mixed>  $validated
     */
    public static function fromValidated(array $validated, bool $isActive): self
    {
        return new self(
            name: $validated['name'],
            email: $validated['email'],
            designationId: (int) $validated['designation_id'],
            role: UserRole::from($validated['role']),
            isActive: $isActive,
            password: filled($validated['password'] ?? null)
                ? $validated['password']
                : null,
        );
    }

    /**
     * Convert the DTO into model attributes.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(bool $includePassword = true): array
    {
        $attributes = [
            'name' => $this->name,
            'email' => $this->email,
            'designation_id' => $this->designationId,
            'role' => $this->role,
            'is_active' => $this->isActive,
        ];

        if ($includePassword && $this->password !== null) {
            $attributes['password'] = $this->password;
        }

        return $attributes;
    }
}
