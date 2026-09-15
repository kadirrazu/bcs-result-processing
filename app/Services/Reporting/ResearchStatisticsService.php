<?php

namespace App\Services\Reporting;

use App\Enums\PreliminaryProcessingStatus;
use App\Enums\PreliminaryResultStatus;
use App\Enums\WrittenProcessingStatus;
use App\Models\BachelorSubject;
use App\Models\CadreMaster;
use App\Models\CadreSubMaster;
use App\Models\District;
use App\Models\Division;
use App\Models\Gender;
use App\Models\PreliminaryProcessingState;
use App\Models\Registration;
use App\Models\University;
use App\Models\WrittenProcessingState;
use App\Services\Allocation\AllocationA6ReadinessService;
use App\Services\Allocation\AllocationResultDispositionService;
use App\Support\Examinations\ExaminationContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Authoritative aggregate source for Research & Statistics reporting.
 *
 * Population scopes are deliberately independent: Registration is always available;
 * Preliminary/Written require their finalized current authority; Recommended requires
 * the same fail-closed publishing authority as A6. All dimensions are aggregated in
 * SQL by stable codes and mapped through central masters afterwards.
 */
final class ResearchStatisticsService
{
    private const EDUCATION_PARENT_CADRES = [610, 620, 630, 640, 660];

    public function __construct(
        private readonly ExaminationContext $context,
        private readonly AllocationA6ReadinessService $allocationReadiness,
        private readonly AllocationResultDispositionService $dispositions,
    ) {}


    /** @return array<string,array{title:string,reports:array<string,string>}> */
    public function catalog(): array
    {
        return [
            'applicant' => ['title' => 'Applicant Candidates', 'reports' => [
                'applicant-overall' => 'Applicant Candidates — Gender-wise Statistics',
                'applicant-division' => 'Applicant Candidates — Division-wise Gender Statistics',
                'applicant-district' => 'Applicant Candidates — District-wise Gender Statistics',
                'applicant-age' => 'Applicant Candidates — Age Group-wise Gender Statistics',
            ]],
            'preliminary' => ['title' => 'Preliminary Qualified Candidates', 'reports' => [
                'preliminary-overall' => 'Preliminary Qualified Candidates — Gender-wise Statistics',
                'preliminary-division' => 'Preliminary Qualified Candidates — Division-wise Gender Statistics',
                'preliminary-district' => 'Preliminary Qualified Candidates — District-wise Gender Statistics',
                'preliminary-age' => 'Preliminary Qualified Candidates — Age Group-wise Gender Statistics',
            ]],
            'written' => ['title' => 'Written Qualified Candidates', 'reports' => [
                'written-overall' => 'Written Qualified Candidates — Gender-wise Statistics',
                'written-division' => 'Written Qualified Candidates — Division-wise Gender Statistics',
                'written-district' => 'Written Qualified Candidates — District-wise Gender Statistics',
                'written-age' => 'Written Qualified Candidates — Age Group-wise Gender Statistics',
            ]],
            'recommended' => ['title' => 'Allocated / Recommended Candidates', 'reports' => [
                'recommended-overall' => 'Allocated / Recommended Candidates — Gender-wise Statistics',
                'recommended-division' => 'Allocated / Recommended Candidates — Division-wise Gender Statistics',
                'recommended-district' => 'Allocated / Recommended Candidates — District-wise Gender Statistics',
                'recommended-age' => 'Allocated / Recommended Candidates — Age Group-wise Gender Statistics',
                'recommended-subject' => 'Allocated / Recommended Candidates — Bachelor Subject-wise Gender Statistics',
                'recommended-institution' => 'Allocated / Recommended Candidates — University / College / Institute-wise Gender Statistics',
                'recommended-top-institutions' => 'Top 10 Educational Institutions by Recommended Candidates — Gender Breakdown',
                'recommended-general-overall' => 'General Cadre Recommended Candidates — Gender-wise Statistics',
                'recommended-general-division' => 'General Cadre Recommended Candidates — Division-wise Gender Statistics',
                'recommended-general-district' => 'General Cadre Recommended Candidates — District-wise Gender Statistics',
                'recommended-technical-overall' => 'Technical Cadre Recommended Candidates — Gender-wise Statistics',
                'recommended-technical-division' => 'Technical Cadre Recommended Candidates — Division-wise Gender Statistics',
                'recommended-technical-district' => 'Technical Cadre Recommended Candidates — District-wise Gender Statistics',
                'recommended-education-overall' => 'General / Technical Education Cadre Recommended Candidates — Gender-wise Statistics',
                'recommended-education-division' => 'General / Technical Education Cadre Recommended Candidates — Division-wise Gender Statistics',
                'recommended-education-district' => 'General / Technical Education Cadre Recommended Candidates — District-wise Gender Statistics',
            ]],
        ];
    }

    /** @return array{key:string,category:string,title:string,data:array<string,mixed>,ranked_top_10:bool,age_date:?string} */
    public function report(string $key): array
    {
        $catalog = $this->catalog();
        $category = null; $title = null;
        foreach ($catalog as $categoryKey => $definition) {
            if (isset($definition['reports'][$key])) { $category = $categoryKey; $title = $definition['reports'][$key]; break; }
        }
        abort_if($category === null, 404);

        $availability = $this->availability();
        abort_unless((bool) ($availability[$category]['ready'] ?? false), 409, (string) ($availability[$category]['reason'] ?? 'This report is not ready.'));

        $maps = $this->maps();
        $ageDate = $this->context->current()?->age_calculation_date?->format('Y-m-d');
        $suffix = substr($key, strlen($category) + 1);
        $ranked = false;

        if (in_array($suffix, ['overall','division','district','age'], true)) {
            $data = match ($suffix) {
                'overall' => $this->overall($category),
                'division' => $this->dimension($category, 'division_code', $maps['divisions'], 'Unknown / Unmapped Division'),
                'district' => $this->dimension($category, 'district_code', $maps['districts'], 'Unknown / Unmapped District'),
                'age' => $this->ageDimension($category, $ageDate),
            };
        } elseif ($key === 'recommended-subject') {
            $data = $this->dimension('recommended', 'bachelor_subject_code', $maps['subjects'], 'Unknown / Unmapped Subject');
        } elseif ($key === 'recommended-institution') {
            $data = $this->dimension('recommended', 'university_code', $maps['universities'], 'Unknown / Unmapped Institution');
        } elseif ($key === 'recommended-top-institutions') {
            $all = $this->dimension('recommended', 'university_code', $maps['universities'], 'Unknown / Unmapped Institution');
            $rows = array_slice($all['rows'], 0, 10); $data = ['rows'=>$rows,'total'=>$this->totalRow($rows),'top10'=>true]; $ranked = true;
        } elseif (preg_match('/^recommended-(general|technical|education)-(overall|division|district)$/', $key, $m)) {
            $group = $this->allocationGroup($m[1], $maps); $data = $group[$m[2]];
        } else { abort(404); }

        if ($suffix === 'age' && !($data['available'] ?? false)) abort(409, 'Age Calculation Date is not configured for this examination.');
        return ['key'=>$key,'category'=>$category,'title'=>$title,'data'=>$data,'ranked_top_10'=>$ranked,'age_date'=>$ageDate];
    }

    /** @return array<string,mixed> */
    public function build(): array
    {
        $examination = $this->context->current();
        $ageDate = $examination?->age_calculation_date?->format('Y-m-d');
        $availability = $this->availability();
        $maps = $this->maps();

        $sections = [
            'applicant' => $this->population('applicant', $ageDate, $maps),
            'preliminary' => $availability['preliminary']['ready'] ? $this->population('preliminary', $ageDate, $maps) : null,
            'written' => $availability['written']['ready'] ? $this->population('written', $ageDate, $maps) : null,
            'recommended' => $availability['recommended']['ready'] ? $this->population('recommended', $ageDate, $maps) : null,
        ];

        if (is_array($sections['recommended'])) {
            $sections['recommended']['subject'] = $this->dimension('recommended', 'bachelor_subject_code', $maps['subjects'], 'Unknown / Unmapped Subject');
            $sections['recommended']['university'] = $this->dimension('recommended', 'university_code', $maps['universities'], 'Unknown / Unmapped Institution');
            $sections['recommended']['top_university'] = array_slice($sections['recommended']['university']['rows'], 0, 10);
            $sections['recommended']['allocation_groups'] = [
                'general' => $this->allocationGroup('general', $maps),
                'technical' => $this->allocationGroup('technical', $maps),
                'education' => $this->allocationGroup('education', $maps),
            ];
        }

        return compact('availability', 'sections', 'ageDate');
    }

    /** @return array<string,array{ready:bool,reason:?string}> */
    public function availability(): array
    {
        $pre = PreliminaryProcessingState::query()->find(1);
        $preStatus = $pre?->status instanceof PreliminaryProcessingStatus ? $pre->status->value : (string) ($pre?->status ?? '');
        $preReady = $preStatus === PreliminaryProcessingStatus::ResultFinalized->value && (bool) $pre?->latest_finalization_run_id;

        $written = WrittenProcessingState::query()->find(1);
        $writtenStatus = $written?->status instanceof WrittenProcessingStatus ? $written->status->value : (string) ($written?->status ?? '');
        $writtenReady = $writtenStatus === WrittenProcessingStatus::ResultFinalized->value
            && ! (bool) $written?->is_stale && (bool) $written?->latest_processing_run_id;

        $allocation = $this->allocationReadiness->inspect();

        return [
            'applicant' => ['ready' => true, 'reason' => null],
            'preliminary' => ['ready' => $preReady, 'reason' => $preReady ? null : 'Current finalized Preliminary authority is unavailable.'],
            'written' => ['ready' => $writtenReady, 'reason' => $writtenReady ? null : 'Current finalized Written authority is unavailable.'],
            'recommended' => ['ready' => (bool) ($allocation['ready'] ?? false), 'reason' => ($allocation['ready'] ?? false) ? null : (string) ($allocation['reason'] ?? 'Current finalized Allocation authority is unavailable.')],
        ];
    }

    /** @param array<string,array<int|string,string>> $maps @return array<string,mixed> */
    private function population(string $population, ?string $ageDate, array $maps): array
    {
        return [
            'overall' => $this->overall($population),
            'division' => $this->dimension($population, 'division_code', $maps['divisions'], 'Unknown / Unmapped Division'),
            'district' => $this->dimension($population, 'district_code', $maps['districts'], 'Unknown / Unmapped District'),
            'age' => $this->ageDimension($population, $ageDate),
        ];
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:array<string,mixed>} */
    private function overall(string $population): array
    {
        $rows = $this->genderAggregate($this->query($population));
        $total = $this->totalRow($rows);
        return ['rows' => [['label' => 'All Candidates'] + $total], 'total' => $total];
    }

    /** @param array<int|string,string> $labels @return array{rows:array<int,array<string,mixed>>,total:array<string,mixed>} */
    private function dimension(string $population, string $column, array $labels, string $unknown): array
    {
        $raw = $this->query($population)
            ->selectRaw("registrations.{$column} AS dimension_code, registrations.sex_code, COUNT(*) AS aggregate")
            ->groupBy("registrations.{$column}", 'registrations.sex_code')
            ->get();

        $rows = $this->pivot($raw, fn ($code) => $code === null ? $unknown : ($labels[(string) $code] ?? $unknown.' ('.$code.')'));
        usort($rows, fn ($a, $b) => $population === 'recommended' && in_array($column, ['university_code'], true)
            ? ($b['total'] <=> $a['total']) ?: strcasecmp($a['label'], $b['label'])
            : strcasecmp($a['label'], $b['label']));

        return ['rows' => $rows, 'total' => $this->totalRow($rows)];
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:array<string,mixed>,available:bool} */
    private function ageDimension(string $population, ?string $ageDate): array
    {
        if (! $ageDate) {
            return ['rows' => [], 'total' => ['total'=>0,'genders'=>[]], 'available' => false];
        }

        $driver = Registration::query()->getConnection()->getDriverName();
        $ageExpr = $driver === 'sqlite'
            ? "CAST(strftime('%Y', '{$ageDate}') AS INTEGER) - CAST(strftime('%Y', registrations.birth_date) AS INTEGER) - (strftime('%m-%d', '{$ageDate}') < strftime('%m-%d', registrations.birth_date))"
            : "TIMESTAMPDIFF(YEAR, registrations.birth_date, '{$ageDate}')";
        $bucket = "CASE WHEN registrations.birth_date IS NULL THEN 'Unknown Age' WHEN ({$ageExpr}) < 21 THEN 'Below 21' WHEN ({$ageExpr}) BETWEEN 21 AND 23 THEN '21-23' WHEN ({$ageExpr}) BETWEEN 24 AND 26 THEN '24-26' WHEN ({$ageExpr}) BETWEEN 27 AND 29 THEN '27-29' ELSE '30 and above' END";

        $raw = $this->query($population)->selectRaw("{$bucket} AS dimension_code, registrations.sex_code, COUNT(*) AS aggregate")
            ->groupByRaw($bucket.', registrations.sex_code')->get();
        $order = ['Below 21'=>1,'21-23'=>2,'24-26'=>3,'27-29'=>4,'30 and above'=>5,'Unknown Age'=>6];
        $rows = $this->pivot($raw, fn ($code) => (string) $code);
        usort($rows, fn ($a,$b) => ($order[$a['label']] ?? 99) <=> ($order[$b['label']] ?? 99));

        return ['rows'=>$rows,'total'=>$this->totalRow($rows),'available'=>true];
    }

    /** @param array<string,array<int|string,string>> $maps @return array<string,mixed> */
    private function allocationGroup(string $group, array $maps): array
    {
        $query = $this->query('recommended');
        if ($group === 'general') {
            $query->where('a5stats.cadre_type', 'GG');
        } elseif ($group === 'technical') {
            $query->where('a5stats.cadre_type', 'TT');
        } else {
            $educationCodes = $this->educationEffectiveCodes();
            $query->whereIn('a5stats.cadre_code', $educationCodes ?: [-1]);
        }

        return [
            'overall' => $this->aggregateFromQuery(clone $query),
            'division' => $this->dimensionFromQuery(clone $query, 'division_code', $maps['divisions'], 'Unknown / Unmapped Division'),
            'district' => $this->dimensionFromQuery(clone $query, 'district_code', $maps['districts'], 'Unknown / Unmapped District'),
        ];
    }

    /** @return array{rows:array<int,array<string,mixed>>,total:array<string,mixed>} */
    private function aggregateFromQuery(Builder $query): array
    {
        $rows = $this->genderAggregate($query);
        $total = $this->totalRow($rows);
        return ['rows'=>[['label'=>'All Candidates'] + $total], 'total'=>$total];
    }

    /** @param array<int|string,string> $labels @return array{rows:array<int,array<string,mixed>>,total:array<string,mixed>} */
    private function dimensionFromQuery(Builder $query, string $column, array $labels, string $unknown): array
    {
        $raw = $query->selectRaw("registrations.{$column} AS dimension_code, registrations.sex_code, COUNT(*) AS aggregate")
            ->groupBy("registrations.{$column}", 'registrations.sex_code')->get();
        $rows = $this->pivot($raw, fn ($code) => $code === null ? $unknown : ($labels[(string)$code] ?? $unknown.' ('.$code.')'));
        usort($rows, fn($a,$b) => strcasecmp($a['label'],$b['label']));
        return ['rows'=>$rows,'total'=>$this->totalRow($rows)];
    }

    private function query(string $population): Builder
    {
        $query = Registration::query()->from('registrations');

        if ($population === 'preliminary') {
            return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('preliminary_results as prstats')
                ->whereColumn('prstats.registration_id','registrations.id')->where('prstats.result_status', PreliminaryResultStatus::Pass->value));
        }
        if ($population === 'written') {
            return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('written_results as wrstats')
                ->whereColumn('wrstats.registration_id','registrations.id')->whereNotNull('wrstats.written_qualified_track'));
        }
        if ($population === 'recommended') {
            $a5 = $this->allocationReadiness->requireReady();
            $query->join('allocation_a5_candidate_results as a5stats', 'a5stats.registration_id', '=', 'registrations.id')
                ->where('a5stats.allocation_a5_run_id', $a5->id)->where('a5stats.overall_status', 'PASS');
            return $this->dispositions->applyPublishedOnly($query, $a5, 'registrations.id');
        }
        return $query;
    }

    /** @return array<int,array{label:string,total:int,genders:array<string,int>}> */
    private function genderAggregate(Builder $query): array
    {
        $raw = $query->selectRaw('registrations.sex_code AS dimension_code, COUNT(*) AS aggregate')->groupBy('registrations.sex_code')->get();
        $genders = $this->genderLabels();
        $counts = [];
        foreach ($raw as $row) $counts[$this->genderLabel($row->dimension_code, $genders)] = (int) $row->aggregate;
        return [['label'=>'All Candidates','total'=>array_sum($counts),'genders'=>$counts]];
    }

    /** @param Collection<int,object> $raw @return array<int,array{label:string,total:int,genders:array<string,int>}> */
    private function pivot(Collection $raw, callable $label): array
    {
        $genderLabels = $this->genderLabels();
        $out = [];
        foreach ($raw as $row) {
            $key = (string) ($row->dimension_code ?? '__NULL__');
            $out[$key] ??= ['label'=>$label($row->dimension_code),'total'=>0,'genders'=>[]];
            $gender = $this->genderLabel($row->sex_code, $genderLabels);
            $count = (int) $row->aggregate;
            $out[$key]['genders'][$gender] = ($out[$key]['genders'][$gender] ?? 0) + $count;
            $out[$key]['total'] += $count;
        }
        return array_values($out);
    }

    /** @param array<int,array<string,mixed>> $rows @return array{total:int,genders:array<string,int>} */
    private function totalRow(array $rows): array
    {
        $total = 0; $genders = [];
        foreach ($rows as $row) {
            $total += (int) ($row['total'] ?? 0);
            foreach (($row['genders'] ?? []) as $gender=>$count) $genders[$gender] = ($genders[$gender] ?? 0) + (int) $count;
        }
        return ['total'=>$total,'genders'=>$genders];
    }

    /** @return array<string,string> */
    private function genderLabels(): array
    {
        return Gender::query()->orderBy('code')->pluck('name','code')->mapWithKeys(fn($v,$k)=>[(string)$k=>(string)$v])->all();
    }

    /** @param array<string,string> $labels */
    private function genderLabel(mixed $code, array $labels): string
    {
        if ($code === null || $code === '') return 'Unknown / Unmapped Gender';
        return $labels[(string)$code] ?? 'Unknown / Unmapped Gender';
    }

    /** @return array<string,array<int|string,string>> */
    private function maps(): array
    {
        return [
            'divisions'=>Division::query()->pluck('name','code')->mapWithKeys(fn($v,$k)=>[(string)$k=>(string)$v])->all(),
            'districts'=>District::query()->pluck('name','code')->mapWithKeys(fn($v,$k)=>[(string)$k=>(string)$v])->all(),
            'subjects'=>BachelorSubject::query()->pluck('subject_name','subject_code')->mapWithKeys(fn($v,$k)=>[(string)$k=>(string)$v])->all(),
            'universities'=>University::query()->pluck('name','code')->mapWithKeys(fn($v,$k)=>[(string)$k=>(string)$v])->all(),
        ];
    }

    /** @return list<int> */
    private function educationEffectiveCodes(): array
    {
        $parents = CadreMaster::query()->whereIn('cadre_code', self::EDUCATION_PARENT_CADRES)->get(['id','cadre_code']);
        $codes = $parents->pluck('cadre_code')->map(fn($v)=>(int)$v)->all();
        $subCodes = CadreSubMaster::query()->whereIn('parent_cadre_id', $parents->pluck('id'))->pluck('sub_cadre_code')->map(fn($v)=>(int)$v)->all();
        return array_values(array_unique(array_merge($codes, $subCodes)));
    }
}
