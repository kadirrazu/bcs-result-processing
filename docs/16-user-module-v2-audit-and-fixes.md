# User Module v2 Audit and Fixes

## Scope

This patch was produced from the uploaded project snapshot and corrects the regressions introduced during the first User Module application-layer refactor.

## Confirmed root causes

1. `UserRole` declared uppercase enum cases while the model, factory, seeders, and tests referenced PascalCase cases.
2. A model `casts()` method had accidentally been copied into the enum.
3. The create controller called a non-existent `UserRole::options()` method while edit used `UserRole::cases()`.
4. The edit controller accepted a query service both through constructor injection and method injection, creating an unnecessary and error-prone second calling convention.
5. The shared Blade form assumed one role payload shape and had no render-focused regression tests.

## Decisions

- Enum case names are `Admin`, `Operator`, and `Viewer`.
- Both create and edit forms receive `UserRole::cases()`.
- The controller uses constructor-injected query/action dependencies consistently.
- The form works with the enum cast and safely handles a legacy string role value.
- Feature tests now render both forms and verify inactive current designation retention.

## Verification performed

All PHP files under `app`, `database`, `tests`, `routes`, `bootstrap`, and `config` passed `php -l` syntax validation.

The uploaded archive did not contain `vendor/`, so the Laravel test suite could not be executed inside the audit workspace. Run the commands in `INSTALL.md` from the real project, where Composer dependencies are installed.
