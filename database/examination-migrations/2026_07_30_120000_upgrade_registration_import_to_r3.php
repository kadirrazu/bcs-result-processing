<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'exam';

    public function up(): void
    {
        Schema::connection($this->connection)->table('registrations', function (Blueprint $table): void {
            $table->renameColumn('related_subject_code', 'post_related_subject_code');
        });

        Schema::connection($this->connection)->table('registration_import_batches', function (Blueprint $table): void {
            $table->unsignedInteger('warning_rows')->default(0)->after('failed_rows');
            $table->unsignedInteger('identity_conflict_rows')->default(0)->after('warning_rows');
            $table->timestamp('rolled_back_at')->nullable()->after('finished_at');
            $table->unsignedBigInteger('rolled_back_by')->nullable()->after('rolled_back_at');
            $table->text('rollback_reason')->nullable()->after('rolled_back_by');
        });

        Schema::connection($this->connection)->create('registration_import_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('registration_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('source_row');
            $table->unsignedBigInteger('registration_id')->nullable()->index();
            $table->string('reg', 8)->nullable()->index();
            $table->string('user_id', 10)->nullable()->index();
            $table->string('action', 20)->index();
            $table->json('warnings')->nullable();
            $table->json('errors')->nullable();
            $table->json('before_data')->nullable();
            $table->json('after_data')->nullable();
            $table->timestamps();
            $table->unique(['batch_id', 'source_row']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('registration_import_rows');

        Schema::connection($this->connection)->table('registration_import_batches', function (Blueprint $table): void {
            $table->dropColumn([
                'warning_rows', 'identity_conflict_rows', 'rolled_back_at',
                'rolled_back_by', 'rollback_reason',
            ]);
        });

        Schema::connection($this->connection)->table('registrations', function (Blueprint $table): void {
            $table->renameColumn('post_related_subject_code', 'related_subject_code');
        });
    }
};
