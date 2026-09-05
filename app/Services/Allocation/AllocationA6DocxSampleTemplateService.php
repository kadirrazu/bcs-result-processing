<?php

namespace App\Services\Allocation;

use App\Models\AllocationA5Run;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/** Builds an operator-ready DOCX sample template from the current A5-bound Circular order. */
final class AllocationA6DocxSampleTemplateService
{
    public function __construct(private readonly AllocationA6ReportService $reports) {}

    /** @return array{0:string,1:string} */
    public function build(AllocationA5Run $a5, ?string $examName = null): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP ZIP extension is required to create the DOCX sample template.');
        }

        $directory = storage_path('app/private/allocation-a6-samples');
        File::ensureDirectoryExists($directory);
        $path = $directory.DIRECTORY_SEPARATOR.'a6-docx-sample-'.uniqid('', true).'.docx';

        $rows = $this->reports->cadres($a5);
        $general = $rows->where('group_rank', 0)->values();
        $technical = $rows->where('group_rank', 1)->values();

        $document = $this->documentXml($examName, $general, $technical);

        $zip = new ZipArchive();
        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the DOCX sample template.');
        }

        $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
        $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
        $zip->addFromString('word/document.xml', $document);
        $zip->addFromString('word/styles.xml', $this->stylesXml());
        $zip->addFromString('word/_rels/document.xml.rels', $this->documentRelationshipsXml());
        $zip->close();

        $name = 'allocation-a6-docx-sample-template-'.now()->format('Ymd-His').'.docx';

        return [$path, $name];
    }

    private function documentXml(?string $examName, Collection $general, Collection $technical): string
    {
        $body = '';
        $body .= $this->paragraph('[[EXAM_NAME]]', true, 28, 'center');
        $body .= $this->paragraph('চূড়ান্ত ক্যাডার বরাদ্দ ফলাফল', true, 26, 'center');
        $body .= $this->paragraph('');

        $body .= $this->paragraph('ক) সাধারণ ক্যাডারসমূহ ও ক্যাডারের পদসমূহঃ', true, 24);
        $body .= $this->table($general);
        $body .= $this->paragraph('');

        $body .= $this->paragraph('খ) প্রফেশনাল বা টেকনিক্যাল ক্যাডারসমূহ/ক্যাডারের প্রফেশনাল বা টেকনিক্যাল পদসমূহঃ', true, 24);
        $body .= $this->table($technical);
        $body .= $this->paragraph('');
        $body .= $this->paragraph('সর্বমোট বরাদ্দ: [[TOTAL_ALLOCATED]]', true, 22);
        $body .= $this->paragraph('ফল প্রকাশের তারিখ: [[RESULT_DATE]]', false, 20);
        $body .= $this->paragraph('রিপোর্ট তৈরির সময়: [REPORT_GENERATION_TIMESTAMP]', false, 18);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:body>'.$body
            .'<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1134" w:right="1134" w:bottom="1134" w:left="1134"/></w:sectPr>'
            .'</w:body></w:document>';
    }

    /**
     * Keep the Circular hierarchy visible in the editable sample:
     * one parent row per cadre_serial and left-aligned cadre_serial.sub_serial entries.
     */
    private function table(Collection $rows): string
    {
        $xml = '<w:tbl><w:tblPr><w:tblW w:w="0" w:type="auto"/><w:tblBorders>'
            .'<w:top w:val="single" w:sz="4" w:color="B8C0CC"/><w:left w:val="single" w:sz="4" w:color="B8C0CC"/>'
            .'<w:bottom w:val="single" w:sz="4" w:color="B8C0CC"/><w:right w:val="single" w:sz="4" w:color="B8C0CC"/>'
            .'<w:insideH w:val="single" w:sz="4" w:color="D8DEE8"/><w:insideV w:val="single" w:sz="4" w:color="D8DEE8"/>'
            .'</w:tblBorders></w:tblPr><w:tblGrid><w:gridCol w:w="1050"/><w:gridCol w:w="8850"/></w:tblGrid>';

        if ($rows->isEmpty()) {
            $xml .= '<w:tr>'.$this->cell('—', 1050, true).$this->cell('কোনো এন্ট্রি নেই', 8850, false).'</w:tr>';
            return $xml.'</w:tbl>';
        }

        foreach ($rows->groupBy('serial') as $serial => $serialRows) {
            /** @var Collection<int,array<string,mixed>> $serialRows */
            $serialRows = $serialRows->sortBy(fn (array $row) => sprintf(
                '%08d-%08d',
                (int) ($row['sub_serial'] ?? -1) + 1,
                (int) ($row['code'] ?? 0),
            ))->values();

            $firstEntry = $serialRows->first()['entry'] ?? null;
            $cadreName = trim((string) ($firstEntry?->cadre_name_bn_snapshot ?? ''));
            if ($cadreName === '') {
                $cadreName = 'বাংলা নাম পাওয়া যায়নি';
            }

            $hasSubSerial = $serialRows->contains(
                fn (array $row) => (int) ($row['sub_serial'] ?? -1) >= 0
            );

            if ($hasSubSerial) {
                $secondCell = $this->paragraph($cadreName, true, 22, 'left');

                foreach ($serialRows as $row) {
                    $subSerial = (int) ($row['sub_serial'] ?? -1);
                    if ($subSerial < 0) {
                        // A parent-only effective row is uncommon, but keep its tags visible if present.
                        $secondCell .= $this->placeholderParagraphs($row, 0);
                        continue;
                    }

                    $entry = $row['entry'] ?? null;
                    $postName = trim((string) ($entry?->post_name_bn_snapshot ?? ''));
                    if ($postName === '') {
                        $postName = 'বাংলা পদের নাম পাওয়া যায়নি';
                    }

                    $subLabel = $this->banglaNumber((int) $serial).'.'.$this->banglaNumber($subSerial).'। '.$postName;
                    $secondCell .= $this->paragraph($subLabel, true, 21, 'left');
                    $secondCell .= $this->placeholderParagraphs($row, 0);
                }
            } else {
                $row = $serialRows->first();
                $postName = trim((string) (($row['entry'] ?? null)?->post_name_bn_snapshot ?? ''));
                $displayName = $cadreName;
                if ($postName !== '' && $postName !== $cadreName) {
                    $displayName .= ': '.$postName;
                }

                $secondCell = $this->paragraph($displayName, true, 22, 'left')
                    .$this->placeholderParagraphs($row, 0);
            }

            $xml .= '<w:tr>'
                .$this->cell($this->banglaNumber((int) $serial).'।', 1050, true)
                .$this->cellXml($secondCell, 8850)
                .'</w:tr>';
        }

        return $xml.'</w:tbl>';
    }

    /** @param array<string,mixed> $row */
    private function placeholderParagraphs(array $row, int $indent): string
    {
        $code = (int) ($row['code'] ?? 0);
        $abbr = strtoupper(trim((string) ($row['abbr'] ?? '')));
        if ($code < 1 || $abbr === '' || preg_match('/^[A-Z0-9]+$/', $abbr) !== 1) {
            throw new RuntimeException('A current Circular cadre/sub-cadre abbreviation could not be resolved for DOCX sample generation.');
        }

        $key = $code.'_'.$abbr;

        return $this->paragraph('[['.$key.']]', false, 20, 'left', $indent)
            .$this->paragraph('[[TOTAL_'.$key.']]', true, 20, 'left', $indent);
    }

    private function cell(string $text, int $width, bool $bold): string
    {
        return $this->cellXml($this->paragraph($text, $bold, 21, 'left'), $width);
    }

    private function cellXml(string $innerXml, int $width): string
    {
        return '<w:tc><w:tcPr><w:tcW w:w="'.$width.'" w:type="dxa"/><w:vAlign w:val="top"/></w:tcPr>'.$innerXml.'</w:tc>';
    }

    private function paragraph(
        string $text,
        bool $bold = false,
        int $size = 22,
        ?string $align = null,
        int $leftIndent = 0,
    ): string {
        $pPrParts = [];
        if ($align !== null) {
            $pPrParts[] = '<w:jc w:val="'.$align.'"/>';
        }
        if ($leftIndent > 0) {
            $pPrParts[] = '<w:ind w:left="'.$leftIndent.'"/>';
        }
        $pPr = $pPrParts === [] ? '' : '<w:pPr>'.implode('', $pPrParts).'</w:pPr>';
        $isBangla = preg_match('/[\x{0980}-\x{09FF}]/u', $text) === 1;
        $font = $isBangla ? 'Nikosh' : 'Times New Roman';
        $resolvedSize = $isBangla ? 24 : 22; // 12pt Bengali, 11pt English/tags.
        $rPr = '<w:rPr><w:rFonts w:ascii="'.$font.'" w:hAnsi="'.$font.'" w:eastAsia="'.$font.'" w:cs="'.$font.'"/>'
            .($bold ? '<w:b/><w:bCs/>' : '')
            .'<w:sz w:val="'.$resolvedSize.'"/><w:szCs w:val="'.$resolvedSize.'"/></w:rPr>';

        return '<w:p>'.$pPr.'<w:r>'.$rPr.'<w:t xml:space="preserve">'.$this->xml($text).'</w:t></w:r></w:p>';
    }

    private function xml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function banglaNumber(int $number): string
    {
        return strtr((string) $number, ['0'=>'০','1'=>'১','2'=>'২','3'=>'৩','4'=>'৪','5'=>'৫','6'=>'৬','7'=>'৭','8'=>'৮','9'=>'৯']);
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            .'<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            .'</Relationships>';
    }

    private function documentRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            .'<w:style w:type="paragraph" w:default="1" w:styleId="Normal"><w:name w:val="Normal"/>'
            .'<w:rPr><w:rFonts w:ascii="Times New Roman" w:hAnsi="Times New Roman" w:eastAsia="Times New Roman" w:cs="Times New Roman"/>'
            .'<w:sz w:val="22"/><w:szCs w:val="22"/></w:rPr></w:style>'
            .'</w:styles>';
    }
}
