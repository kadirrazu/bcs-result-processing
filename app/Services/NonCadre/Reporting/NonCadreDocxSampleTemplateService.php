<?php

namespace App\Services\NonCadre\Reporting;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/** Builds an operator-ready DOCX sample template from the current finalized NC1 Circular order. */
final class NonCadreDocxSampleTemplateService
{
    public function __construct(private readonly NonCadreReportingService $reports) {}

    /** @return array{0:string,1:string} */
    public function build(object $run, ?string $examName = null): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP ZIP extension is required to create the Non-Cadre DOCX sample template.');
        }

        $directory = storage_path('app/private/non-cadre-reporting-samples');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'nc5-docx-sample-'.uniqid('', true).'.docx';

        $document = $this->documentXml($examName, $this->reports->posts());
        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the Non-Cadre DOCX sample template.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/styles.xml', $this->stylesXml());
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRelationshipsXml());
        $zip->close();

        return [$path, 'non-cadre-nc5-docx-sample-template-'.now()->format('Ymd-His').'.docx'];
    }

    private function documentXml(?string $examName, Collection $posts): string
    {
        $body = $this->paragraph('[[EXAM_NAME]]', true, 'center')
            .$this->paragraph('চূড়ান্ত নন-ক্যাডার বরাদ্দ ফলাফল', true, 'center')
            .$this->paragraph('');

        foreach ($posts->groupBy(fn ($post) => (string) ($post->post_grade ?? '')) as $grade => $gradePosts) {
            $body .= $this->paragraph('গ্রেড '.$this->banglaNumber((string) $grade), true);
            $body .= $this->table($gradePosts);
            $body .= $this->paragraph('');
        }

        $body .= $this->paragraph('সর্বমোট বরাদ্দ: [[TOTAL_ALLOCATED]]', true);
        $body .= $this->paragraph('ফল প্রকাশের তারিখ: [[RESULT_DATE]]');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            .$body
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>'
            .'</w:body></w:document>';
    }

    private function table(Collection $posts): string
    {
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
            .'<w:top w:val="single" w:sz="4" w:color="B8C0CC"/><w:left w:val="single" w:sz="4" w:color="B8C0CC"/>'
            .'<w:bottom w:val="single" w:sz="4" w:color="B8C0CC"/><w:right w:val="single" w:sz="4" w:color="B8C0CC"/>'
            .'<w:insideH w:val="single" w:sz="4" w:color="D8DEE8"/><w:insideV w:val="single" w:sz="4" w:color="D8DEE8"/>'
            .'</w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="1200"/><w:gridCol w:w="8700"/></w:tblGrid>';

        foreach ($posts->groupBy(fn ($post) => (string) $post->post_serial) as $serial => $serialPosts) {
            $serialPosts = $serialPosts->sortBy(fn ($post) => sprintf('%08d-%s', (int) ($post->post_sub_serial ?? -1) + 1, (string) $post->post_code))->values();
            $secondCell = '';

            foreach ($serialPosts as $post) {
                $sub = $post->post_sub_serial;
                $label = $sub !== null && $sub !== ''
                    ? $this->banglaNumber((string) $serial).'.'.$this->banglaNumber((string) $sub).'। '
                    : '';
                $ministry = trim((string) ($post->ministry_bn ?? '')) ?: '—';
                $entity = trim((string) ($post->entity_bn ?? '')) ?: '—';
                $postTitle = trim((string) ($post->post_title_bn ?? '')) ?: '—';

                if ($label !== '') {
                    $secondCell .= $this->paragraph($label, true);
                }
                $secondCell .= $this->paragraph('মন্ত্রণালয়ঃ '.$ministry, true);
                $secondCell .= $this->paragraph('সংস্থাঃ '.$entity, true);
                $secondCell .= $this->paragraph('পদের নামঃ '.$postTitle, true);
                $secondCell .= $this->paragraph('পোস্ট কোড: '.(string) $post->post_code);
                $secondCell .= $this->paragraph('[[POST_'.$this->key((string) $post->post_code).']]');
                $secondCell .= $this->paragraph('[[TOTAL_'.$this->key((string) $post->post_code).']]', true);
            }

            $xml .= '<w:tr>'.$this->cell($this->banglaNumber((string) $serial).'।', 1200, true).$this->cellXml($secondCell, 8700).'</w:tr>';
        }

        return $xml.'</w:tbl>';
    }

    private function key(string $postCode): string
    {
        return preg_replace('/[^A-Z0-9]+/', '_', strtoupper($postCode)) ?: 'POST';
    }

    private function cell(string $text, int $width, bool $bold): string
    {
        return $this->cellXml($this->paragraph($text, $bold), $width);
    }

    private function cellXml(string $innerXml, int $width): string
    {
        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/><w:vAlign w:val="top"/></w:tcPr>'.$innerXml.'</w:tc>';
    }

    private function paragraph(string $text, bool $bold = false, ?string $align = 'left'): string
    {
        $pPr = $align ? '<w:pPr><w:jc w:val="'.$align.'"/></w:pPr>' : '';
        $isBangla = preg_match('/[\x{0980}-\x{09FF}]/u', $text) === 1;
        $font = $isBangla ? 'Nikosh' : 'Times New Roman';
        $size = $isBangla ? 24 : 22;
        $rPr = '<w:rPr><w:rFonts w:ascii="'.$font.'" w:hAnsi="'.$font.'" w:eastAsia="'.$font.'" w:cs="'.$font.'"/>'
            .($bold ? '<w:b/><w:bCs/>' : '').'<w:sz w:val="'.$size.'"/><w:szCs w:val="'.$size.'"/></w:rPr>';
        return '<w:p>'.$pPr.'<w:r>'.$rPr.'<w:t xml:space="preserve">'.$this->xml($text).'</w:t></w:r></w:p>';
    }

    private function xml(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    private function banglaNumber(string $number): string { return strtr($number, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']); }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/><Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/></Types>';
    }
    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>';
    }
    private function documentRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    }
    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/><w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:cs="Times New Roman"/><w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:style></w:styles>';
    }
}
