<?php
namespace App\Models;
final class ChoiceValidationRun extends ExaminationModel { protected $guarded=[]; protected function casts():array{return ['source_version'=>'integer','validation_version'=>'integer','circular_version'=>'integer','progress_percent'=>'float','started_at'=>'datetime','finished_at'=>'datetime'];} }
