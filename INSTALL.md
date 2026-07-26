# User Module Enterprise Refactor v1

## Installation

Copy every folder from this package into the Laravel project root. Replace existing files when prompted.

## Files Added

- `app/Actions/Users/CreateUserAction.php`
- `app/Actions/Users/UpdateUserAction.php`
- `app/Data/UserData.php`
- `app/Queries/Users/ListUsersQuery.php`
- `app/Queries/Designations/GetAssignableDesignationsQuery.php`
- `tests/Feature/UserManagement/UserCrudWorkflowTest.php`
- `docs/13-user-module-reference-implementation.md`

## Files Replaced

- `app/Http/Controllers/UserController.php`
- `app/Http/Requests/UpdateUserRequest.php`

## Verification Commands

```bash
composer dump-autoload
php artisan optimize:clear
php vendor/bin/pint --dirty
php artisan test
```

Run the focused module tests when troubleshooting:

```bash
php artisan test tests/Feature/UserManagement
```

## Expected Behavior

- Admin user management remains functional.
- User listing supports name, email and designation search.
- Create and update operations pass through Actions and `UserData`.
- Leaving password blank during edit preserves the current password.
- An admin cannot deactivate their own account.
- Existing inactive designation remains valid for the current user during edit.
