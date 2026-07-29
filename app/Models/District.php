<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
/** Central district code master; division_code is deliberately stable across examination databases. */
final class District extends Model { protected $fillable=['code','division_code','name','name_bn','is_active']; protected function casts(): array{return ['is_active'=>'boolean'];} }
