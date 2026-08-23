<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('exam');

        $schema->create('choice_optimization_historical_sources', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('previous_bcs_number')->unique('co_hist_src_bcs_uq');
            $table->unsignedBigInteger('repository_dataset_id');
            $table->unsignedInteger('repository_dataset_version');
            $table->char('repository_dataset_hash', 64);
            $table->string('status', 32)->default('not_pulled')->index('co_hist_src_status_idx');
            $table->unsignedInteger('candidate_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('review_count')->default(0);
            $table->unsignedInteger('no_match_count')->default(0);
            $table->string('matching_algorithm', 40)->default('co4c1-core-v1');
            $table->text('failure_message')->nullable();
            $table->unsignedBigInteger('last_pulled_by')->nullable()->index('co_hist_src_actor_idx');
            $table->timestamp('last_pulled_at')->nullable()->index('co_hist_src_time_idx');
            $table->timestamps();
        });

        $schema->create('choice_optimization_historical_matches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('historical_source_id');
            $table->unsignedBigInteger('registration_id');
            $table->string('current_reg', 32);
            $table->unsignedSmallInteger('previous_bcs_number');
            $table->unsignedBigInteger('repository_dataset_id');
            $table->unsignedBigInteger('repository_row_id');
            $table->string('previous_reg', 40)->nullable();
            $table->string('previous_name')->nullable();
            $table->string('previous_fname')->nullable();
            $table->string('previous_mname')->nullable();
            $table->string('previous_cadre', 80)->nullable();
            $table->string('match_status', 24)->index('co_hist_match_status_idx');
            $table->string('match_method', 60);
            $table->json('match_evidence')->nullable();
            $table->timestamps();

            $table->index('historical_source_id', 'co_hist_match_source_idx');
            $table->index('registration_id', 'co_hist_match_reg_id_idx');
            $table->index('current_reg', 'co_hist_match_current_reg_idx');
            $table->index('previous_reg', 'co_hist_match_prev_reg_idx');
            $table->index('previous_cadre', 'co_hist_match_cadre_idx');
            $table->unique(
                ['historical_source_id', 'registration_id', 'repository_row_id'],
                'co_hist_match_source_reg_row_uq'
            );
        });
    }

    public function down(): void
    {
        $schema = Schema::connection('exam');
        $schema->dropIfExists('choice_optimization_historical_matches');
        $schema->dropIfExists('choice_optimization_historical_sources');
    }
};
