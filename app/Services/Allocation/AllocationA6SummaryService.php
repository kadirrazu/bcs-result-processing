<?php

namespace App\Services\Allocation;

use App\Models\AllocationA4SeatLedger;
use App\Models\AllocationA5Run;
use App\Models\AllocationResultDisposition;
use App\Models\CircularEntry;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Read-only Allocation A6 summary projection over the finalized A5-bound A4 seat ledger.
 *
 * No allocation calculation happens here. Every figure is a presentation of the
 * committed A4 seat ledger plus A5 validated capacity totals and Circular snapshots.
 */
final class AllocationA6SummaryService
{
    public function __construct(private readonly AllocationA6ReportService $reports) {}

    /** @return Collection<int,array<string,mixed>> */
    public function rows(AllocationA5Run $a5): Collection
    {
        $capacities = $a5->capacityResults()->get();
        $entries = CircularEntry::query()
            ->whereIn('id', $capacities->pluck('circular_entry_id'))
            ->get()
            ->keyBy('id');

        $ledgers = AllocationA4SeatLedger::query()
            ->where('allocation_a4_run_id', (int) $a5->allocation_a4_run_id)
            ->whereIn('circular_entry_id', $capacities->pluck('circular_entry_id'))
            ->get()
            ->keyBy('circular_entry_id');

        $abbreviations = $this->reports->abbreviations($capacities->pluck('cadre_code'));
        $dispositionCounts = AllocationResultDisposition::query()
            ->where('allocation_a5_run_id', (int) $a5->id)
            ->whereIn('status', ['WITHHELD','CANCELLED'])
            ->selectRaw('cadre_code, status, COUNT(*) as aggregate')
            ->groupBy('cadre_code','status')->get()->groupBy('cadre_code');

        return $capacities->map(function ($capacity) use ($entries, $ledgers, $abbreviations, $dispositionCounts): array {
            $entryId = (int) $capacity->circular_entry_id;
            $entry = $entries->get($entryId);
            $ledger = $ledgers->get($entryId);

            if (! $entry || ! $ledger) {
                throw new RuntimeException(
                    'A6_ALLOCATION_SUMMARY_SOURCE_INCOMPLETE: Circular entry or A4 seat ledger is missing for entry '.$entryId.'.'
                );
            }

            $type = strtoupper((string) ($entry->cadre_type?->value ?? $entry->cadre_type ?? ''));
            $groupRank = $type === 'GG' ? 0 : 1;
            $groupLabel = $groupRank === 0 ? 'GENERAL' : 'TECHNICAL / PROFESSIONAL';

            $mqPost = (int) $ledger->mq_capacity;
            $cffPost = (int) $ledger->cff_capacity;
            $emPost = (int) $ledger->em_capacity;
            $phcPost = (int) $ledger->phc_capacity;

            $cffConverted = (int) $ledger->converted_cff;
            $emConverted = (int) $ledger->converted_em;
            $phcConverted = (int) $ledger->converted_phc;
            $convertedIn = $cffConverted + $emConverted + $phcConverted;

            $meritCapacity = (int) $ledger->merit_capacity;
            $meritAllocated = (int) $ledger->mq_occupied;
            $withheld = (int) optional($dispositionCounts->get((int) $capacity->cadre_code, collect())->firstWhere('status','WITHHELD'))->aggregate;
            $cancelled = (int) optional($dispositionCounts->get((int) $capacity->cadre_code, collect())->firstWhere('status','CANCELLED'))->aggregate;

            return [
                'group_rank' => $groupRank,
                'category' => $groupLabel,
                'serial' => (int) $entry->cadre_serial,
                'sub_serial' => $entry->sub_serial === null ? null : (int) $entry->sub_serial,
                'serial_label' => (string) $entry->cadre_serial.($entry->sub_serial === null ? '' : '.'.(int) $entry->sub_serial),
                'cadre_code' => (int) $capacity->cadre_code,
                'cadre_abbr' => (string) $abbreviations->get((int) $capacity->cadre_code, 'UNMAPPED'),
                'cadre_name' => trim((string) $entry->cadre_name_snapshot),
                'post_name' => trim((string) $entry->post_name_snapshot),

                'total_post' => (int) $capacity->sanctioned_posts,
                'total_allocated' => (int) $capacity->allocated_count,
                'total_vacant' => (int) $capacity->remaining_posts,
                'withheld_count' => $withheld,
                'cancelled_count' => $cancelled,
                'published_active' => max(0, (int) $capacity->allocated_count - $withheld - $cancelled),

                'mq_post' => $mqPost,
                'converted_in' => $convertedIn,
                'merit_capacity' => $meritCapacity,
                'merit_allocated' => $meritAllocated,
                'merit_rest' => max(0, $meritCapacity - $meritAllocated),

                'cff_post' => $cffPost,
                'cff_allocated' => (int) $ledger->cff_occupied,
                'cff_converted' => $cffConverted,
                'cff_rest' => max(0, $cffPost - (int) $ledger->cff_occupied - $cffConverted),

                'em_post' => $emPost,
                'em_allocated' => (int) $ledger->em_occupied,
                'em_converted' => $emConverted,
                'em_rest' => max(0, $emPost - (int) $ledger->em_occupied - $emConverted),

                'phc_post' => $phcPost,
                'phc_allocated' => (int) $ledger->phc_occupied,
                'phc_converted' => $phcConverted,
                'phc_rest' => max(0, $phcPost - (int) $ledger->phc_occupied - $phcConverted),

                'nm_allocations' => (int) $ledger->nm_count,
                'shifted_allocations' => (int) $ledger->shifted_count,
                'quota_to_merit' => (int) $ledger->quota_to_merit_count,
            ];
        })->sortBy(fn (array $row) => sprintf(
            '%02d-%08d-%08d-%08d',
            $row['group_rank'],
            $row['serial'],
            ($row['sub_serial'] ?? -1) + 1,
            $row['cadre_code'],
        ))->values();
    }

    /** @param Collection<int,array<string,mixed>> $rows @return array<string,int> */
    public function totals(Collection $rows): array
    {
        $numeric = [
            'total_post','total_allocated','total_vacant','withheld_count','cancelled_count','published_active',
            'mq_post','converted_in','merit_capacity','merit_allocated','merit_rest',
            'cff_post','cff_allocated','cff_converted','cff_rest',
            'em_post','em_allocated','em_converted','em_rest',
            'phc_post','phc_allocated','phc_converted','phc_rest',
            'nm_allocations','shifted_allocations','quota_to_merit',
        ];

        return collect($numeric)->mapWithKeys(
            fn (string $key) => [$key => (int) $rows->sum(fn (array $row) => (int) $row[$key])]
        )->all();
    }


    /** @return array<int,string> */
    public function shortExcelHeaders(): array
    {
        return [
            'Category','SL','Cadre Code','Cadre Abbreviation','Cadre Name','Post Name',
            'Total Post','Total Allocated','Withheld','Cancelled','Published Active','Total Vacant',
        ];
    }

    /** @return array<int,mixed> */
    public function shortExcelRow(array $row): array
    {
        return [
            $row['category'], $row['serial_label'], $row['cadre_code'], $row['cadre_abbr'], $row['cadre_name'], $row['post_name'],
            $row['total_post'], $row['total_allocated'], $row['withheld_count'], $row['cancelled_count'], $row['published_active'], $row['total_vacant'],
        ];
    }

    /** @return array<int,string> */
    public function excelHeaders(): array
    {
        return [
            'Category','SL','Cadre Code','Cadre Abbreviation','Cadre Name','Post Name',
            'Total Post','Total Allocated','Withheld','Cancelled','Published Active','Total Vacant',
            'Merit Pool - Original MQ Post','Merit Pool - NM Converted In','Merit Pool - Capacity','Merit Pool - Allocated','Merit Pool - Rest',
            'CFF - Post','CFF - Allocated','CFF - NM Converted','CFF - Rest',
            'EM - Post','EM - Allocated','EM - NM Converted','EM - Rest',
            'PHC - Post','PHC - Allocated','PHC - NM Converted','PHC - Rest',
            'Phase-2 - NM Allocations','Phase-2 - Shifted','Phase-2 - Quota to Merit',
        ];
    }

    /** @return array<int,mixed> */
    public function excelRow(array $row): array
    {
        return [
            $row['category'], $row['serial_label'], $row['cadre_code'], $row['cadre_abbr'], $row['cadre_name'], $row['post_name'],
            $row['total_post'], $row['total_allocated'], $row['withheld_count'], $row['cancelled_count'], $row['published_active'], $row['total_vacant'],
            $row['mq_post'], $row['converted_in'], $row['merit_capacity'], $row['merit_allocated'], $row['merit_rest'],
            $row['cff_post'], $row['cff_allocated'], $row['cff_converted'], $row['cff_rest'],
            $row['em_post'], $row['em_allocated'], $row['em_converted'], $row['em_rest'],
            $row['phc_post'], $row['phc_allocated'], $row['phc_converted'], $row['phc_rest'],
            $row['nm_allocations'], $row['shifted_allocations'], $row['quota_to_merit'],
        ];
    }
}
