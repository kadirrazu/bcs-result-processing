<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Central gender/sex code master. */
final class Gender extends Model { protected $fillable=['code','name','name_bn','is_active']; protected function casts(): array{return ['is_active'=>'boolean'];} }
