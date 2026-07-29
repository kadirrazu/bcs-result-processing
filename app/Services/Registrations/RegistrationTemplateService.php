<?php
namespace App\Services\Registrations;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
/** Build the official Excel template; headers are intentionally fixed for deterministic imports. */
final class RegistrationTemplateService
{
 public function create(string $path): void{$s=new Spreadsheet();$sh=$s->getActiveSheet();$sh->setTitle('Registrations');$sh->fromArray(config('registrations.headers'),null,'A1');$sh->fromArray(['U000000001','12345678','Example Candidate','','','1995-05-02',1,1,1,1,101,201,2,'','',null,null,null,'1234567890',3,'active','Sample row - delete before import'],null,'A2');$sh->getStyle('A1:V1')->getFont()->setBold(true);$sh->freezePane('A2');foreach(range('A','V') as $c)$sh->getColumnDimension($c)->setAutoSize(true);(new Xlsx($s))->save($path);}
}
