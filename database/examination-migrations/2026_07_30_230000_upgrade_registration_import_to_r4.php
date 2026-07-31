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
            $table->unsignedBigInteger('examination_id')->nullable()->after('id');
            $table->unsignedInteger('processed_rows')->default(0)->after('total_rows');
            $table->unsignedInteger('current_row')->default(0)->after('processed_rows');
            $table->unsignedInteger('chunk_size')->default(1000)->after('current_row');
            $table->unsignedInteger('current_chunk')->default(0)->after('chunk_size');
            $table->unsignedInteger('total_chunks')->default(0)->after('current_chunk');
            $table->decimal('progress_percent', 7, 4)->default(0)->after('total_chunks');
            $table->text('failure_message')->nullable()->after('error_file');
            $table->timestamp('queued_at')->nullable()->after('started_at');
            $table->timestamp('heartbeat_at')->nullable()->after('queued_at');
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('registration_import_batches', function (Blueprint $table): void {
            $table->dropIndex(['status', 'created_at']);
            $table->dropColumn([
                'examination_id', 'processed_rows', 'current_row', 'chunk_size',
                'current_chunk', 'total_chunks', 'progress_percent', 'failure_message',
                'queued_at', 'heartbeat_at',
            ]);
        });
    }
};
