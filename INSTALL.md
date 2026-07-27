# User Module Enterprise Refactor v2 — Installation

## 1. Back up or commit current work

```bash
git status
git add .
git commit -m "chore: checkpoint before user module v2 patch"
```

## 2. Copy the patch

Copy every directory from this package into the Laravel project root and allow the four code/test files to be replaced.

Files replaced:

- `app/Enums/UserRole.php`
- `app/Http/Controllers/UserController.php`
- `resources/views/users/_form.blade.php`
- `tests/Feature/UserManagement/UserCrudWorkflowTest.php`

File added:

- `docs/16-user-module-v2-audit-and-fixes.md`

## 3. Clear generated state

```bash
composer dump-autoload
php artisan optimize:clear
```

## 4. Format and test

```bash
php vendor/bin/pint --dirty
php artisan test tests/Feature/UserManagement
php artisan test
```

## 5. Manual checks

1. Open Users > Create and confirm all three roles render.
2. Open Users > Edit and confirm the current role is selected.
3. Edit a user without entering a password and confirm the old password remains valid.
4. Confirm an inactive current designation remains visible on its user's edit form.
5. Confirm an administrator cannot deactivate their own account.

## Expected result

The full suite should pass, and create/edit forms should load without enum, object-as-array, missing-method, or query-argument errors.
