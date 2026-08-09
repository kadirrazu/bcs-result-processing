<?php
namespace App\Enums;
enum CircularProcessingStatus:string
{
 case NotStarted='not_started'; case Draft='draft'; case Imported='imported'; case Validated='validated'; case Approved='approved'; case PreviewGenerated='preview_generated'; case Confirmed='confirmed'; case Finalized='finalized'; case Stale='stale';
 public function label():string{return match($this){self::NotStarted=>'Not started',self::Draft=>'Draft',self::Imported=>'Imported',self::Validated=>'Validated',self::Approved=>'Approved',self::PreviewGenerated=>'Preview generated',self::Confirmed=>'Confirmed',self::Finalized=>'Finalized',self::Stale=>'Outdated'};}
}
