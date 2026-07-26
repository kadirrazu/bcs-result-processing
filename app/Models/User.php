<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Central application user.
 *
 * Examination candidates are not represented by this model. This model is
 * reserved for authenticated application operators and administrators.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Attributes that may be mass assigned.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'designation_id',
        'role',
        'is_active',
        'last_login_at',
    ];

    /**
     * Attributes hidden during serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Attribute casts used by the model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * The user's organizational designation.
     */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Designation::class);
    }

    /**
     * Determine whether the user has administrator authority.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
