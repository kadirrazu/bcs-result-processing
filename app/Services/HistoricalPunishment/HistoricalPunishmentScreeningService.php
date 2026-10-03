<?php

namespace App\Services\HistoricalPunishment;

use App\Models\{AllocationA5CandidateResult, AllocationA5Run, HistoricalPunishment, HistoricalPunishmentScreeningMatch, HistoricalPunishmentScreeningRun, MeritProcessingState, MeritResult, PreliminaryProcessingState, PreliminaryResult, Registration, WrittenProcessingState, WrittenResult};
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HistoricalPunishmentScreeningService
{
    public const MATCHING_ALGORITHM = 'co4c1-core-v1';
    public const CHUNK_SIZE = 750;

    public function createRun(string $phase, string $referenceDate, int $actorId): HistoricalPunishmentScreeningRun
    {
        $query = $this->candidateQuery($phase);

        return HistoricalPunishmentScreeningRun::query()->create([
            'phase' => $phase,
            'reference_date' => $referenceDate,
            'candidate_count' => (clone $query)->count(),
            'processed_count' => 0,
            'matched_count' => 0,
            'review_count' => 0,
            'active_warning_count' => 0,
            'matching_algorithm' => self::MATCHING_ALGORITHM,
            'status' => 'queued',
            'created_by' => $actorId,
        ]);
    }

    public function process(int $runId): void
    {
        $run = HistoricalPunishmentScreeningRun::query()->findOrFail($runId);
        $run->update(['status' => 'running', 'started_at' => now(), 'failure_message' => null]);

        // Repository is central/common DB. Build one compact in-memory lookup once per job.
        // toBase() is intentional: Eloquent Collection::except() expects models and calls getKey().
        $history = HistoricalPunishment::query()
            ->orderBy('id')
            ->get()
            ->toBase()
            ->groupBy(fn ($h) => $this->histKey($h) ?? '__NONE__')
            ->except('__NONE__');

        $matched = 0;
        $review = 0;
        $warnings = 0;
        $processed = 0;
        $now = now();

        $this->candidateQuery($run->phase)->chunkById(self::CHUNK_SIZE, function ($registrations) use ($run, $history, &$matched, &$review, &$warnings, &$processed, $now): void {
            $insert = [];

            foreach ($registrations as $reg) {
                $processed++;
                $key = $this->regKey($reg);
                if (! $key) {
                    continue;
                }

                $candidates = collect($history->get($key, []));
                foreach ($candidates as $h) {
                    $evidence = $this->evidence($reg, $h, $candidates->count() > 1);
                    $needsReview = $this->needsReview($evidence);
                    $active = $this->active($h, $run->reference_date->format('Y-m-d'));
                    $state = $h->is_lifetime
                        ? 'lifetime'
                        : ($active ? 'active_until_'.$h->punishment_end?->format('Y-m-d') : 'expired');

                    $insert[] = [
                        'run_id' => $run->id,
                        'registration_id' => $reg->id,
                        'user_id' => $reg->user_id,
                        'reg' => $reg->reg,
                        'name' => $reg->name,
                        'historical_punishment_id' => $h->id,
                        'match_status' => $needsReview ? 'review' : 'matched',
                        'match_method' => $needsReview ? 'CORE_EXACT_SUPPORTING_REVIEW' : 'CORE_EXACT',
                        'evidence' => json_encode($evidence, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'punishment_active' => $active,
                        'punishment_state' => $state,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $needsReview ? $review++ : $matched++;
                    if ($active && ! $needsReview) {
                        $warnings++;
                    }
                }
            }

            if ($insert !== []) {
                foreach (array_chunk($insert, 500) as $rows) {
                    HistoricalPunishmentScreeningMatch::query()->insert($rows);
                }
            }

            HistoricalPunishmentScreeningRun::query()->whereKey($run->id)->update([
                'processed_count' => $processed,
                'matched_count' => $matched,
                'review_count' => $review,
                'active_warning_count' => $warnings,
            ]);
        }, 'id');

        HistoricalPunishmentScreeningRun::query()->whereKey($run->id)->update([
            'status' => 'completed',
            'processed_count' => $processed,
            'matched_count' => $matched,
            'review_count' => $review,
            'active_warning_count' => $warnings,
            'finished_at' => now(),
        ]);
    }

    /**
     * Build a registration query with a SQL subquery/EXISTS instead of materialising
     * thousands of registration IDs into PHP and feeding them back to WHERE IN (...).
     */
    private function candidateQuery(string $phase): Builder
    {
        $query = Registration::query()->where('status', 'active')->orderBy('registrations.id');

        if ($phase === 'preliminary_initial') {
            return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('preliminary_results as pr')->whereColumn('pr.registration_id', 'registrations.id')->where('pr.candidate_status', 'active'));
        }
        if ($phase === 'preliminary_final') {
            $s = PreliminaryProcessingState::query()->find(1);
            if (! $s?->result_finalized_at) throw ValidationException::withMessages(['phase' => 'Finalize Preliminary result first.']);
            return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('preliminary_results as pr')->whereColumn('pr.registration_id', 'registrations.id')->where('pr.candidate_status', 'active')->where('pr.result_status', 'pass'));
        }
        if ($phase === 'written_initial') {
            return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('written_results as wr')->whereColumn('wr.registration_id', 'registrations.id')->where('wr.status', 'active'));
        }
        if ($phase === 'written_final') {
            $s = WrittenProcessingState::query()->first();
            if (! $s?->result_finalized_at || $s->is_stale) throw ValidationException::withMessages(['phase' => 'Finalize a current Written result first.']);
            return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('written_results as wr')->whereColumn('wr.registration_id', 'registrations.id')->where('wr.status', 'active')->whereNotNull('wr.written_qualified_track'));
        }
        if ($phase === 'merit_initial' || $phase === 'merit_final') {
            $s = MeritProcessingState::query()->find(1);
            if (! $s?->latest_run_id) throw ValidationException::withMessages(['phase' => 'Run Merit Generation first.']);
            if ($phase === 'merit_final' && (! $s->finalized_at || $s->is_stale)) throw ValidationException::withMessages(['phase' => 'Finalize a current Merit result first.']);
            $runId = (int) $s->latest_run_id;
            return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('merit_results as mr')->whereColumn('mr.registration_id', 'registrations.id')->where('mr.processing_run_id', $runId));
        }
        if ($phase === 'allocation_final') {
            $run = AllocationA5Run::query()->where('status', 'finalized')->where('is_stale', false)->latest('id')->first();
            if (! $run) throw ValidationException::withMessages(['phase' => 'Finalize a current Cadre Allocation result first.']);
            $runId = (int) $run->id;
            return $query->whereExists(fn ($q) => $q->selectRaw('1')->from((new AllocationA5CandidateResult())->getTable().' as ar')->whereColumn('ar.registration_id', 'registrations.id')->where('ar.allocation_a5_run_id', $runId)->where('ar.overall_status', 'PASS'));
        }
        if ($phase === 'noncadre_final') {
            $run = DB::connection('exam')->table('non_cadre_allocation_runs')->where('status', 'finalized')->where('is_stale', false)->orderByDesc('id')->first();
            if (! $run) throw ValidationException::withMessages(['phase' => 'Finalize a current Non-Cadre Allocation result first.']);
            $runId = (int) $run->id;
            return $query->whereExists(fn ($q) => $q->selectRaw('1')->from('non_cadre_allocation_results as nr')->whereColumn('nr.registration_id', 'registrations.id')->where('nr.allocation_run_id', $runId)->whereNotNull('nr.post_code'));
        }

        throw ValidationException::withMessages(['phase' => 'Invalid screening population.']);
    }

    private function active($h, string $d): bool { if ($h->is_lifetime) return true; if (! $h->punishment_end) return false; $afterStart = ! $h->punishment_start || $d >= $h->punishment_start->format('Y-m-d'); return $afterStart && $d <= $h->punishment_end->format('Y-m-d'); }
    private function regKey($r): ?string { if (! $r->ssc_roll || ! $r->ssc_year || ! $r->birth_date) return null; return $this->id($r->ssc_roll).'|'.(int) $r->ssc_year.'|'.$r->birth_date->format('Y-m-d'); }
    private function histKey($h): ?string { if (! $h->ssc_roll || ! $h->ssc_year || ! $h->b_date) return null; return $this->id($h->ssc_roll).'|'.(int) $h->ssc_year.'|'.$h->b_date->format('Y-m-d'); }
    private function evidence($r, $h, bool $multi): array { return ['supporting' => ['name' => $this->text($r->name, $h->name), 'nid' => $this->same($r->national_id, $h->nid_no), 'hsc_roll' => $this->same($r->hsc_roll, $h->hsc_roll), 'hsc_year' => $this->num($r->hsc_year, $h->hsc_year), 'secondary_dob' => $this->date($r->birth_date?->format('Y-m-d'), $h->dob?->format('Y-m-d'))], 'multiple_core_candidates' => $multi]; }
    private function needsReview(array $e): bool { if ($e['multiple_core_candidates']) return true; $s = $e['supporting']; if (($s['name']['status'] ?? null) !== 'exact') return true; foreach (['nid','hsc_roll','hsc_year','secondary_dob'] as $k) if (($s[$k]['status'] ?? null) === 'mismatch') return true; return false; }
    private function text($a, $b): array { $a = $this->t($a); $b = $this->t($b); if (! $a || ! $b) return ['status' => 'not_compared']; $x = $this->norm($a); $y = $this->norm($b); return ['status' => $x === $y ? 'exact' : ((str_contains($x, $y) || str_contains($y, $x)) ? 'partial' : 'different'), 'current' => $a, 'previous' => $b]; }
    private function same($a, $b): array { if ($a === null || $a === '' || $b === null || $b === '') return ['status' => 'not_compared']; return ['status' => $this->id($a) === $this->id($b) ? 'match' : 'mismatch']; }
    private function num($a, $b): array { if ($a === null || $b === null) return ['status' => 'not_compared']; return ['status' => (int) $a === (int) $b ? 'match' : 'mismatch']; }
    private function date($a, $b): array { if (! $a || ! $b) return ['status' => 'not_compared']; return ['status' => $a === $b ? 'match' : 'mismatch']; }
    private function t($v): ?string { $v = trim((string) $v); return $v === '' ? null : $v; }
    private function id($v): string { return strtoupper(preg_replace('/[^A-Z0-9]+/i', '', (string) $v) ?? ''); }
    private function norm($v): string { return preg_replace('/\s+/', ' ', strtolower(trim((string) $v))) ?? ''; }
}
