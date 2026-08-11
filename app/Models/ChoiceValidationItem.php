<?php
namespace App\Models;
final class ChoiceValidationItem extends ExaminationModel { protected $guarded=[]; protected function casts():array{return ['eligibility_snapshot'=>'array'];} }
