<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Designation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Manage authenticated central application users.
 *
 * Business operations will be moved into application actions in the next
 * milestone. This step first establishes an enforceable authorization boundary.
 */
class UserController extends Controller
{
    /**
     * Display the paginated user directory.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->query('search'));

        $users = User::query()
            ->with('designation')
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhereHas('designation', function ($query) use ($search): void {
                            $query->where('name', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return view('users.index', compact('users', 'search'));
    }

    /**
     * Display the user creation form.
     */
    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'designations' => $this->activeDesignations(),
            'roles' => UserRole::options(),
        ]);
    }

    /**
     * Persist a new application user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'designation_id' => $validated['designation_id'],
            'role' => $validated['role'],
            'password' => $validated['password'],
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('users.index')
            ->with('success', 'User created successfully.');
    }

    /**
     * Redirect the unsupported detail route to the edit form.
     */
    public function show(User $user): RedirectResponse
    {
        $this->authorize('view', $user);

        return redirect()->route('users.edit', $user);
    }

    /**
     * Display the user edit form.
     */
    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        $user->load('designation');

        return view('users.edit', [
            'user' => $user,
            'designations' => $this->activeDesignations($user),
            'roles' => UserRole::options(),
        ]);
    }

    /**
     * Update an existing application user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $validated = $request->validated();
        $isActive = $request->boolean('is_active');

        if ($request->user()->is($user) && ! $isActive) {
            return back()
                ->withInput()
                ->withErrors([
                    'is_active' => 'You cannot deactivate your own account.',
                ]);
        }

        $data = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'designation_id' => $validated['designation_id'],
            'role' => $validated['role'],
            'is_active' => $isActive,
        ];

        if (! empty($validated['password'])) {
            $data['password'] = $validated['password'];
        }

        $user->update($data);

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }

    /**
     * Return active designations, retaining the target user's current value.
     *
     * @return Collection<int, Designation>
     */
    private function activeDesignations(?User $user = null): Collection
    {
        return Designation::query()
            ->where(function ($query) use ($user): void {
                $query->where('is_active', true);

                if ($user?->designation_id) {
                    $query->orWhere('id', $user->designation_id);
                }
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
