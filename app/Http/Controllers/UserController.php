<?php

namespace App\Http\Controllers;

use App\Actions\Users\CreateUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Data\UserData;
use App\Enums\UserRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use App\Queries\Designations\GetAssignableDesignationsQuery;
use App\Queries\Users\ListUsersQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Coordinate HTTP requests for central application user administration.
 */
final class UserController extends Controller
{
    public function __construct(
        private readonly ListUsersQuery $listUsers,
        private readonly GetAssignableDesignationsQuery $assignableDesignations,
        private readonly CreateUserAction $createUser,
        private readonly UpdateUserAction $updateUser,
    ) {}

    /**
     * Display the paginated user directory.
     */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $search = trim((string) $request->query('search'));

        return view('users.index', [
            'users' => $this->listUsers->execute($search),
            'search' => $search,
        ]);
    }

    /**
     * Display the user creation form.
     */
    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('users.create', [
            'designations' => $this->assignableDesignations->execute(),
            'roles' => UserRole::cases(),
        ]);
    }

    /**
     * Persist a new application user.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->createUser->execute(
            UserData::fromValidated($request->validated(), $request->boolean('is_active'))
        );

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
     * Display the form for editing an existing user.
     */
    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', [
            'user' => $user,
            'designations' => $this->assignableDesignations->execute($user->designation_id),
            'roles' => UserRole::cases(),
        ]);
    }

    /**
     * Update an existing application user.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->updateUser->execute(
            actor: $request->user(),
            target: $user,
            data: UserData::fromValidated($request->validated(), $request->boolean('is_active')),
        );

        return redirect()
            ->route('users.index')
            ->with('success', 'User updated successfully.');
    }
}
