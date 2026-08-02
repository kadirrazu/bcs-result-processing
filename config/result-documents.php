<?php

return [
    'registrations_per_line' => (int) env('RESULT_DOCUMENT_REGISTRATIONS_PER_LINE', 8),
    'registration_separator' => '    ',
    'max_template_size_kb' => (int) env('RESULT_DOCUMENT_MAX_TEMPLATE_SIZE_KB', 20480),
];
