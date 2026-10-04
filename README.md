# BCS Result Processing System

A Laravel-based result processing system for Bangladesh Civil Service
(BCS) examinations. The project manages examination data through
controlled, auditable processing workflows including registration,
preliminary, written examination, validation, corrections, result
processing, reporting, and later stages of the BCS result lifecycle.

The system is designed for large datasets, examination-specific
databases, background queue processing, staged imports, validation,
audit trails, manual corrections, reproducible processing steps, and
traceable result generation.

## Requirements

-   PHP 8.3+
-   Composer
-   MySQL / MariaDB
-   Node.js and npm
-   Laravel-compatible web server (WAMP/XAMPP or equivalent)

## Development Environment

For local WAMP/XAMPP development, configure the active PHP `php.ini`
with the following recommended values:

``` ini
upload_max_filesize = 512M
post_max_size = 600M
memory_limit = 1024M
max_execution_time = 0
max_input_time = -1
```

Notes:

-   `post_max_size` should remain larger than `upload_max_filesize`.
-   `max_execution_time = 0` disables the PHP script execution time
    limit.
-   `max_input_time = -1` follows `max_execution_time`.
-   After changing `php.ini`, restart Apache.
-   Verify the PHP configuration used by the web server with
    `phpinfo()`.
-   Verify the PHP configuration used by the CLI with:

``` bash
php --ini
```

The Apache/web PHP configuration and CLI PHP configuration may use
different `php.ini` files, so both should be checked when setting up a
new development environment.

## Installation

Clone the repository and enter the project directory:

``` bash
git clone <repository-url>
cd bcs-result-processing
```

Install PHP dependencies:

``` bash
composer install
```

Install frontend dependencies:

``` bash
npm install
```

Create the environment file:

``` bash
cp .env.example .env
```

On Windows CMD, if `cp` is unavailable:

``` cmd
copy .env.example .env
```

Generate the application key:

``` bash
php artisan key:generate
```

Configure the database and other required settings in `.env`, then run
the main application migrations:

``` bash
php artisan migrate
```

Build frontend assets:

``` bash
npm run build
```

Clear cached configuration:

``` bash
php artisan optimize:clear
```

Create/select the required examination and run its examination-database
migrations as applicable:

``` bash
php artisan examination:migrate
```

For queued imports and processing, run the queue worker:

``` bash
php artisan queue:work database --queue=imports --timeout=0 --tries=1 --memory=900
```

For local development, start Laravel if it is not already being served
through WAMP/XAMPP:

``` bash
php artisan serve
```

Then open the application in your browser and select/configure the
examination you want to work with.

## Stable Development Workflow

For normal day-to-day development, keep dependency versions reproducible by using the committed lock files. After pulling the latest project changes, use the following workflow:

```bash
git pull origin master

composer install
npm install

php artisan migrate
php artisan examination:migrate   # Run when new examination migrations are available
php artisan optimize:clear
```

When you want a stricter clean frontend dependency installation from the committed `package-lock.json`, you may use `npm ci` instead of `npm install`:

```bash
npm ci
```

After dependency or frontend-related changes, verify the project before committing/pushing:

```bash
php artisan test
npm run build
```

### Dependency Update Rule

Do **not** run `composer update` or `npm update` as a routine step on every pull or development session.

- `composer install` installs the versions recorded in `composer.lock`.
- `npm install` installs dependencies using `package-lock.json` and is appropriate for normal local development.
- `npm ci` performs a clean, reproducible installation strictly from `package-lock.json` and is useful for clean verification/CI-style installs.
- `composer update` intentionally resolves newer allowed Composer package versions and may modify `composer.lock`.
- `npm update` intentionally updates allowed npm package versions and may modify `package-lock.json`.

Treat `composer update` and `npm update` as deliberate dependency-maintenance tasks. When they are intentionally run, review the changed lock files and run the full verification commands before committing them:

```bash
php artisan optimize:clear
php artisan test
npm run build

git status
git add composer.lock package-lock.json
git commit -m "Update Composer and npm dependencies"
git push origin master
```

If a dependency update was accidental and you do not want to keep the resulting lock-file changes, restore the repository versions before pulling other changes:

```bash
git restore composer.lock package-lock.json
composer install
npm install
```

Always review `git status` before committing or pulling so that unintended local changes are not lost or allowed to block a later `git pull`.
