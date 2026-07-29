<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var string */
    protected $connection = 'exam';

    /**
     * Create registration import tracking and candidate identity tables.
     */
    public function up(): void
    {
        Schema::connection($this->connection)->create(
            'registration_import_batches',
            function (Blueprint $table): void {
                $table->id();
                $table->string('original_name');
                $table->string('stored_name')->nullable();
                $table->string('status', 20)->default('processing')->index();
                $table->unsignedInteger('total_rows')->default(0);
                $table->unsignedInteger('inserted_rows')->default(0);
                $table->unsignedInteger('updated_rows')->default(0);
                $table->unsignedInteger('failed_rows')->default(0);
                $table->timestamp('started_at')->nullable();
                $table->timestamp('finished_at')->nullable();
                $table->string('error_file')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();
            }
        );

        Schema::connection($this->connection)->create(
            'registrations',
            function (Blueprint $table): void {
                $table->id();

                // Immutable source identities used by all later processing stages.
                $table->string('user_id', 10)->unique();
                $table->string('reg', 8)->unique();
                $table->string('national_id', 25)->nullable()->index();

                $table->string('name', 150);
                $table->string('father_name', 150)->nullable();
                $table->string('mother_name', 150)->nullable();
                $table->string('name_bn', 200)->nullable();
                $table->string('father_name_bn', 200)->nullable();
                $table->string('mother_name_bn', 200)->nullable();
                $table->date('birth_date')->nullable();

                // Stable codes point to masters stored in the central database.
                $table->unsignedTinyInteger('sex_code')->nullable()->index();
                $table->unsignedSmallInteger('district_code')->nullable()->index();
                $table->unsignedSmallInteger('division_code')->nullable()->index();
                $table->unsignedInteger('university_code')->nullable()->index();
                $table->unsignedInteger('bachelor_subject_code')->nullable()->index();
                $table->unsignedInteger('related_subject_code')->nullable()->index();

                // 1 = GG, 2 = TT, 3 = GT. Text codes are resolved in the enum.
                $table->unsignedTinyInteger('cadre_category')->index();

                // Preserve raw source values; do not coerce them to booleans.
                $table->unsignedSmallInteger('has_ff_quota')->nullable();
                $table->unsignedSmallInteger('has_em_quota')->nullable();
                $table->unsignedSmallInteger('has_phc_quota')->nullable();
                $table->boolean('has_quota')->default(false)->index();

                $table->string('status', 20)->default('active')->index();
                $table->string('validation_status', 20)->default('pending')->index();
                $table->text('comment')->nullable();
                $table->unsignedBigInteger('source_batch_id')->nullable()->index();
                $table->timestamps();

                // Processing and reporting indexes for 300,000+ rows.
                $table->index(['status', 'cadre_category']);
                $table->index(['status', 'has_quota']);
                $table->index(['district_code', 'sex_code']);
            }
        );
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('registrations');
        Schema::connection($this->connection)->dropIfExists('registration_import_batches');
    }
};
