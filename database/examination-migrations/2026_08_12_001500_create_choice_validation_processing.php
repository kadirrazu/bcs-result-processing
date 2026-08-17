<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        $schema=Schema::connection('exam');
        $schema->create('choice_validation_runs',function(Blueprint $table):void{
            $table->id();$table->unsignedInteger('source_version')->index();$table->unsignedInteger('validation_version')->index();$table->unsignedInteger('circular_version')->index();
            $table->string('status',80)->default('queued')->index();$table->unsignedInteger('total_candidates')->default(0);$table->unsignedInteger('processed_candidates')->default(0);
            $table->unsignedInteger('valid_candidates')->default(0);$table->unsignedInteger('not_applicable_candidates')->default(0);$table->unsignedInteger('zero_valid_choice_candidates')->default(0);
            $table->unsignedInteger('kept_choices')->default(0);$table->unsignedInteger('removed_choices')->default(0);$table->unsignedInteger('expanded_choices')->default(0);
            $table->decimal('progress_percent',7,4)->default(0);$table->unsignedBigInteger('started_by')->nullable()->index();$table->timestamp('started_at')->nullable();$table->timestamp('finished_at')->nullable();$table->text('failure_message')->nullable();$table->timestamps();
            $table->unique(['source_version','validation_version'],'cv_runs_src_val_uq');
        });
        $schema->create('choice_validation_results',function(Blueprint $table):void{
            $table->id();$table->unsignedBigInteger('choice_source_id');$table->foreign('choice_source_id','cv_results_source_fk')->references('id')->on('choice_validation_sources')->cascadeOnDelete();
            $table->unsignedBigInteger('registration_id')->index();$table->string('reg',32)->index();$table->string('user_id',64)->nullable()->index();$table->unsignedInteger('source_version')->index();$table->unsignedInteger('validation_version')->index();$table->unsignedInteger('circular_version')->index();
            $table->string('written_qualified_track',4)->nullable()->index();$table->string('effective_track',16)->nullable()->index();$table->string('status',32)->index();$table->string('result_reason_code',80)->nullable()->index();
            $table->json('validated_choice_codes')->nullable();$table->unsignedInteger('original_choice_count')->default(0);$table->unsignedInteger('validated_choice_count')->default(0);$table->unsignedInteger('removed_choice_count')->default(0);$table->unsignedInteger('expanded_choice_count')->default(0);
            $table->json('eligibility_snapshot')->nullable();$table->unsignedBigInteger('processing_run_id')->index();$table->timestamp('processed_at')->nullable();$table->timestamps();
            $table->unique(['registration_id','validation_version'],'cv_results_reg_val_uq');
        });
        $schema->create('choice_validation_items',function(Blueprint $table):void{
            $table->id();$table->unsignedBigInteger('choice_validation_result_id');$table->foreign('choice_validation_result_id','cv_items_result_fk')->references('id')->on('choice_validation_results')->cascadeOnDelete();
            $table->unsignedInteger('source_position');$table->string('source_column',32);$table->string('source_code',40)->nullable();$table->string('resolved_type',16)->default('unknown')->index();$table->unsignedBigInteger('resolved_cadre_id')->nullable();$table->unsignedBigInteger('resolved_sub_cadre_id')->nullable();
            $table->string('result',16)->index();$table->string('reason_code',80)->nullable()->index();$table->text('reason_message')->nullable();$table->unsignedInteger('output_position')->nullable();$table->string('output_code',40)->nullable()->index();$table->string('expanded_from_code',40)->nullable();$table->unsignedBigInteger('circular_entry_id')->nullable()->index();$table->json('eligibility_snapshot')->nullable();$table->timestamps();
            $table->index(['choice_validation_result_id','source_position'],'cv_items_result_src_idx');
        });
        $schema->table('choice_validation_processing_states',function(Blueprint $table):void{
            $table->unsignedInteger('current_validation_version')->default(0)->after('approved_source_version');$table->unsignedBigInteger('latest_validation_run_id')->nullable()->after('current_validation_version');$table->timestamp('validation_completed_at')->nullable()->after('latest_validation_run_id');
        });
    }
    public function down(): void {
        $schema=Schema::connection('exam');
        $schema->table('choice_validation_processing_states',function(Blueprint $table):void{$table->dropColumn(['current_validation_version','latest_validation_run_id','validation_completed_at']);});
        $schema->dropIfExists('choice_validation_items');$schema->dropIfExists('choice_validation_results');$schema->dropIfExists('choice_validation_runs');
    }
};
