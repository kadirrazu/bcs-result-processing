<?php
namespace App\Models;
final class HistoricalPunishmentScreeningRun extends ExaminationModel
{
    public $timestamps=false; protected $guarded=[];
    protected function casts(): array { return ['reference_date'=>'date','created_at'=>'datetime','started_at'=>'datetime','finished_at'=>'datetime']; }
    public function progressPercent(): int { if ($this->candidate_count <= 0) return $this->status === 'completed' ? 100 : 0; return min(100, (int) floor(($this->processed_count / $this->candidate_count) * 100)); }
}
