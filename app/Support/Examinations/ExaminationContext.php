<?php

namespace App\Support\Examinations;

use App\Models\Examination;
use Illuminate\Contracts\Session\Session;

/**
 * Request-scoped accessor for the examination selected by the authenticated user.
 */
final class ExaminationContext
{
    public const SESSION_KEY = 'active_examination_id';

    private ?Examination $resolved = null;

    public function __construct(private readonly Session $session) {}

    /**
     * Return the selected examination, or null when no valid context exists.
     */
    public function current(): ?Examination
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $id = $this->session->get(self::SESSION_KEY);

        if (! is_numeric($id)) {
            return null;
        }

        $examination = Examination::query()->find((int) $id);

        if ($examination === null || ! $examination->isSelectable()) {
            $this->clear();

            return null;
        }

        return $this->resolved = $examination;
    }

    public function currentId(): ?int
    {
        return $this->current()?->getKey();
    }

    public function currentDatabase(): ?string
    {
        return $this->current()?->database_name;
    }

    public function hasActive(): bool
    {
        return $this->current() !== null;
    }

    /**
     * Select an examination after its database has been validated upstream.
     */
    public function select(Examination $examination): void
    {
        abort_unless($examination->isSelectable(), 422, 'This examination cannot be selected.');

        $this->session->put(self::SESSION_KEY, $examination->getKey());
        $this->resolved = $examination;
    }

    /**
     * Forget the cached model so changes in the central registry are re-read.
     */
    public function refresh(): ?Examination
    {
        $this->resolved = null;

        return $this->current();
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
        $this->resolved = null;
    }

    public function is(Examination $examination): bool
    {
        return $this->current()?->is($examination) ?? false;
    }
}
