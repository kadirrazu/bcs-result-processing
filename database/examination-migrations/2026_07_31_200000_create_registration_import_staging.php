<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'exam';

    public function up(): void
    {
        Schema::connection($this->connection)->table('registration_import_batches', function (Blueprint $table): void {
            $table->unsignedInteger('staged_rows')->default(0)->after('processed_rows');
            $table->unsignedInteger('valid_rows')->default(0)->after('warning_rows');
            $table->unsignedInteger('invalid_rows')->default(0)->after('valid_rows');
            $table->unsignedInteger('approved_rows')->default(0)->after('invalid_rows');
            $table->timestamp('validated_at')->nullable()->after('finished_at');
            $table->timestamp('approved_at')->nullable()->after('validated_at');
            $table->unsignedBigInteger('approved_by')->nullable()->after('approved_at');
        });

        Schema::connection($this->connection)->create('registration_import_staging', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('batch_id')->constrained('registration_import_batches')->cascadeOnDelete();
            $table->unsignedInteger('source_row');

            // Raw source values are preserved exactly enough for validation/reporting.
            $table->string('user_id', 50)->nullable();
            $table->string('reg', 50)->nullable();
            $table->string('national_id', 50)->nullable();
            $table->string('name', 255)->nullable();
            $table->string('father_name', 255)->nullable();
            $table->string('mother_name', 255)->nullable();
            $table->string('name_bn', 255)->nullable();
            $table->string('father_name_bn', 255)->nullable();
            $table->string('mother_name_bn', 255)->nullable();
            $table->string('raw_birth_date', 32)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('sex_code', 20)->nullable();
            $table->string('district_code', 20)->nullable();
            $table->string('division_code', 20)->nullable();
            $table->string('university_code', 20)->nullable();
            $table->string('bachelor_subject_code', 20)->nullable();
            $table->string('post_related_subject_code', 20)->nullable();
            $table->string('cadre_category', 20)->nullable();
            $table->string('has_ff_quota', 20)->nullable();
            $table->string('has_em_quota', 20)->nullable();
            $table->string('has_phc_quota', 20)->nullable();
            $table->boolean('has_quota')->default(false);
            $table->string('candidate_status', 30)->nullable();
            $table->text('comment')->nullable();

            $table->string('validation_status', 20)->default('pending');
            $table->json('validation_errors')->nullable();
            $table->json('validation_warnings')->nullable();
            $table->unsignedBigInteger('registration_id')->nullable();
            $table->timestamps();

            $table->unique(['batch_id', 'source_row']);
            $table->index(['batch_id', 'validation_status']);
            $table->index(['batch_id', 'reg']);
            $table->index(['batch_id', 'user_id']);
            $table->index(['reg']);
            $table->index(['user_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('registration_import_staging');

        Schema::connection($this->connection)->table('registration_import_batches', function (Blueprint $table): void {
            $table->dropColumn([
                'staged_rows', 'valid_rows', 'invalid_rows', 'approved_rows',
                'validated_at', 'approved_at', 'approved_by',
            ]);
        });
    }
};
