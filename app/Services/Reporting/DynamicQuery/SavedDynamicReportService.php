<?php

namespace App\Services\Reporting\DynamicQuery;

use App\Models\ReportingDynamicRunHistory;
use App\Models\ReportingDynamicSavedReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class SavedDynamicReportService
{
    public function __construct(private readonly SemanticFieldRegistry $registry) {}

    /** @return array<int,array<string,mixed>> */
    public function list(): array
    {
        return ReportingDynamicSavedReport::query()
            ->latest('updated_at')
            ->limit((int) config('dynamic-reports.max_saved_reports', 100))
            ->get(['id', 'name', 'description', 'version', 'updated_at'])
            ->map(fn (ReportingDynamicSavedReport $report): array => [
                'id' => (int) $report->id,
                'name' => (string) $report->name,
                'description' => (string) ($report->description ?? ''),
                'version' => (int) $report->version,
                'updated_at' => $report->updated_at?->toIso8601String(),
            ])->all();
    }

    /** @return array<string,mixed> */
    public function load(int $id): array
    {
        $report = ReportingDynamicSavedReport::query()->findOrFail($id);

        return [
            'id' => (int) $report->id,
            'name' => (string) $report->name,
            'description' => (string) ($report->description ?? ''),
            'version' => (int) $report->version,
            'definition' => (array) $report->definition,
        ];
    }

    /** @return array<string,mixed> */
    public function save(?int $id, string $name, ?string $description, array $definition, ?int $userId): array
    {
        $definition = $this->canonicalDefinition($definition);
        $hash = hash('sha256', json_encode($definition, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        return DB::connection('exam')->transaction(function () use ($id, $name, $description, $definition, $hash, $userId): array {
            if ($id) {
                $report = ReportingDynamicSavedReport::query()->lockForUpdate()->findOrFail($id);
                $report->fill([
                    'name' => trim($name),
                    'description' => $this->nullableTrim($description),
                    'definition' => $definition,
                    'definition_hash' => $hash,
                    'version' => (int) $report->version + 1,
                    'updated_by' => $userId,
                ])->save();
            } else {
                if (ReportingDynamicSavedReport::query()->count() >= (int) config('dynamic-reports.max_saved_reports', 100)) {
                    throw ValidationException::withMessages(['name' => 'Saved report limit reached for this examination.']);
                }
                $report = ReportingDynamicSavedReport::query()->create([
                    'name' => trim($name),
                    'description' => $this->nullableTrim($description),
                    'definition' => $definition,
                    'definition_hash' => $hash,
                    'version' => 1,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                ]);
            }

            return $this->load((int) $report->id);
        });
    }

    public function delete(int $id): void
    {
        ReportingDynamicSavedReport::query()->findOrFail($id)->delete();
    }

    public function recordPreview(int $savedReportId, array $definition, int $matchingCount, int $previewSize, int $durationMs, ?int $userId): void
    {
        $this->recordExecution($savedReportId, $definition, 'preview', $matchingCount, $previewSize, $durationMs, 'completed', null, $userId);
    }

    public function recordExecution(
        int $savedReportId,
        array $definition,
        string $runType,
        int $matchingCount,
        int $previewSize,
        int $durationMs,
        string $status,
        ?string $errorMessage,
        ?int $userId,
    ): void {
        if (! $this->historyTableReady()) {
            return;
        }

        try {
            $report = ReportingDynamicSavedReport::query()->find($savedReportId);
            if (! $report) {
                return;
            }

            $runType = in_array($runType, ['preview', 'xlsx_export', 'pdf_export'], true) ? $runType : 'preview';
            $status = in_array($status, ['completed', 'failed'], true) ? $status : 'completed';

            ReportingDynamicRunHistory::query()->create([
                'saved_report_id' => (int) $report->id,
                'saved_report_version' => (int) $report->version,
                'run_type' => $runType,
                'definition_snapshot' => $this->canonicalDefinition($definition),
                'matching_count' => max(0, $matchingCount),
                'preview_size' => max(0, $previewSize),
                'duration_ms' => max(0, $durationMs),
                'status' => $status,
                'error_message' => $errorMessage ? mb_substr($errorMessage, 0, 65000) : null,
                'executed_by' => $userId,
                'executed_at' => now(),
            ]);
        } catch (\Throwable) {
            // Audit/history must never make a valid preview/export fail.
            return;
        }
    }

    /** @return array<int,array<string,mixed>> */
    public function recentRuns(?int $savedReportId = null): array
    {
        if (! $this->historyTableReady()) {
            return [];
        }

        return ReportingDynamicRunHistory::query()
            ->when($savedReportId, fn ($query) => $query->where('saved_report_id', $savedReportId))
            ->latest('executed_at')
            ->limit(20)
            ->get(['id', 'saved_report_id', 'saved_report_version', 'run_type', 'matching_count', 'preview_size', 'duration_ms', 'status', 'executed_at'])
            ->map(fn (ReportingDynamicRunHistory $run): array => [
                'id' => (int) $run->id,
                'saved_report_id' => (int) $run->saved_report_id,
                'version' => (int) $run->saved_report_version,
                'run_type' => (string) $run->run_type,
                'matching_count' => (int) $run->matching_count,
                'preview_size' => (int) $run->preview_size,
                'duration_ms' => (int) $run->duration_ms,
                'status' => (string) $run->status,
                'executed_at' => $run->executed_at?->toIso8601String(),
            ])->all();
    }

    /** @return array<string,mixed> */
    public function canonicalDefinition(array $definition): array
    {
        $mode = in_array(($definition['mode'] ?? 'detail'), ['detail', 'summary'], true) ? (string) $definition['mode'] : 'detail';
        $fields = $this->approvedIds((array) ($definition['fields'] ?? []), 'selectable');
        $groups = $this->approvedIds((array) ($definition['groups'] ?? []), 'groupable');
        $sorts = [];
        foreach ((array) ($definition['sorts'] ?? []) as $sort) {
            if (! is_array($sort) || empty($sort['field'])) continue;
            $meta = $this->registry->get((string) $sort['field']);
            if (! ($meta['sortable'] ?? false)) continue;
            $sorts[] = ['field' => (string) $sort['field'], 'direction' => strtolower((string) ($sort['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc'];
        }

        $aggregates = [];
        foreach ((array) ($definition['aggregates'] ?? []) as $aggregate) {
            if (! is_array($aggregate) || empty($aggregate['field']) || empty($aggregate['function'])) continue;
            $meta = $this->registry->get((string) $aggregate['field']);
            $function = strtolower((string) $aggregate['function']);
            if (! in_array($function, (array) ($meta['aggregates'] ?? []), true)) {
                throw ValidationException::withMessages(['aggregates' => 'An aggregate is not allowed for its selected field.']);
            }
            $aggregates[] = [
                'field' => (string) $aggregate['field'],
                'function' => $function,
                'label' => mb_substr(trim((string) ($aggregate['label'] ?? '')), 0, 120),
            ];
        }

        return [
            'mode' => $mode,
            'fields' => $fields,
            'conditions' => $this->canonicalConditions((array) ($definition['conditions'] ?? [])),
            'sorts' => array_slice($sorts, 0, (int) config('dynamic-reports.max_sorts', 5)),
            'groups' => array_slice($groups, 0, (int) config('dynamic-reports.max_groups', 5)),
            'aggregates' => array_slice($aggregates, 0, 10),
            'labels' => $this->canonicalLabels((array) ($definition['labels'] ?? [])),
            'preview_size' => $this->previewSize((int) ($definition['preview_size'] ?? config('dynamic-reports.default_preview_size', 10))),
            'report_title' => mb_substr(trim((string) ($definition['report_title'] ?? 'Dynamic Query Report')), 0, 180),
            'show_serial' => (bool) ($definition['show_serial'] ?? true),
            'show_page_number' => (bool) ($definition['show_page_number'] ?? true),
            'show_timestamp' => (bool) ($definition['show_timestamp'] ?? true),
        ];
    }

    /** @return array<int,string> */
    private function approvedIds(array $ids, string $capability): array
    {
        $approved = [];
        foreach (array_values(array_unique(array_map('strval', $ids))) as $id) {
            $meta = $this->registry->get($id);
            if ($meta[$capability] ?? false) $approved[] = $id;
        }
        return array_slice($approved, 0, (int) config('dynamic-reports.max_selected_fields', 30));
    }

    /** @return array<string,mixed> */
    private function canonicalConditions(array $conditions): array
    {
        if ($conditions === []) return ['boolean' => 'and', 'rules' => []];
        if (array_is_list($conditions)) $conditions = ['boolean' => 'and', 'rules' => $conditions];
        $nodes = 0;
        return $this->canonicalConditionGroup($conditions, 0, $nodes);
    }

    /** @return array<string,mixed> */
    private function canonicalConditionGroup(array $group, int $depth, int &$nodes): array
    {
        if ($depth > (int) config('dynamic-reports.max_condition_depth', 4)) {
            throw ValidationException::withMessages(['conditions' => 'Condition nesting is too deep.']);
        }
        $out = ['boolean' => strtolower((string) ($group['boolean'] ?? 'and')) === 'or' ? 'or' : 'and', 'rules' => []];
        foreach ((array) ($group['rules'] ?? []) as $rule) {
            if (! is_array($rule)) continue;
            if (++$nodes > (int) config('dynamic-reports.max_conditions', 20)) {
                throw ValidationException::withMessages(['conditions' => 'Too many condition rules.']);
            }
            if (isset($rule['rules'])) {
                $out['rules'][] = $this->canonicalConditionGroup($rule, $depth + 1, $nodes);
                continue;
            }
            if (empty($rule['field']) || empty($rule['operator'])) continue;
            $meta = $this->registry->get((string) $rule['field']);
            $operator = (string) $rule['operator'];
            if (! in_array($operator, (array) ($meta['operators'] ?? []), true)) {
                throw ValidationException::withMessages(['conditions' => 'Unsupported condition operator.']);
            }
            $out['rules'][] = [
                'field' => (string) $rule['field'], 'operator' => $operator,
                'value' => $rule['value'] ?? null, 'value2' => $rule['value2'] ?? null,
            ];
        }
        return $out;
    }

    /** @return array<string,string> */
    private function canonicalLabels(array $labels): array
    {
        $out = [];
        foreach ($labels as $fieldId => $label) {
            $this->registry->get((string) $fieldId);
            $label = mb_substr(trim((string) $label), 0, 120);
            if ($label !== '') $out[(string) $fieldId] = $label;
        }
        return $out;
    }


    private function historyTableReady(): bool
    {
        return Schema::connection('exam')->hasTable('reporting_dynamic_run_history');
    }

    private function previewSize(int $size): int
    {
        $allowed = array_map('intval', (array) config('dynamic-reports.preview_sizes', [5, 10, 20]));
        return in_array($size, $allowed, true) ? $size : (int) config('dynamic-reports.default_preview_size', 10);
    }

    private function nullableTrim(?string $value): ?string
    {
        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }
}
