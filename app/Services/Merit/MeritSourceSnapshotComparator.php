<?php
namespace App\Services\Merit;
final class MeritSourceSnapshotComparator
{
    public function equivalent(mixed $a,mixed $b):bool{return $this->canonicalize($a)===$this->canonicalize($b);}
    private function canonicalize(mixed $value):mixed{if(!is_array($value))return $value;if(array_is_list($value))return array_map(fn($v)=>$this->canonicalize($v),$value);ksort($value);foreach($value as $k=>$v)$value[$k]=$this->canonicalize($v);return $value;}
}
