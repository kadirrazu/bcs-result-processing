<?php
namespace App\Models;
final class HistoricalPunishmentScreeningMatch extends ExaminationModel
{
    protected $guarded=[];
    protected function casts(): array { return ['evidence'=>'array','punishment_active'=>'boolean','reviewed_at'=>'datetime']; }
}
