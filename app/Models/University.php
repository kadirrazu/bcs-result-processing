<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Central university/institution code master used during registration import validation. */
final class University extends Model { protected $fillable=['code','name','name_bn','is_active']; protected function casts(): array{return ['is_active'=>'boolean'];} }
