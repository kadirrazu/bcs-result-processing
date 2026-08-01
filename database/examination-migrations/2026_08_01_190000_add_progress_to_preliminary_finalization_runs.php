<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'exam';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable('preliminary_finalization_runs')) {
            return;
        }

        $schema->table('preliminary_finalization_runs', function (Blueprint $table) use ($schema): void {
            if (! $schema->hasColumn('preliminary_finalization_runs', 'current_step')) {
                $table->string('current_step', 120)->nullable()->after('status');
            }

            if (! $schema->hasColumn('preliminary_finalization_runs', 'total_rows')) {
                $table->unsignedBigInteger('total_rows')->default(0)->after('current_step');
            }

            if (! $schema->hasColumn('preliminary_finalization_runs', 'processed_rows')) {
                $table->unsignedBigInteger('processed_rows')->default(0)->after('total_rows');
            }

            if (! $schema->hasColumn('preliminary_finalization_runs', 'progress_percent')) {
                $table->decimal('progress_percent', 5, 2)->default(0)->after('processed_rows');
            }
        });
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if (! $schema->hasTable('preliminary_finalization_runs')) {
            return;
        }

        $columns = array_values(array_filter([
            'current_step',
            'total_rows',
            'processed_rows',
            'progress_percent',
        ], fn (string $column): bool => $schema->hasColumn('preliminary_finalization_runs', $column)));

        if ($columns !== []) {
            $schema->table('preliminary_finalization_runs', function (Blueprint $table) use ($columns): void {
                $table->dropColumn($columns);
            });
        }
    }
};
