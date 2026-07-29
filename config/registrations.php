<?php
return [
 'chunk_size'=>(int)env('REGISTRATION_IMPORT_CHUNK_SIZE',2000),
 'headers'=>['user','reg','name','fname','mname','b_date','sex','district','division','university','b_subject','rl_subject','has_ff_quota','has_em_quota','has_phc_quota','name_bn','fname_bn','mname_bn','national_id','cadre_category','status','comment'],
];
