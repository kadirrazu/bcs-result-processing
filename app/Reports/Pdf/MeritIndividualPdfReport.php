<?php

namespace App\Reports\Pdf;

use App\Models\BachelorSubject;
use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\ChoiceValidationResult;
use App\Models\MeritResult;
use App\Models\PostRelatedSubject;
use App\Models\PreliminaryResult;
use App\Models\Registration;
use App\Models\TabulationResult;
use App\Models\VivaResult;
use App\Models\WrittenResult;
use App\Reports\Shared\BanglaPdfFontResolver;
use App\Support\Examinations\ExaminationContext;
use App\Support\Registrations\RegistrationReferencePresenter;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class MeritIndividualPdfReport
{
    public function __construct(
        private readonly BanglaPdfFontResolver $fontResolver,
        private readonly ExaminationContext $context,
    ) {}

    /** @return array{content:string,filename:string} */
    public function generate(MeritResult $result): array
    {
        $result->load([
            'run',
            'cadreRanks' => fn ($q) => $q->orderBy('cadre_code'),
        ]);

        $tabulation = TabulationResult::query()->findOrFail($result->tabulation_result_id);
        $registration = Registration::query()->findOrFail($tabulation->registration_id);
        $preliminary = $tabulation->preliminary_result_id ? PreliminaryResult::query()->find($tabulation->preliminary_result_id) : null;
        $written = WrittenResult::query()->findOrFail($tabulation->written_result_id);
        $viva = VivaResult::query()->findOrFail($tabulation->viva_result_id);

        $bachelorTitle = filled($registration->bachelor_subject_code)
            ? BachelorSubject::query()->where('subject_code', $registration->bachelor_subject_code)->value('subject_name')
            : null;
        $prsTitle = filled($registration->post_related_subject_code)
            ? PostRelatedSubject::query()->where('subject_code', $registration->post_related_subject_code)->value('subject_name')
            : null;
        $bachelorSubjectDisplay = RegistrationReferencePresenter::codeTitle(
            $registration->bachelor_subject_code,
            $bachelorTitle,
            'Unmapped bachelor subject code',
        );
        $postRelatedSubjectDisplay = RegistrationReferencePresenter::codeTitle(
            $registration->post_related_subject_code,
            $prsTitle,
            'Unmapped post-related subject code',
        );

        $choiceVersion = (int) data_get($result->run?->source_snapshot, 'choice_validation.validation_version', 0);
        $circularVersion = (int) data_get($result->run?->source_snapshot, 'circular.version', 0);
        $choiceValidation = $choiceVersion > 0
            ? ChoiceValidationResult::query()
                ->with(['source.items' => fn ($q) => $q->orderBy('position')])
                ->where('registration_id', $result->registration_id)
                ->where('validation_version', $choiceVersion)
                ->when($circularVersion > 0, fn ($q) => $q->where('circular_version', $circularVersion))
                ->first()
            : null;

        $originalChoiceCodes = $choiceValidation?->source?->items
            ?->filter(fn ($item) => filled($item->choice_code ?? $item->raw_value))
            ->map(fn ($item) => (string) ($item->choice_code ?? $item->raw_value))
            ->values()
            ->all() ?? [];
        $validatedChoiceCodes = array_values(array_map('strval', (array) ($choiceValidation?->validated_choice_codes ?? [])));
        $choiceCodes = array_values(array_unique(array_filter(array_merge($originalChoiceCodes, $validatedChoiceCodes))));

        $mainCadres = CadreMaster::query()
            ->whereIn('cadre_code', array_map('intval', $choiceCodes))
            ->get(['cadre_code', 'cadre_abbr'])
            ->mapWithKeys(fn ($row) => [(string) $row->cadre_code => (string) $row->cadre_abbr]);
        $subCadres = CadreSubMaster::query()
            ->whereIn('sub_cadre_code', array_map('intval', $choiceCodes))
            ->get(['sub_cadre_code', 'sub_cadre_abbr'])
            ->mapWithKeys(fn ($row) => [(string) $row->sub_cadre_code => (string) $row->sub_cadre_abbr]);
        $abbrFor = static fn (string $code): string => (string) ($mainCadres->get($code) ?? $subCadres->get($code) ?? 'UNKNOWN');

        $originalChoiceAbbrs = array_map($abbrFor, $originalChoiceCodes);
        $validatedChoiceAbbrs = array_map($abbrFor, $validatedChoiceCodes);

        $font = $this->fontResolver->resolve();
        $defaults = (new ConfigVariables)->getDefaults();
        $fontDefaults = (new FontVariables)->getDefaults();
        $dirs = $defaults['fontDir'];
        if ($font['directory']) {
            $dirs[] = $font['directory'];
        }
        $fontData = ['R' => $font['regular'], 'useOTL' => 0x80];
        if ($font['bold']) {
            $fontData['B'] = $font['bold'];
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4-L',
            'margin_left' => 10,
            'margin_right' => 10,
            'margin_top' => 10,
            'margin_bottom' => 13,
            'tempDir' => $this->tempDirectory(),
            'fontDir' => array_values(array_unique($dirs)),
            'fontdata' => array_replace($fontDefaults['fontdata'], [$font['family'] => $fontData]),
            'default_font' => $font['family'],
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
        ]);

        $mpdf->SetTitle('Individual Finalized Merit - '.$result->reg);
        $mpdf->SetHTMLFooter('<div style="border-top:0.2mm solid #ccc;font-size:8pt;padding-top:1.5mm">Individual Finalized Merit <span style="float:right">Page {PAGENO} of {nbpg}</span></div>');

        $html = view('reports.pdf.merit-individual', [
            'result' => $result,
            'registration' => $registration,
            'preliminary' => $preliminary,
            'written' => $written,
            'viva' => $viva,
            'tabulation' => $tabulation,
            'choiceValidation' => $choiceValidation,
            'originalChoiceCodes' => $originalChoiceCodes,
            'originalChoiceAbbrs' => $originalChoiceAbbrs,
            'validatedChoiceCodes' => $validatedChoiceCodes,
            'validatedChoiceAbbrs' => $validatedChoiceAbbrs,
            'bachelorSubjectDisplay' => $bachelorSubjectDisplay,
            'postRelatedSubjectDisplay' => $postRelatedSubjectDisplay,
            'exam' => $this->context->current(),
            'generatedAt' => now(),
        ])->render();

        $mpdf->WriteHTML($html);

        return [
            'content' => $mpdf->Output('', Destination::STRING_RETURN),
            'filename' => 'merit-'.$result->reg.'-v'.$result->processing_version.'.pdf',
        ];
    }

    private function tempDirectory(): string
    {
        $path = storage_path('app/private/mpdf');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException('Unable to create mPDF temp directory.');
        }
        return $path;
    }
}
