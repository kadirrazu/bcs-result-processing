<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'exam';

    public function up(): void
    {
        Schema::connection($this->connection)->table('registration_import_staging', function (Blueprint $table): void {
            // These standalone indexes duplicate the useful suffix of the batch-scoped
            // indexes below and add substantial B-tree maintenance during 100K+ imports.
            // Workflow-critical indexes remain untouched:
            //   UNIQUE(batch_id, source_row)
            //   INDEX(batch_id, validation_status)
            //   INDEX(batch_id, reg)
            //   INDEX(batch_id, user_id)
            $table->dropIndex('registration_import_staging_reg_index');
            $table->dropIndex('registration_import_staging_user_id_index');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('registration_import_staging', function (Blueprint $table): void {
            $table->index(['reg']);
            $table->index(['user_id']);
        });
    }
};
