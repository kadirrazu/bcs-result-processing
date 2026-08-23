<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('previous_bcs_repository_datasets', function (Blueprint $table): void {
            $table->char('dataset_hash', 64)->nullable()->after('failure_message');
            $table->timestamp('validated_at')->nullable()->after('staged_at');
            $table->foreignId('validated_by')->nullable()->after('validated_at')
                ->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('validated_by');
            $table->foreignId('approved_by')->nullable()->after('approved_at')
                ->constrained('users')->nullOnDelete();
        });

        Schema::table('previous_bcs_repository_rows', function (Blueprint $table): void {
            $table->json('validation_warnings')->nullable()->after('validation_errors');
        });
    }

    public function down(): void
    {
        Schema::table('previous_bcs_repository_rows', function (Blueprint $table): void {
            $table->dropColumn('validation_warnings');
        });

        Schema::table('previous_bcs_repository_datasets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn('approved_at');
            $table->dropConstrainedForeignId('validated_by');
            $table->dropColumn(['validated_at', 'dataset_hash']);
        });
    }
};
