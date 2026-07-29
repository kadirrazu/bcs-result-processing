<?php
namespace App\Services\Registrations;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
/** Restrict PhpSpreadsheet to one bounded row window. */
final class RegistrationChunkReadFilter implements IReadFilter
{
 public function __construct(private int $startRow,private int $endRow){}
 public function readCell(string $columnAddress,int $row,string $worksheetName=''): bool{return $row===1||($row>=$this->startRow&&$row<=$this->endRow);}
}
