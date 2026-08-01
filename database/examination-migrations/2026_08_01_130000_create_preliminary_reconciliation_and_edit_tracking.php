<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const CONNECTION = 'exam';

    public function up(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        /*
        |--------------------------------------------------------------------------
        | This migration is intentionally partial-failure safe.
        |--------------------------------------------------------------------------
        | MySQL DDL is not fully transactional. If an earlier version of this
        | migration failed after creating one table/column, rerunning must not
        | fail with "already exists". Every object is therefore checked first.
        */
        if (! $schema->hasTable('preliminary_reconciliation_reports')) {
            $schema->create('preliminary_reconciliation_reports', function (Blueprint $table): void {
                $table->id();
                $table->unsignedBigInteger('import_batch_id')->nullable();
                $table->unsignedInteger('active_registered')->default(0);
                $table->unsignedInteger('imported_rows')->default(0);
                $table->unsignedInteger('present_with_mark')->default(0);
                $table->unsignedInteger('present_with_status_text')->default(0);
                $table->unsignedInteger('cancelled_with_reason')->default(0);
                $table->unsignedInteger('cancelled_without_reason')->default(0);
                $table->unsignedInteger('absent')->default(0);
                $table->unsignedInteger('excluded_non_active_registration')->default(0);
                $table->json('summary');
                $table->unsignedBigInteger('generated_by');
                $table->timestamp('generated_at');
                $table->timestamps();

                $table->index('import_batch_id', 'prelim_recon_import_batch_idx');
            });
        }

        if (! $schema->hasColumn('preliminary_processing_states', 'latest_reconciliation_report_id')) {
            $schema->table('preliminary_processing_states', function (Blueprint $table): void {
                $table->unsignedBigInteger('latest_reconciliation_report_id')
                    ->nullable()
                    ->after('latest_import_batch_id');

                // Explicit short index name avoids MySQL's 64-character limit.
                $table->index('latest_reconciliation_report_id', 'prelim_state_recon_idx');
            });
        }

        if (! $schema->hasColumn('preliminary_results', 'last_edited_by')) {
            $schema->table('preliminary_results', function (Blueprint $table): void {
                $table->unsignedBigInteger('last_edited_by')->nullable()->after('finalized_at');
                $table->index('last_edited_by', 'prelim_result_editor_idx');
            });
        }

        if (! $schema->hasColumn('preliminary_results', 'last_edited_at')) {
            $schema->table('preliminary_results', function (Blueprint $table): void {
                $table->timestamp('last_edited_at')->nullable()->after('last_edited_by');
            });
        }

        if (! $schema->hasColumn('preliminary_results', 'last_edit_reason')) {
            $schema->table('preliminary_results', function (Blueprint $table): void {
                $table->text('last_edit_reason')->nullable()->after('last_edited_at');
            });
        }

        if (! $schema->hasColumn('preliminary_processing_audits', 'registration_id')) {
            $schema->table('preliminary_processing_audits', function (Blueprint $table): void {
                $table->unsignedBigInteger('registration_id')->nullable()->after('processing_run_id');
                $table->index('registration_id', 'prelim_audit_registration_idx');
            });
        }

        if (! $schema->hasColumn('preliminary_processing_audits', 'preliminary_result_id')) {
            $schema->table('preliminary_processing_audits', function (Blueprint $table): void {
                $table->unsignedBigInteger('preliminary_result_id')->nullable()->after('registration_id');
                $table->index('preliminary_result_id', 'prelim_audit_result_idx');
            });
        }
    }

    public function down(): void
    {
        $schema = Schema::connection(self::CONNECTION);

        if ($schema->hasTable('preliminary_processing_audits')) {
            if ($schema->hasColumn('preliminary_processing_audits', 'preliminary_result_id')) {
                $schema->table('preliminary_processing_audits', function (Blueprint $table): void {
                    $table->dropIndex('prelim_audit_result_idx');
                    $table->dropColumn('preliminary_result_id');
                });
            }

            if ($schema->hasColumn('preliminary_processing_audits', 'registration_id')) {
                $schema->table('preliminary_processing_audits', function (Blueprint $table): void {
                    $table->dropIndex('prelim_audit_registration_idx');
                    $table->dropColumn('registration_id');
                });
            }
        }

        if ($schema->hasTable('preliminary_results')) {
            if ($schema->hasColumn('preliminary_results', 'last_edit_reason')) {
                $schema->table('preliminary_results', function (Blueprint $table): void {
                    $table->dropColumn('last_edit_reason');
                });
            }

            if ($schema->hasColumn('preliminary_results', 'last_edited_at')) {
                $schema->table('preliminary_results', function (Blueprint $table): void {
                    $table->dropColumn('last_edited_at');
                });
            }

            if ($schema->hasColumn('preliminary_results', 'last_edited_by')) {
                $schema->table('preliminary_results', function (Blueprint $table): void {
                    $table->dropIndex('prelim_result_editor_idx');
                    $table->dropColumn('last_edited_by');
                });
            }
        }

        if (
            $schema->hasTable('preliminary_processing_states')
            && $schema->hasColumn('preliminary_processing_states', 'latest_reconciliation_report_id')
        ) {
            $schema->table('preliminary_processing_states', function (Blueprint $table): void {
                $table->dropIndex('prelim_state_recon_idx');
                $table->dropColumn('latest_reconciliation_report_id');
            });
        }

        $schema->dropIfExists('preliminary_reconciliation_reports');
    }
};
