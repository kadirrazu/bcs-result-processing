<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        if (! $schema->hasColumn('allocation_runs', 'is_stale')) {
            $schema->table('allocation_runs', function (Blueprint $table): void {
                $table->boolean('is_stale')->default(false)->index('alloc_run_stale_idx');
                $table->text('stale_reason')->nullable();
                $table->timestamp('staled_at')->nullable()->index('alloc_run_staled_at_idx');
            });
        }

        if (! $schema->hasColumn('allocation_a4_runs', 'is_stale')) {
            $schema->table('allocation_a4_runs', function (Blueprint $table): void {
                $table->boolean('is_stale')->default(false)->index('alloc_a4_stale_idx');
                $table->text('stale_reason')->nullable();
                $table->timestamp('staled_at')->nullable()->index('alloc_a4_staled_at_idx');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');

        if ($schema->hasColumn('allocation_a4_runs', 'is_stale')) {
            $schema->table('allocation_a4_runs', function (Blueprint $table): void {
                $table->dropIndex('alloc_a4_stale_idx');
                $table->dropIndex('alloc_a4_staled_at_idx');
                $table->dropColumn(['is_stale', 'stale_reason', 'staled_at']);
            });
        }

        if ($schema->hasColumn('allocation_runs', 'is_stale')) {
            $schema->table('allocation_runs', function (Blueprint $table): void {
                $table->dropIndex('alloc_run_stale_idx');
                $table->dropIndex('alloc_run_staled_at_idx');
                $table->dropColumn(['is_stale', 'stale_reason', 'staled_at']);
            });
        }
    }
};
