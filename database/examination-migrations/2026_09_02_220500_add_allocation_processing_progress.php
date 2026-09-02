<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('exam')->table('allocation_processing_states', function (Blueprint $table): void {
            $table->string('phase', 60)->nullable()->after('status');
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('phase');
            $table->unsignedInteger('progress_current')->default(0)->after('progress_percent');
            $table->unsignedInteger('progress_total')->default(0)->after('progress_current');
            $table->string('progress_message', 500)->nullable()->after('progress_total');
            $table->text('last_error')->nullable()->after('progress_message');
        });
    }

    public function down(): void
    {
        Schema::connection('exam')->table('allocation_processing_states', function (Blueprint $table): void {
            $table->dropColumn(['phase','progress_percent','progress_current','progress_total','progress_message','last_error']);
        });
    }
};
