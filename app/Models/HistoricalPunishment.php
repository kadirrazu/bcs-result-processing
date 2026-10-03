<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
final class HistoricalPunishment extends Model
{
    protected $guarded=[];
    protected function casts(): array { return ['b_date'=>'date','dob'=>'date','is_lifetime'=>'boolean','punishment_start'=>'date','punishment_end'=>'date']; }
}
