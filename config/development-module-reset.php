<?php

return [
    'allowed_environments' => ['local', 'development'],

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
            // Keep this list in exact sync with examination migrations owned by the
            // Circular module. Shared/foundation tables are intentionally not
            // duplicated here unless the migration-registry contract owns them.
            'tables' => [
                'circular_authority_previews',
                'circular_confirmations',
                'circular_import_batches',
                'circular_import_staging',
            ],
        ],

        'choice_validation' => [
            'label' => 'Choice Validation',
            'tables' => [
                'choice_validation_import_batches',
                'choice_validation_import_staging',
                'choice_validation_sources',
                'choice_validation_source_items',
                'choice_validation_processing_states',
                'choice_validation_processing_audits',
                'choice_validation_runs',
                'choice_validation_results',
                'choice_validation_items',
                'choice_validation_manual_corrections',
                'choice_validation_finalization_runs',
            ],
            'scoped_deletes' => [
                ['table' => 'import_correction_entries', 'column' => 'module', 'values' => ['choice_validation']],
            ],
            'downstream' => ['Tabulation, Merit, Choice Optimization and Allocation'],
        ],
    ],
];
