<?php
return [
 'queue'=>env('CIRCULAR_QUEUE','imports'),
 'code_delimiter'=>'|',
 'headers'=>['cadre_serial','sub_serial','cadre_code','sub_cadre_code','cadre_type','post_count','bachelor_subject_codes','prs_codes','status','note'],
 'required_headers'=>['cadre_serial','cadre_code','cadre_type','post_count','status'],
 'staging_chunk_size'=>(int)env('CIRCULAR_STAGING_CHUNK_SIZE',1000),
 'validation_chunk_size'=>(int)env('CIRCULAR_VALIDATION_CHUNK_SIZE',1000),
];
