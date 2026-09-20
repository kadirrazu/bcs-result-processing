<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('exam')->table('non_cadre_choice_imports', function (Blueprint $table): void {
            $table->string('stored_filename')->nullable()->after('source_filename');
            $table->unsignedInteger('processed_rows')->default(0)->after('total_rows');
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('invalid_rows');
            $table->text('failure_message')->nullable()->after('progress_percent');
            $table->timestamp('queued_at')->nullable()->after('failure_message');
            $table->timestamp('started_at')->nullable()->after('queued_at');
            $table->timestamp('finished_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::connection('exam')->table('non_cadre_choice_imports', function (Blueprint $table): void {
            $table->dropColumn(['stored_filename','processed_rows','progress_percent','failure_message','queued_at','started_at','finished_at']);
        });
    }
};
