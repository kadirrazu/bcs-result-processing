<?php

namespace App\Reports\Pdf;

use App\Services\NonCadre\Reporting\NonCadreReportingService;
use Illuminate\Support\Str;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use RuntimeException;

final class NonCadreInteractivePdfReport
{
    public function generate(NonCadreReportingService $reports, string $scope, string $mode, ?string $postCode, string $examinationName): array
    {
        $identity = $mode === 'booklet';
        $title = '';
        $view = '';
        $data = ['mode' => $mode, 'examinationName' => $examinationName];

        if ($scope === 'common') {
            $title = 'Common Merit Position Allocation '.($identity ? 'Booklet Publishing' : 'Verification').' Report';
            $view = 'reports.pdf.non-cadre.common';
            $data['rows'] = $reports->commonMeritAll($identity);
        } elseif ($scope === 'posts') {
            $title = 'Post Choice & Quota Eligibility Reports';
            $view = 'reports.pdf.non-cadre.posts';
            $data['posts'] = $reports->posts();
        } elseif (in_array($scope, ['post', 'post-allocated'], true)) {
            abort_if(! filled($postCode), 422, 'Post code is required.');
            $payload = $reports->postReportAll((string) $postCode, $identity, $scope === 'post-allocated');
            $title = ($scope === 'post-allocated' ? 'Allocated Candidates' : 'Post Choice & Quota Eligibility').' '.($identity ? 'Booklet Publishing' : 'Verification').' Report';
            $view = 'reports.pdf.non-cadre.post';
            $data = [...$data, ...$payload, 'allocatedOnly' => $scope === 'post-allocated'];
        } elseif ($scope === 'serial-merit') {
            $payload = $reports->serialMeritReport();
            $title = (string) $payload['title'];
            $view = 'reports.pdf.non-cadre.serial-merit';
            $data = [...$data, ...$payload];
        } else {
            throw new RuntimeException('Unsupported NC5 PDF report scope.');
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8', 'format' => 'A4', 'orientation' => $scope === 'common' || str_starts_with($scope, 'post') ? 'L' : 'P',
            'margin_left' => 8, 'margin_right' => 8, 'margin_top' => 10, 'margin_bottom' => 10,
            'tempDir' => $this->tempDirectory(), 'default_font' => 'dejavusans',
        ]);
        $mpdf->SetTitle($examinationName.' - '.$title);
        $mpdf->SetHTMLFooter('<div style="text-align:right;font-size:7pt;color:#667085">Page {PAGENO} of {nbpg}</div>');
        $data['title'] = $title;
        $mpdf->WriteHTML(view($view, $data)->render());
        $slug = Str::slug($examinationName) ?: 'bcs';
        return ['content' => $mpdf->Output('', Destination::STRING_RETURN), 'filename' => $slug.'-non-cadre-'.Str::slug($scope.'-'.$mode).'-'.now()->format('Ymd-His').'.pdf'];
    }

    private function tempDirectory(): string
    {
        $path = storage_path('app/private/mpdf/non-cadre-reporting');
        if (! is_dir($path) && ! mkdir($path, 0775, true) && ! is_dir($path)) throw new RuntimeException("Unable to create mPDF temporary directory [{$path}].");
        return $path;
    }
}
