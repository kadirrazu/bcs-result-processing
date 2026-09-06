<?php

namespace App\Services\Allocation;

use App\Models\AllocationProcessingAudit;
use App\Models\AllocationProcessingState;
use App\Models\AllocationSeatBreakupRow;
use App\Models\AllocationSeatBreakupVersion;
use App\Models\AllocationRun;
use App\Models\AllocationA4Run;
use App\Models\AllocationA5Run;
use App\Models\AllocationInputFreeze;
use App\Services\Circular\CircularFinalizedDatasetService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class AllocationSeatBreakupService
{
    public function __construct(
        private readonly CircularFinalizedDatasetService $circular,
        private readonly AllocationSettingsService $settings,
        private readonly AllocationSeatBreakupHasher $hasher,
    ) {}

    public function templatePath(): string
    {
        $circular = $this->circular->verifiedSummary();
        $settings = $this->settings->verified();
        $entries = $this->circular->entries()->where('status', 'active')->values();

        $book = new Spreadsheet();
        $book->getProperties()->setCustomProperty('allocation_circular_version', (int) $circular['version']);
        $book->getProperties()->setCustomProperty('allocation_circular_hash', (string) $circular['dataset_hash']);
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('Seat Breakup');
        $sheet->fromArray([['sl', 'cadre_code', 'total_post', 'mq', 'cff', 'em', 'phc']], null, 'A1');

        $row = 2;
        foreach ($entries as $entry) {
            $serial = (string) $entry->cadre_serial.($entry->sub_serial !== null ? '.'.$entry->sub_serial : '');
            $total = (int) $entry->post_count;
            [$mq, $cff, $em, $phc] = $this->provisionalBreakup($total, $settings);
            // Keep the Circular serial as TEXT. Excel otherwise converts values such
            // as 13.10 to numeric 13.1, which destroys the copied display serial.
            $sheet->setCellValueExplicit('A'.$row, $serial, DataType::TYPE_STRING);
            $sheet->fromArray([[(int) $entry->effective_code, $total, $mq, $cff, $em, $phc]], null, 'B'.$row);
            $row++;
        }
        $sheet->getStyle('A:A')->getNumberFormat()->setFormatCode('@');
        $sheet->freezePane('A2');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        foreach (range('A', 'G') as $column) $sheet->getColumnDimension($column)->setAutoSize(true);

        $path = tempnam(sys_get_temp_dir(), 'allocation-seat-breakup-');
        if ($path === false) throw new \RuntimeException('Unable to create Seat Breakup workbook.');
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        return $path;
    }

    public function import(UploadedFile $file, ?int $actorId): AllocationSeatBreakupVersion
    {
        $circular = $this->circular->verifiedSummary();
        $this->settings->verified();
        $entries = $this->circular->entries()->where('status', 'active')->values();

        // Circular serial is copied for operator readability, but spreadsheet
        // software may collapse a text serial such as 13.10 into numeric 13.1.
        // The CURRENT finalized Circular remains the sole authority.  During
        // upload we therefore resolve an exact Circular row using cadre_code
        // plus serial equivalence, with total_post only as a safe disambiguator
        // when an old/edited workbook has already lost a trailing zero.
        $expected = $entries->map(function ($entry): array {
            $sl = (string) $entry->cadre_serial.($entry->sub_serial !== null ? '.'.$entry->sub_serial : '');
            return [
                'sl' => $sl,
                'entry_id' => (int) $entry->id,
                'cadre_code' => (int) $entry->effective_code,
                'total_post' => (int) $entry->post_count,
            ];
        })->values();

        $book = IOFactory::load($file->getRealPath());

        // Workbook metadata is informational only. Upload validation always uses
        // the currently effective finalized Circular resolved above as the sole
        // authority for sl/cadre_code/total_post.
        $sheet = $book->getActiveSheet();
        $raw = $sheet->toArray(null, true, true, false);
        $header = array_map(fn ($v) => strtolower(trim((string) $v)), array_shift($raw) ?? []);
        if ($header !== ['sl', 'cadre_code', 'total_post', 'mq', 'cff', 'em', 'phc']) {
            throw ValidationException::withMessages(['file' => 'Seat Breakup header must be exactly: sl, cadre_code, total_post, mq, cff, em, phc.']);
        }

        $rows = [];
        $seen = [];
        $errors = [];
        foreach ($raw as $index => $values) {
            if (collect($values)->every(fn ($v) => $v === null || trim((string) $v) === '')) continue;
            $line = $index + 2;
            $sl = trim((string) ($values[0] ?? ''));
            $nums = [];
            foreach ([1,2,3,4,5,6] as $col) {
                $value = $values[$col] ?? null;
                $trimmed = trim((string) ($value ?? ''));

                // Empty quota buckets in operator-edited Excel mean zero.
                // Identity/authority fields (cadre_code, total_post) and MQ must
                // remain explicit so accidental omissions are never hidden.
                if ($trimmed === '' && in_array($col, [4,5,6], true)) {
                    $nums[$col] = 0;
                    continue;
                }

                if (! is_numeric($value) || (int) $value < 0 || (string)(int)$value !== $trimmed) {
                    $errors[] = "Row {$line}: cadre_code, total_post and mq must be non-negative integers; blank cff/em/phc cells are treated as 0.";
                    continue 2;
                }
                $nums[$col] = (int) $value;
            }
            [$code,$total,$mq,$cff,$em,$phc] = [$nums[1],$nums[2],$nums[3],$nums[4],$nums[5],$nums[6]];
            $matches = $expected->filter(fn (array $row): bool =>
                $row['cadre_code'] === $code && $this->serialEquivalent($sl, $row['sl'])
            )->values();

            if ($matches->isEmpty()) {
                $sameSerial = $expected->filter(fn (array $row): bool => $this->serialEquivalent($sl, $row['sl']))->values();
                if ($sameSerial->isEmpty()) {
                    $errors[] = "Row {$line}: sl={$sl}, cadre_code={$code} does not exist in current finalized Circular v{$circular['version']}.";
                } else {
                    $allowedCodes = $sameSerial->pluck('cadre_code')->unique()->sort()->implode(', ');
                    $errors[] = "Row {$line}: cadre_code={$code} does not match current finalized Circular v{$circular['version']} for sl={$sl}. Expected code(s): {$allowedCodes}.";
                }
                continue;
            }

            // Prefer the exact text serial.  For legacy/operator-edited workbooks
            // where 13.10 became 13.1, use total_post only to disambiguate two
            // otherwise equivalent candidate rows.  Never guess if ambiguity remains.
            if ($matches->count() > 1) {
                $exact = $matches->filter(fn (array $row): bool => trim($row['sl']) === $sl)->values();
                if ($exact->count() === 1) {
                    $matches = $exact;
                } else {
                    $sameTotal = $matches->where('total_post', $total)->values();
                    if ($sameTotal->count() === 1) {
                        $matches = $sameTotal;
                    }
                }
            }

            if ($matches->count() !== 1) {
                $expectedSerials = $matches->pluck('sl')->unique()->implode(', ');
                $errors[] = "Row {$line}: sl={$sl}, cadre_code={$code} is ambiguous after Excel serial normalization. Current Circular candidates: {$expectedSerials}. Regenerate the Seat Breakup workbook so the sl column remains text.";
                continue;
            }

            $expectedRow = $matches->first();
            $entryKey = (int) $expectedRow['entry_id'];
            if (isset($seen[$entryKey])) { $errors[] = "Row {$line}: duplicate Circular row sl={$expectedRow['sl']}, cadre_code={$code}."; continue; }
            $seen[$entryKey] = true;

            if ($total !== $expectedRow['total_post']) {
                $errors[] = "Row {$line}: total_post does not exactly match current finalized Circular v{$circular['version']} for sl={$expectedRow['sl']}, cadre_code={$code}. Uploaded total_post={$total}; expected total_post={$expectedRow['total_post']}."; continue;
            }
            if ($mq + $cff + $em + $phc !== $total) {
                $errors[] = "Row {$line}: mq+cff+em+phc must equal total_post."; continue;
            }
            if ($total < 10 && ($mq !== $total || $cff !== 0 || $em !== 0 || $phc !== 0)) {
                $errors[] = "Row {$line}: total_post 1-9 must be 100% MQ with zero CFF/EM/PHC."; continue;
            }
            // Persist the authoritative Circular serial, not a normalized/lost Excel representation.
            $sl = (string) $expectedRow['sl'];
            $rows[] = compact('sl','code','total','mq','cff','em','phc') + ['entry_id' => $expectedRow['entry_id']];
        }
        if (count($seen) !== $expected->count()) $errors[] = 'Seat Breakup must contain every active finalized Circular row exactly once.';
        $book->disconnectWorksheets();
        if ($errors) throw ValidationException::withMessages(['file' => array_slice($errors, 0, 20)]);

        return DB::connection('exam')->transaction(function () use ($rows, $circular, $file, $actorId): AllocationSeatBreakupVersion {
            $next = ((int) AllocationSeatBreakupVersion::query()->max('version')) + 1;
            $version = AllocationSeatBreakupVersion::query()->create([
                'version' => $next, 'status' => 'validated',
                'circular_version' => (int) $circular['version'], 'circular_hash' => (string) $circular['dataset_hash'],
                'source_file' => $file->getClientOriginalName(), 'created_by' => $actorId,
            ]);
            foreach ($rows as $row) {
                AllocationSeatBreakupRow::query()->create([
                    'seat_breakup_version_id' => $version->id, 'sl' => $row['sl'],
                    'cadre_code' => $row['code'], 'total_post' => $row['total'], 'mq' => $row['mq'],
                    'cff' => $row['cff'], 'em' => $row['em'], 'phc' => $row['phc'], 'circular_entry_id' => $row['entry_id'],
                ]);
            }
            $version->forceFill($this->totals($version))->save();
            AllocationProcessingAudit::query()->create([
                'event' => 'SEAT_BREAKUP_VALIDATED', 'actor_id' => $actorId,
                'to_status' => 'validated', 'context' => ['seat_breakup_version' => $next], 'created_at' => now(),
            ]);
            return $version->refresh();
        });
    }

    public function finalize(AllocationSeatBreakupVersion $version, ?int $actorId): AllocationSeatBreakupVersion
    {
        return DB::connection('exam')->transaction(function () use ($version, $actorId): AllocationSeatBreakupVersion {
            $version = AllocationSeatBreakupVersion::query()->whereKey($version->id)->lockForUpdate()->firstOrFail();
            if ((string) $version->status !== 'validated') {
                throw ValidationException::withMessages(['seat_breakup' => 'Only a validated Seat Breakup version can be finalized.']);
            }
            $circular = $this->circular->verifiedSummary();
            if ((int)$version->circular_version !== (int)$circular['version'] || !hash_equals((string)$version->circular_hash, (string)$circular['dataset_hash'])) {
                throw ValidationException::withMessages(['seat_breakup' => 'Circular changed after Seat Breakup validation. Upload a new Seat Breakup.']);
            }
            $hash = $this->hasher->hash($version);
            $previousFinalized = AllocationSeatBreakupVersion::query()
                ->where('status', 'finalized')
                ->whereKeyNot($version->id)
                ->latest('version')
                ->first();

            AllocationSeatBreakupVersion::query()->where('status', 'finalized')->update(['status' => 'superseded']);
            $version->forceFill(['status' => 'finalized', 'dataset_hash' => $hash, 'finalized_by' => $actorId, 'finalized_at' => now()])->save();

            $reason = $previousFinalized
                ? "Seat Breakup was re-finalized as v{$version->version}. Re-freeze A2 and re-run A3/A4."
                : null;

            AllocationProcessingState::query()->whereKey(1)->update([
                'finalized_seat_breakup_version_id' => $version->id,
                // A new effective Seat Breakup invalidates any existing A2/A3/A4 lineage.
                'is_stale' => $reason !== null,
                'stale_reason' => $reason,
            ]);

            if ($reason !== null) {
                // Seat Breakup is a direct A2 input. Preserve the old immutable
                // snapshot/queues as history, but retire it immediately.
                AllocationInputFreeze::query()->where('status', 'frozen')->update([
                    'status' => 'stale',
                    'updated_at' => now(),
                ]);

                AllocationRun::query()->where('status', 'phase1_complete')->where('is_stale', false)->update([
                    'is_stale' => true, 'stale_reason' => $reason, 'staled_at' => now(), 'updated_at' => now(),
                ]);
                AllocationA4Run::query()->where('status', 'a4_complete')->where('is_stale', false)->update([
                    'is_stale' => true, 'stale_reason' => $reason, 'staled_at' => now(), 'updated_at' => now(),
                ]);
                AllocationA5Run::query()->whereIn('status', ['validated_ok','validated_failed','finalized'])->where('is_stale', false)->update([
                    'is_stale' => true, 'stale_reason' => $reason, 'staled_at' => now(), 'updated_at' => now(),
                ]);
            }
            AllocationProcessingAudit::query()->create([
                'event' => 'SEAT_BREAKUP_FINALIZED', 'actor_id' => $actorId,
                'from_status' => 'validated', 'to_status' => 'finalized',
                'context' => ['seat_breakup_version' => (int)$version->version, 'dataset_hash' => $hash], 'created_at' => now(),
            ]);
            return $version->refresh();
        });
    }

    public function verifiedFinalized(): AllocationSeatBreakupVersion
    {
        $state = AllocationProcessingState::query()->firstOrCreate(['id'=>1], ['status'=>'not_started']);
        $version = $state->finalized_seat_breakup_version_id ? AllocationSeatBreakupVersion::query()->find($state->finalized_seat_breakup_version_id) : null;
        if (!$version || (string)$version->status !== 'finalized' || !$version->dataset_hash) {
            throw ValidationException::withMessages(['seat_breakup' => 'A finalized/frozen Seat Breakup is required.']);
        }
        $circular = $this->circular->verifiedSummary();
        if ((int)$version->circular_version !== (int)$circular['version'] || !hash_equals((string)$version->circular_hash, (string)$circular['dataset_hash'])) {
            throw ValidationException::withMessages(['seat_breakup' => 'SEAT_BREAKUP_CIRCULAR_MISMATCH: Finalized Circular changed. Recreate and finalize Seat Breakup.']);
        }
        if (!hash_equals((string)$version->dataset_hash, $this->hasher->hash($version))) {
            throw ValidationException::withMessages(['seat_breakup' => 'SEAT_BREAKUP_HASH_MISMATCH: Finalized Seat Breakup data changed after finalization.']);
        }
        return $version;
    }

    private function serialEquivalent(string $uploaded, string $expected): bool
    {
        $uploaded = trim($uploaded);
        $expected = trim($expected);
        if ($uploaded === $expected) return true;

        // Excel commonly stores 13.10 as numeric 13.1. cadre_code (and, only
        // when needed, total_post) disambiguates the authoritative Circular row.
        if (is_numeric($uploaded) && is_numeric($expected)) {
            return abs((float) $uploaded - (float) $expected) < 0.000000001;
        }
        return false;
    }

    private function provisionalBreakup(int $total, $settings): array
    {
        if ($total < 10) return [$total, 0, 0, 0];

        // Hamilton/largest-remainder apportionment.
        //
        // 1) Give every bucket its whole-number (floor) share.
        // 2) Distribute the still-unassigned seats by the largest fractional
        //    remainder, so seat conservation is exact and no bucket is rounded
        //    independently beyond the sanctioned total.
        // 3) For an exact remainder tie the locked business priority is:
        //       MQ -> CFF -> EM/PHC
        //    EM and PHC are the same business priority (both 1% by default);
        //    EM-before-PHC below is only a deterministic final tie-break.
        //
        // Integer numerators avoid floating-point boundary ambiguity.
        $percentages = [
            'mq' => (int) $settings->mq_percent,
            'cff' => (int) $settings->cff_percent,
            'em' => (int) $settings->em_percent,
            'phc' => (int) $settings->phc_percent,
        ];

        $bucketOrder = ['mq', 'cff', 'em', 'phc'];
        $tiePriority = ['mq' => 0, 'cff' => 1, 'em' => 2, 'phc' => 2];
        $stableOrder = array_flip($bucketOrder);

        $seats = [];
        $remainders = [];
        foreach ($bucketOrder as $bucket) {
            $numerator = $total * $percentages[$bucket];
            $seats[$bucket] = intdiv($numerator, 100);
            $remainders[$bucket] = $numerator % 100;
        }

        $remaining = $total - array_sum($seats);
        if ($remaining > 0) {
            $ranked = $bucketOrder;
            usort($ranked, function (string $a, string $b) use ($remainders, $tiePriority, $stableOrder): int {
                $byRemainder = $remainders[$b] <=> $remainders[$a];
                if ($byRemainder !== 0) return $byRemainder;

                $byPriority = $tiePriority[$a] <=> $tiePriority[$b];
                if ($byPriority !== 0) return $byPriority;

                return $stableOrder[$a] <=> $stableOrder[$b];
            });

            for ($i = 0; $i < $remaining; $i++) {
                $seats[$ranked[$i]]++;
            }
        }

        return [$seats['mq'], $seats['cff'], $seats['em'], $seats['phc']];
    }

    private function totals(AllocationSeatBreakupVersion $version): array
    {
        $q = $version->rows();
        return [
            'total_rows' => (clone $q)->count(), 'total_posts' => (int)(clone $q)->sum('total_post'),
            'mq_posts' => (int)(clone $q)->sum('mq'), 'cff_posts' => (int)(clone $q)->sum('cff'),
            'em_posts' => (int)(clone $q)->sum('em'), 'phc_posts' => (int)(clone $q)->sum('phc'),
            'validation_summary' => ['validated' => true, 'quota_breakup_minimum_total_posts' => 10],
        ];
    }
}
