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
