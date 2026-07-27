# Dynamic Examination Database Manager — Installation

Copy this package into the Laravel project root and overwrite matching files.

## 1. Environment

Optional when examination databases use the same MySQL server and credentials as CENTRAL:

```env
EXAM_DB_BASE_CONNECTION=mysql
```

Create each registered physical database manually, for example:

```sql
CREATE DATABASE bcs_exam_47 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

The configured application database user must have access to it. The application never stores database passwords in the central registry.

## 2. Route middleware for future examination modules

Use the middleware classes directly on examination-domain route groups:

```php
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;

Route::middleware([
    'auth',
    EnsureExaminationSelected::class,
    ConfigureExaminationConnection::class,
])->group(function (): void {
    // Candidate, Cadre, Subject, Marks, Merit and Allocation routes.
});
```

Do not apply these middleware to central routes such as users or examinations.

## 3. Commands

```bash
composer dump-autoload
php artisan optimize:clear
php artisan migrate
php vendor/bin/pint --dirty
php artisan test tests/Unit/Examinations
php artisan test tests/Feature/Examinations
php artisan test
```

## 4. Manual verification

1. Create the physical database matching `database_name`.
2. Open **Examinations**.
3. Click **Check DB**; health should become **Connected**.
4. Click **Select**; the examination should become **Active context**.
5. Temporarily rename the database and confirm selection fails without replacing the previous active context.

## 5. Model rule

Central models extend `Illuminate\Database\Eloquent\Model`.
Operational examination models extend:

```php
use App\Models\ExaminationModel;

final class Candidate extends ExaminationModel
{
    // Uses the active physical BCS database automatically.
}
```
