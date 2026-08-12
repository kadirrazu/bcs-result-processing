<?php
namespace App\Services\Tabulation;
use App\Services\Written\WrittenSubjectConfig;
final class TabulationRuleConfig
{
    public function __construct(private readonly WrittenSubjectConfig $written){}
    public function generalWrittenFullMark():float{return $this->written->trackFullMark('general');}
    public function technicalWrittenFullMark():float{return $this->written->trackFullMark('technical');}
    public function vivaFullMark():float{return (float)config('viva.full_mark',100);}
    public function generalGrandFullMark():float{return $this->generalWrittenFullMark()+$this->vivaFullMark();}
    public function technicalGrandFullMark():float{return $this->technicalWrittenFullMark()+$this->vivaFullMark();}
    public function grandTotalReviewPercent():float{return (float)config('tabulation.grand_total_review_percent',75);}
    public function processingChunkSize():int{return max(100,(int)config('tabulation.processing_chunk_size',1000));}
    public function snapshot():array{return[
        'general_written_full_mark'=>$this->generalWrittenFullMark(),
        'technical_written_full_mark'=>$this->technicalWrittenFullMark(),
        'viva_full_mark'=>$this->vivaFullMark(),
        'general_grand_full_mark'=>$this->generalGrandFullMark(),
        'technical_grand_full_mark'=>$this->technicalGrandFullMark(),
        'grand_total_review_percent'=>$this->grandTotalReviewPercent(),
    ];}
}
