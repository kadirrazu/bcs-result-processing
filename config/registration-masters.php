<?php
return [
 'genders'=>['title'=>'Sex / Gender','model'=>App\Models\Gender::class,'fields'=>['code','name','name_bn']],
 'divisions'=>['title'=>'Divisions','model'=>App\Models\Division::class,'fields'=>['code','name','name_bn']],
 'districts'=>['title'=>'Districts','model'=>App\Models\District::class,'fields'=>['code','division_code','name','name_bn']],
 'universities'=>['title'=>'Universities','model'=>App\Models\University::class,'fields'=>['code','name','name_bn']],
];
