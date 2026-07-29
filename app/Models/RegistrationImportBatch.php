<?php
namespace App\Models;
/** Audit record for one registration spreadsheet import. */
final class RegistrationImportBatch extends ExaminationModel
{
    protected $fillable=['original_name','stored_name','status','total_rows','inserted_rows','updated_rows','failed_rows','started_at','finished_at','error_file','created_by'];
    protected function casts(): array{return ['started_at'=>'datetime','finished_at'=>'datetime'];}
}
