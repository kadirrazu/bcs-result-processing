<?php

return [
    'allowed_environments' => ['local', 'development'],

    // Large development resets are intentionally deleted in committed chunks.
    // This avoids one very large InnoDB undo/redo transaction while preserving
    // table dependency order and without disabling foreign-key checks.
    'delete_chunk_size' => max(
        1000,
        (int) env('EXAMINATION_RESET_DELETE_CHUNK_SIZE', 10000)
    ),

    'modules' => [
        'registration' => [
            'label' => 'Registration',
            'tables' => [
                'registration_audits',
                'registration_import_rows',
                'registration_import_staging',
                'registrations',
                'registration_import_batches',
            ],
            'scoped_deletes' => [
                ['table' => 'import_correction_entries', 'column' => 'module', 'values' => ['registration']],
            ],
            'downstream' => ['Preliminary', 'Written', 'Viva and later modules'],
        ],

        'preliminary' => [
            'label' => 'Preliminary',
            'tables' => [
                'preliminary_processing_audits',
                'preliminary_finalization_runs',
                'preliminary_cutoff_decisions',
                'preliminary_distribution_reports',
                'preliminary_reconciliation_reports',
                'preliminary_results',
                'preliminary_import_staging',
                'preliminary_processing_states',
                'preliminary_import_batches',
            ],
            'scoped_deletes' => [
                ['table' => 'import_correction_entries', 'column' => 'module', 'values' => ['preliminary']],
            ],
            'downstream' => ['Written', 'Viva and later modules'],
        ],

        'written' => [
            'label' => 'Written',
            'tables' => [
                'written_processing_audits',
                'written_candidate_marks',
                'written_processing_runs',
                'written_reconciliation_reports',
                'written_results',
                'written_import_staging',
                'written_processing_states',
                'written_import_batches',
            ],
            'scoped_deletes' => [
                ['table' => 'import_correction_entries', 'column' => 'module', 'values' => ['written']],
            ],
            'downstream' => ['Viva and later modules'],
        ],

        'viva' => [
            'label' => 'Viva',
            'tables' => [
                'viva_processing_audits',
                'viva_finalization_runs',
                'viva_processing_runs',
                'viva_reconciliation_runs',
                'viva_results',
                'viva_board_import_staging',
                'viva_candidate_mappings',
                'viva_mapping_import_staging',
                'viva_processing_states',
                'viva_import_batches',
            ],
            'scoped_deletes' => [
                ['table' => 'import_correction_entries', 'column' => 'module', 'values' => ['viva_mapping', 'viva_board']],
            ],
            'downstream' => ['Tabulation, Merit and later modules'],
        ],

        'circular' => [
            'label' => 'Circular',
            // Child/dependent tables are intentionally listed before their parents
            // so reset progress reflects actual row deletes instead of hidden FK cascades.
            'tables' => [
                'circular_confirmations',
                'circular_authority_previews',
                'circular_import_staging',
                'circular_import_batches',
                'circular_processing_audits',
                'circular_entry_prs',
                'circular_entry_bachelor_subjects',
                'circular_entries',
                'circular_processing_states',
            ],
        ],

        'choice_validation' => [
            'label' => 'Choice Validation',
            // Delete FK children before parents. This avoids cascade-deleting rows
            // behind the progress bar and keeps the row-based reset count accurate.
            'tables' => [
                'choice_validation_finalization_runs',
                'choice_validation_items',
                'choice_validation_results',
                'choice_validation_manual_corrections',
                'choice_validation_source_items',
                'choice_validation_sources',
                'choice_validation_import_staging',
                'choice_validation_import_batches',
                'choice_validation_processing_audits',
                'choice_validation_runs',
                'choice_validation_processing_states',
            ],
            'scoped_deletes' => [
                ['table' => 'import_correction_entries', 'column' => 'module', 'values' => ['choice_validation']],
            ],
            'downstream' => ['Merit, Choice Optimization and Allocation'],
        ],

        'tabulation' => [
            'label' => 'Tabulation',
            'tables' => [
                'tabulation_processing_audits',
                'tabulation_finalization_runs',
                'tabulation_results',
                'tabulation_processing_runs',
                'tabulation_processing_states',
            ],
            'downstream' => ['Merit and later modules'],
        ],

        'merit' => [
            'label' => 'Merit Generation',
            'tables' => [
                'merit_processing_audits',
                'merit_finalization_runs',
                'merit_cadre_ranks',
                'merit_results',
                'merit_processing_runs',
                'merit_processing_states',
            ],
            'downstream' => ['Choice Optimization and Allocation'],
        ],

        'choice_optimization' => [
            'label' => 'Choice Optimization',
            'tables' => [
                'choice_optimization_consolidated_historical_recommendations',
                'choice_optimization_google_form_recommendations',
                'choice_optimization_google_form_rows',
                'choice_optimization_google_form_batches',
                'choice_optimization_historical_choices',
                'choice_optimization_historical_matches',
                'choice_optimization_historical_sources',
                'choice_optimization_effective_choices',
                'choice_optimization_omr_staging',
                'choice_optimization_omr_batches',
                'choice_optimization_processing_audits',
                'choice_optimization_processing_states',
                'choice_optimization_settings',
            ],
            'downstream' => ['Allocation'],
        ],


        'allocation' => [
            'label' => 'Allocation',
            'tables' => [
                'allocation_processing_audits',
                'allocation_decision_events',
                'allocation_seat_ledgers',
                'allocation_results',
                'allocation_runs',
                'allocation_input_queue_entries',
                'allocation_input_candidates',
                'allocation_input_freezes',
                'allocation_seat_breakup_rows',
                'allocation_seat_breakup_versions',
                'allocation_processing_states',
                'allocation_settings',
            ],
            'downstream' => ['Reports and future Non-Cadre Allocation'],
        ],





    ],
];
