<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('historical_punishments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('bcs');
            $table->string('reg', 40)->nullable();
            $table->string('name')->nullable();
            $table->string('fname')->nullable();
            $table->string('mname')->nullable();
            $table->date('b_date')->nullable();
            $table->date('dob')->nullable();
            $table->string('dist_name')->nullable();
            $table->string('ssc_roll', 80)->nullable();
            $table->unsignedSmallInteger('ssc_year')->nullable();
            $table->string('hsc_roll', 80)->nullable();
            $table->unsignedSmallInteger('hsc_year')->nullable();
            $table->string('nid_no', 80)->nullable();
            $table->boolean('is_lifetime')->default(false);
            $table->date('punishment_start')->nullable();
            $table->date('punishment_end')->nullable();
            $table->text('punishment_reason')->nullable();
            $table->string('source_type', 20)->default('manual');
            $table->string('source_file')->nullable();
            $table->unsignedInteger('source_row')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['ssc_roll','ssc_year','b_date'], 'hist_pun_core_idx');
            $table->index(['bcs','reg'], 'hist_pun_bcs_reg_idx');
            $table->index('is_lifetime', 'hist_pun_lifetime_idx');
        });

        Schema::create('historical_punishment_audits', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('historical_punishment_id')->nullable()->constrained('historical_punishments')->nullOnDelete();
            $table->string('action', 50);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historical_punishment_audits');
        Schema::dropIfExists('historical_punishments');
    }
};
