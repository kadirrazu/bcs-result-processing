<?php

namespace App\Services\Reporting\DynamicQuery;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Support\Examinations\ExaminationContext;
use App\Models\Examination;

final class DynamicQueryCompiler
{
    public function __construct(
        private readonly SemanticFieldRegistry $registry,
        private readonly DynamicReportAuthority $authority,
        private readonly SemanticValueFormatter $formatter,
        private readonly ExaminationContext $examinationContext,
    ) {}

    /** @return array{count:int,rows:array<int,array<string,mixed>>,columns:array<int,array<string,string>>,warnings:array<int,string>} */
    public function preview(array $definition): array
    {
        $mode = (string) ($definition['mode'] ?? 'detail');
        if (! in_array($mode, ['detail', 'summary'], true)) {
            throw ValidationException::withMessages(['mode' => 'Unsupported report mode.']);
        }

        $fields = array_values(array_unique(array_map('strval', (array) ($definition['fields'] ?? []))));
        $groups = array_values(array_unique(array_map('strval', (array) ($definition['groups'] ?? []))));
        $aggregates = array_values(array_filter((array) ($definition['aggregates'] ?? []), 'is_array'));

        if ($mode === 'detail' && $fields === []) {
            throw ValidationException::withMessages(['fields' => 'Select at least one report field.']);
        }
        if ($mode === 'summary' && $groups === [] && $aggregates === []) {
            throw ValidationException::withMessages(['groups' => 'Summary mode requires at least one group or aggregate.']);
        }
        if (count($fields) > (int) config('dynamic-reports.max_selected_fields', 30)) {
            throw ValidationException::withMessages(['fields' => 'Too many report fields selected.']);
        }
        if (count($groups) > (int) config('dynamic-reports.max_groups', 5)) {
            throw ValidationException::withMessages(['groups' => 'Too many group fields.']);
        }
        if (count($aggregates) > 10) {
            throw ValidationException::withMessages(['aggregates' => 'Too many aggregate columns.']);
        }

        $metadata = [];
        $sources = ['registrations'];
        $referenced = array_values(array_unique(array_merge($fields, $groups)));
        foreach ($referenced as $id) {
            $meta = $this->fieldMeta($id);
            $metadata[$id] = $meta;
            $sources[] = (string) $meta['source'];
        }
        foreach ($aggregates as $aggregate) {
            $fieldId = (string) ($aggregate['field'] ?? '');
            if ($fieldId === '') continue;
            $meta = $this->fieldMeta($fieldId);
            $metadata[$fieldId] = $meta;
            $sources[] = (string) $meta['source'];
        }
        foreach ($this->conditionFieldIds((array) ($definition['conditions'] ?? [])) as $fieldId) {
            $meta = $this->fieldMeta($fieldId);
            $sources[] = (string) $meta['source'];
        }
        foreach ((array) ($definition['sorts'] ?? []) as $sort) {
            if (! is_array($sort) || empty($sort['field'])) continue;
            $meta = $this->fieldMeta((string) $sort['field']);
            $sources[] = (string) $meta['source'];
        }
        $sources = $this->normalizeSources($sources);

        $authority = $this->authority->resolve($sources);
        $this->assertSourcesReady($sources, $authority);

        $base = $this->baseQuery($sources, $authority);
        $this->applyConditions($base, (array) ($definition['conditions'] ?? []));
        $countQuery = clone $base;
        $count = (int) $countQuery->distinct()->count('registrations.id');

        $allowedSizes = (array) config('dynamic-reports.preview_sizes', [5, 10, 20, 50, 100]);
        $size = (int) ($definition['preview_size'] ?? config('dynamic-reports.default_preview_size', 10));
        if (! in_array($size, $allowedSizes, true)) $size = (int) config('dynamic-reports.default_preview_size', 10);

        if ($mode === 'summary') {
            return $this->summaryPreview($base, $groups, $aggregates, $metadata, $definition, $count, $size, $authority['warnings']);
        }

        foreach ($fields as $index => $id) {
            $base->addSelect(DB::raw($metadata[$id]['expression'].' as `f'.$index.'`'));
        }
        $this->applySorts($base, (array) ($definition['sorts'] ?? []));

        $rows = $base->limit($size)->get()->map(function ($row) use ($fields): array {
            $out = [];
            foreach ($fields as $index => $id) $out[$id] = $row->{'f'.$index} ?? null;
            return $out;
        })->all();
        $rows = $this->formatter->formatRows($rows, $fields);

        $labels = (array) ($definition['labels'] ?? []);
        $columns = [];
        foreach ($fields as $id) {
            $columns[] = ['id' => $id, 'label' => trim((string) ($labels[$id] ?? '')) ?: (string) $metadata[$id]['label']];
        }

        return ['count' => $count, 'rows' => $rows, 'columns' => $columns, 'totals' => [], 'total_label' => null, 'warnings' => $authority['warnings']];
    }

    /** @return array{count:int,rows:array<int,array<string,mixed>>,columns:array<int,array<string,string>>,warnings:array<int,string>} */
    private function summaryPreview(Builder $base, array $groups, array $aggregates, array $metadata, array $definition, int $count, int $size, array $warnings): array
    {
        $columns = [];
        $aliases = [];
        $totals = $groups !== []
            ? $this->summaryAggregateTotals(clone $base, $aggregates, $metadata)
            : [];

        foreach ($groups as $index => $id) {
            $meta = $metadata[$id] ?? $this->fieldMeta($id);
            if (! ($meta['groupable'] ?? false)) throw ValidationException::withMessages(['groups' => 'A selected field cannot be grouped.']);
            $alias = 'g'.$index;
            $base->addSelect(DB::raw($meta['expression'].' as `'.$alias.'`'))->groupByRaw((string) $meta['expression']);
            $aliases[$id] = $alias;
            $columns[] = ['id' => $id, 'label' => (string) $meta['label'], 'role' => 'group'];
        }
        foreach ($aggregates as $index => $aggregate) {
            $fieldId = (string) ($aggregate['field'] ?? '');
            $function = strtolower((string) ($aggregate['function'] ?? ''));
            if ($fieldId === '' || $function === '') continue;
            $meta = $metadata[$fieldId] ?? $this->fieldMeta($fieldId);
            if (! in_array($function, (array) ($meta['aggregates'] ?? []), true)) {
                throw ValidationException::withMessages(['aggregates' => 'Aggregate function is not allowed for the selected field.']);
            }
            $sqlFunction = match ($function) {
                'count' => 'COUNT', 'count_distinct' => 'COUNT', 'sum' => 'SUM', 'avg' => 'AVG', 'min' => 'MIN', 'max' => 'MAX',
                default => throw ValidationException::withMessages(['aggregates' => 'Unsupported aggregate function.']),
            };
            $distinct = $function === 'count_distinct' ? 'DISTINCT ' : '';
            $alias = 'a'.$index;
            $base->addSelect(DB::raw($sqlFunction.'('.$distinct.$meta['expression'].') as `'.$alias.'`'));
            $id = 'aggregate.'.$index;
            $aliases[$id] = $alias;
            $label = trim((string) ($aggregate['label'] ?? '')) ?: strtoupper(str_replace('_', ' ', $function)).' · '.(string) $meta['label'];
            $columns[] = ['id' => $id, 'label' => $label, 'role' => 'aggregate', 'function' => $function];
        }
        $aggregateSortApplied = false;
        foreach ($aggregates as $index => $aggregate) {
            $direction = strtolower((string) ($aggregate['sort_direction'] ?? ''));
            if (! in_array($direction, ['asc', 'desc'], true)) continue;
            $base->orderByRaw('`a'.$index.'` '.strtoupper($direction));
            $aggregateSortApplied = true;
        }
        foreach ($groups as $id) {
            $base->orderByRaw((string) ($metadata[$id]['expression'] ?? $this->fieldMeta($id)['expression']).' ASC');
        }
        $rows = $base->limit($size)->get()->map(function ($row) use ($aliases): array {
            $out = [];
            foreach ($aliases as $id => $alias) $out[$id] = $row->{$alias} ?? null;
            return $out;
        })->all();
        $rows = $this->formatter->formatRows($rows, $groups);

        return [
            'count' => $count,
            'rows' => $rows,
            'columns' => $columns,
            'totals' => $totals,
            'total_label' => $totals !== [] ? 'GRAND TOTAL' : null,
            'warnings' => $warnings,
        ];
    }

    /**
     * Calculate summary footer values against the complete filtered dataset.
     * These totals are independent of the preview row limit and group pagination.
     *
     * @param array<int,array<string,mixed>> $aggregates
     * @param array<string,array<string,mixed>> $metadata
     * @return array<string,mixed>
     */
    private function summaryAggregateTotals(Builder $base, array $aggregates, array $metadata): array
    {
        $selects = [];
        $ids = [];

        foreach ($aggregates as $index => $aggregate) {
            $fieldId = (string) ($aggregate['field'] ?? '');
            $function = strtolower((string) ($aggregate['function'] ?? ''));
            if ($fieldId === '' || $function === '') continue;

            $meta = $metadata[$fieldId] ?? $this->fieldMeta($fieldId);
            if (! in_array($function, (array) ($meta['aggregates'] ?? []), true)) {
                throw ValidationException::withMessages(['aggregates' => 'Aggregate function is not allowed for the selected field.']);
            }

            $sqlFunction = match ($function) {
                'count' => 'COUNT',
                'count_distinct' => 'COUNT',
                'sum' => 'SUM',
                'avg' => 'AVG',
                'min' => 'MIN',
                'max' => 'MAX',
                default => throw ValidationException::withMessages(['aggregates' => 'Unsupported aggregate function.']),
            };
            $distinct = $function === 'count_distinct' ? 'DISTINCT ' : '';
            $alias = 'total_a'.$index;
            $selects[] = $sqlFunction.'('.$distinct.$meta['expression'].') as `'.$alias.'`';
            $ids['aggregate.'.$index] = $alias;
        }

        if ($selects === []) return [];

        $row = $base->selectRaw(implode(', ', $selects))->first();
        $totals = [];
        foreach ($ids as $id => $alias) {
            $totals[$id] = $row->{$alias} ?? null;
        }

        return $totals;
    }

    private function baseQuery(array $sources, array $authority): Builder
    {
        $query = DB::connection('exam')->table('registrations');
        if (in_array('preliminary', $sources, true)) {
            $query->leftJoin('preliminary_results', 'preliminary_results.registration_id', '=', 'registrations.id');
        }
        if (in_array('written', $sources, true)) {
            $query->leftJoin('written_results', 'written_results.registration_id', '=', 'registrations.id');
        }
        if (in_array('viva', $sources, true)) {
            $query->leftJoin('viva_results', function ($join) use ($authority): void {
                $join->on('viva_results.registration_id', '=', 'registrations.id')
                    ->where('viva_results.processing_run_id', '=', $authority['viva_processing_run_id']);
            });
        }
        if (in_array('tabulation', $sources, true)) {
            $query->leftJoin('tabulation_results', function ($join) use ($authority): void {
                $join->on('tabulation_results.registration_id', '=', 'registrations.id')
                    ->where('tabulation_results.processing_run_id', '=', $authority['tabulation_run_id']);
            });
        }
        if (in_array('merit', $sources, true)) {
            $query->leftJoin('merit_results', function ($join) use ($authority): void {
                $join->on('merit_results.registration_id', '=', 'registrations.id')
                    ->where('merit_results.processing_run_id', '=', $authority['merit_run_id']);
            });
        }
        if (in_array('choice_validation', $sources, true)) {
            $query->leftJoin('choice_validation_results', function ($join) use ($authority): void {
                $join->on('choice_validation_results.registration_id', '=', 'registrations.id')
                    ->where('choice_validation_results.validation_version', '=', $authority['choice_validation_version']);
            });

            $registrationChoices = DB::connection('exam')->table('choice_validation_source_items')
                ->selectRaw("choice_validation_source_id, GROUP_CONCAT(choice_code ORDER BY position SEPARATOR ',') as choice_codes_csv, COUNT(choice_code) as choice_count")
                ->whereNotNull('choice_code')
                ->where('choice_code', '!=', '')
                ->groupBy('choice_validation_source_id');
            $query->leftJoinSub($registrationChoices, 'dynamic_registration_choices', function ($join): void {
                $join->on('dynamic_registration_choices.choice_validation_source_id', '=', 'choice_validation_results.choice_source_id');
            });
        }
        if (in_array('choice_optimization', $sources, true)) {
            $query->leftJoin('choice_optimization_historical_choices', 'choice_optimization_historical_choices.registration_id', '=', 'registrations.id');

            $latestManual = DB::connection('exam')->table('manual_allocation_choice_adjustment_events')
                ->selectRaw('registration_id, choice_code, MAX(id) as latest_id')
                ->groupBy('registration_id', 'choice_code');
            $activeManual = DB::connection('exam')->table('manual_allocation_choice_adjustment_events as manual_event')
                ->joinSub($latestManual, 'latest_manual_event', function ($join): void {
                    $join->on('latest_manual_event.latest_id', '=', 'manual_event.id');
                })
                ->where('manual_event.action', 'EXCLUDE')
                ->selectRaw("manual_event.registration_id, COUNT(*) as excluded_count, GROUP_CONCAT(manual_event.choice_code ORDER BY manual_event.choice_code SEPARATOR ',') as excluded_codes_csv")
                ->groupBy('manual_event.registration_id');
            $query->leftJoinSub($activeManual, 'dynamic_manual_adjustments', function ($join): void {
                $join->on('dynamic_manual_adjustments.registration_id', '=', 'registrations.id');
            });
        }
        if (in_array('allocation', $sources, true)) {
            $query->leftJoin('allocation_a5_candidate_results', function ($join) use ($authority): void {
                $join->on('allocation_a5_candidate_results.registration_id', '=', 'registrations.id')
                    ->where('allocation_a5_candidate_results.allocation_a5_run_id', '=', $authority['allocation_a5_run_id']);
            });
        }
        if (in_array('allocation_disposition', $sources, true)) {
            $query->leftJoin('allocation_result_dispositions', function ($join) use ($authority): void {
                $join->on('allocation_result_dispositions.registration_id', '=', 'registrations.id')
                    ->where('allocation_result_dispositions.allocation_a5_run_id', '=', $authority['allocation_a5_run_id']);
            });
        }
        if (in_array('circular', $sources, true)) {
            $query->leftJoin('circular_entries', function ($join) use ($authority): void {
                $join->on('circular_entries.id', '=', 'allocation_a5_candidate_results.circular_entry_id')
                    ->where('circular_entries.version', '=', $authority['circular_version']);
            });
        }
        return $query;
    }

    private function applyConditions(Builder $query, array $conditions): void
    {
        if ($conditions === []) return;

        // Backward compatibility for first-slice flat AND definitions.
        if (array_is_list($conditions)) {
            $conditions = ['boolean' => 'and', 'rules' => $conditions];
        }

        $nodeCount = 0;
        $this->applyConditionGroup($query, $conditions, 0, $nodeCount, 'and');
    }

    private function applyConditionGroup(Builder $query, array $group, int $depth, int &$nodeCount, string $outerBoolean): void
    {
        $maxDepth = (int) config('dynamic-reports.max_condition_depth', 4);
        $maxNodes = (int) config('dynamic-reports.max_conditions', 20);
        if ($depth > $maxDepth) throw ValidationException::withMessages(['conditions' => 'Condition nesting is too deep.']);

        $boolean = strtolower((string) ($group['boolean'] ?? 'and'));
        if (! in_array($boolean, ['and', 'or'], true)) throw ValidationException::withMessages(['conditions' => 'Unsupported condition group logic.']);
        $rules = array_values(array_filter((array) ($group['rules'] ?? []), 'is_array'));
        if ($rules === []) return;

        $method = $outerBoolean === 'or' ? 'orWhere' : 'where';
        $query->{$method}(function (Builder $nested) use ($rules, $boolean, $depth, &$nodeCount): void {
            foreach ($rules as $rule) {
                $nodeCount++;
                if ($nodeCount > (int) config('dynamic-reports.max_conditions', 20)) {
                    throw ValidationException::withMessages(['conditions' => 'Too many condition rules.']);
                }
                $ruleBoolean = $boolean === 'or' ? 'or' : 'and';
                if (isset($rule['rules'])) {
                    $this->applyConditionGroup($nested, $rule, $depth + 1, $nodeCount, $ruleBoolean);
                    continue;
                }
                $this->applyConditionRule($nested, $rule, $ruleBoolean);
            }
        });
    }

    private function applyConditionRule(Builder $query, array $condition, string $boolean): void
    {
        if (empty($condition['field']) || empty($condition['operator'])) return;
        $meta = $this->fieldMeta((string) $condition['field']);
        $operator = (string) $condition['operator'];
        if (! in_array($operator, (array) ($meta['operators'] ?? []), true)) {
            throw ValidationException::withMessages(['conditions' => 'Unsupported condition operator.']);
        }
        $column = (string) $meta['expression'];
        $value = $condition['value'] ?? null;
        $value2 = $condition['value2'] ?? null;
        $method = $boolean === 'or' ? 'orWhere' : 'where';

        if (($meta['type'] ?? null) === 'choice-list') {
            $this->applyChoiceListCondition($query, $meta, $operator, $value, $boolean);
            return;
        }

        if (($meta['type'] ?? null) === 'boolean') {
            $this->applyBooleanCondition($query, $column, $operator, $value, $boolean);
            return;
        }

        if ($this->isComputedExpression($column)) {
            $this->applyComputedExpressionCondition($query, $column, $operator, $value, $value2, $boolean);
            return;
        }

        match ($operator) {
            'eq' => $query->{$method}($column, '=', $value),
            'neq' => $query->{$method}($column, '!=', $value),
            'gt' => $query->{$method}($column, '>', $value),
            'gte' => $query->{$method}($column, '>=', $value),
            'lt' => $query->{$method}($column, '<', $value),
            'lte' => $query->{$method}($column, '<=', $value),
            'contains' => $query->{$method}($column, 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $value).'%'),
            'starts_with' => $query->{$method}($column, 'like', str_replace(['%', '_'], ['\\%', '\\_'], (string) $value).'%'),
            'ends_with' => $query->{$method}($column, 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $value)),
            'between' => $query->{$boolean === 'or' ? 'orWhereBetween' : 'whereBetween'}($column, [$value, $value2]),
            'in' => $query->{$boolean === 'or' ? 'orWhereIn' : 'whereIn'}($column, is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)), fn ($v) => $v !== '')),
            'is_blank' => $query->{$method}(fn ($q) => $q->whereNull($column)->orWhere($column, '')),
            'is_not_blank' => $query->{$method}(fn ($q) => $q->whereNotNull($column)->where($column, '!=', '')),
            default => throw ValidationException::withMessages(['conditions' => 'Unsupported condition operator.']),
        };
    }

    private function isComputedExpression(string $expression): bool
    {
        // Registry expressions are server-owned/trusted. A plain semantic column
        // is table.column; everything else is a computed SQL expression and
        // must never be passed to Builder::where() as an identifier.
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*\.[A-Za-z_][A-Za-z0-9_]*$/', trim($expression)) !== 1;
    }

    private function applyComputedExpressionCondition(
        Builder $query,
        string $expression,
        string $operator,
        mixed $value,
        mixed $value2,
        string $boolean,
    ): void {
        $method = $boolean === 'or' ? 'orWhereRaw' : 'whereRaw';
        $escaped = static fn (mixed $raw): string => str_replace(
            ['\\', '%', '_'],
            ['\\\\', '\\%', '\\_'],
            (string) $raw,
        );

        [$sql, $bindings] = match ($operator) {
            'eq' => ["({$expression}) = ?", [$value]],
            'neq' => ["({$expression}) <> ?", [$value]],
            'gt' => ["({$expression}) > ?", [$value]],
            'gte' => ["({$expression}) >= ?", [$value]],
            'lt' => ["({$expression}) < ?", [$value]],
            'lte' => ["({$expression}) <= ?", [$value]],
            'contains' => ["({$expression}) LIKE ? ESCAPE '\\\\\\\\'", ['%'.$escaped($value).'%']],
            'starts_with' => ["({$expression}) LIKE ? ESCAPE '\\\\\\\\'", [$escaped($value).'%']],
            'ends_with' => ["({$expression}) LIKE ? ESCAPE '\\\\\\\\'", ['%'.$escaped($value)]],
            'between' => ["({$expression}) BETWEEN ? AND ?", [$value, $value2]],
            'is_blank' => ["(({$expression}) IS NULL OR ({$expression}) = '')", []],
            'is_not_blank' => ["(({$expression}) IS NOT NULL AND ({$expression}) <> '')", []],
            'in' => $this->computedInCondition($expression, $value),
            default => throw ValidationException::withMessages(['conditions' => 'Unsupported computed-field condition operator.']),
        };

        $query->{$method}($sql, $bindings);
    }

    /** @return array{0:string,1:array<int,mixed>} */
    private function computedInCondition(string $expression, mixed $value): array
    {
        $values = is_array($value)
            ? array_values($value)
            : array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn ($v): bool => $v !== ''));

        if ($values === []) {
            throw ValidationException::withMessages(['conditions' => 'Enter at least one value.']);
        }

        return [
            "({$expression}) IN (".implode(',', array_fill(0, count($values), '?')).')',
            $values,
        ];
    }

    private function applyBooleanCondition(Builder $query, string $expression, string $operator, mixed $value, string $boolean): void
    {
        if (! in_array($operator, ['eq', 'neq'], true)) {
            throw ValidationException::withMessages(['conditions' => 'Unsupported boolean condition operator.']);
        }

        $normalized = match (strtolower(trim((string) $value))) {
            '1', 'true', 'yes', 'y' => 1,
            '0', 'false', 'no', 'n' => 0,
            default => null,
        };

        if ($normalized === null) {
            throw ValidationException::withMessages(['conditions' => 'Boolean condition value must be Yes or No.']);
        }

        // Boolean semantic fields may be computed SQL expressions (for example
        // CFF means has_ff_quota = 2). Treat the configured expression as SQL,
        // never as a quoted identifier via Builder::where().
        $sql = '(' . $expression . ') ' . ($operator === 'neq' ? '<>' : '=') . ' ?';
        $query->{$boolean === 'or' ? 'orWhereRaw' : 'whereRaw'}($sql, [$normalized]);
    }

    /** @param array<string,mixed> $meta */
    private function applyChoiceListCondition(Builder $query, array $meta, string $operator, mixed $value, string $boolean): void
    {
        $storage = (string) ($meta['choice_storage'] ?? 'json');
        $column = (string) ($meta['condition_expression'] ?? $meta['expression']);
        $method = $boolean === 'or' ? 'orWhere' : 'where';
        $values = is_array($value)
            ? array_values(array_filter(array_map(static fn ($v): string => trim((string) $v), $value), static fn (string $v): bool => $v !== ''))
            : array_values(array_filter(array_map('trim', explode(',', (string) $value)), static fn (string $v): bool => $v !== ''));

        if (in_array($operator, ['is_blank', 'is_not_blank'], true)) {
            $blankSql = match ($storage) {
                'csv' => "({$column} IS NULL OR {$column} = '')",
                'final_json' => "({$column} IS NULL OR GREATEST(COALESCE(JSON_LENGTH({$column}),0)-COALESCE(dynamic_manual_adjustments.excluded_count,0),0) = 0)",
                default => "({$column} IS NULL OR JSON_LENGTH({$column}) = 0)",
            };
            $sql = $operator === 'is_blank' ? $blankSql : "NOT {$blankSql}";
            $query->{$boolean === 'or' ? 'orWhereRaw' : 'whereRaw'}($sql);
            return;
        }

        if ($values === []) {
            throw ValidationException::withMessages(['conditions' => 'Enter at least one choice code.']);
        }

        $containsSql = function (string $code) use ($storage, $column): array {
            if ($storage === 'csv') {
                return ["FIND_IN_SET(?, COALESCE({$column}, '')) > 0", [$code]];
            }

            $sql = "JSON_SEARCH({$column}, 'one', ?) IS NOT NULL";
            $bindings = [$code];
            if ($storage === 'final_json') {
                $sql .= " AND FIND_IN_SET(?, COALESCE(dynamic_manual_adjustments.excluded_codes_csv, '')) = 0";
                $bindings[] = $code;
            }
            return [$sql, $bindings];
        };

        if (in_array($operator, ['contains_choice', 'not_contains_choice'], true)) {
            [$sql, $bindings] = $containsSql($values[0]);
            if ($operator === 'not_contains_choice') $sql = "NOT ({$sql})";
            $query->{$boolean === 'or' ? 'orWhereRaw' : 'whereRaw'}($sql, $bindings);
            return;
        }

        $outer = $boolean === 'or' ? 'orWhere' : 'where';
        $query->{$outer}(function (Builder $nested) use ($operator, $values, $containsSql): void {
            foreach ($values as $index => $code) {
                [$sql, $bindings] = $containsSql($code);
                if ($operator === 'contains_any') {
                    $index === 0 ? $nested->whereRaw($sql, $bindings) : $nested->orWhereRaw($sql, $bindings);
                } elseif ($operator === 'contains_all') {
                    $nested->whereRaw($sql, $bindings);
                } else {
                    throw ValidationException::withMessages(['conditions' => 'Unsupported choice-list operator.']);
                }
            }
        });
    }

    /** @param array<int,string> $sources @param array<string,mixed> $authority */
    private function assertSourcesReady(array $sources, array $authority): void
    {
        $required = [
            'preliminary' => ['preliminary_finalization_run_id', 'Preliminary'],
            'written' => ['written_processing_run_id', 'Written'],
            'viva' => ['viva_processing_run_id', 'Viva'],
            'tabulation' => ['tabulation_run_id', 'Tabulation'],
            'merit' => ['merit_run_id', 'Merit'],
            'allocation' => ['allocation_a5_run_id', 'Allocation'],
            'allocation_disposition' => ['allocation_a5_run_id', 'Allocation disposition'],
            'choice_validation' => ['choice_validation_finalization_run_id', 'Choice Validation'],
            'choice_optimization' => ['choice_optimization_hash', 'Choice Optimization'],
            'circular' => ['circular_version', 'Circular'],
        ];
        foreach ($required as $source => [$key, $label]) {
            if (in_array($source, $sources, true) && empty($authority[$key])) {
                throw ValidationException::withMessages(['query' => $label.' source is not finalized/current.']);
            }
        }
    }

    /** @param array<int,string> $sources @return array<int,string> */
    private function normalizeSources(array $sources): array
    {
        $sources = array_values(array_unique(array_map('strval', $sources)));
        // Candidate-centric Circular fields describe the candidate's allocated
        // post, so they intentionally depend on the current finalized A5 row.
        if (in_array('circular', $sources, true) && ! in_array('allocation', $sources, true)) {
            $sources[] = 'allocation';
        }
        if (in_array('allocation_disposition', $sources, true) && ! in_array('allocation', $sources, true)) {
            $sources[] = 'allocation';
        }
        return array_values(array_unique($sources));
    }

    /** @return array<int,string> */
    private function conditionFieldIds(array $conditions): array
    {
        if ($conditions === []) return [];
        if (array_is_list($conditions)) $conditions = ['rules' => $conditions];
        $ids = [];
        $walk = function (array $node) use (&$walk, &$ids): void {
            if (! empty($node['field'])) $ids[] = (string) $node['field'];
            foreach ((array) ($node['rules'] ?? []) as $child) if (is_array($child)) $walk($child);
        };
        $walk($conditions);
        return array_values(array_unique($ids));
    }

    private function applySorts(Builder $query, array $sorts): void
    {
        if (count($sorts) > (int) config('dynamic-reports.max_sorts', 5)) {
            throw ValidationException::withMessages(['sorts' => 'Too many sort fields.']);
        }
        foreach ($sorts as $sort) {
            if (! is_array($sort) || empty($sort['field'])) continue;
            $meta = $this->fieldMeta((string) $sort['field']);
            if (! ($meta['sortable'] ?? false)) continue;
            $direction = strtolower((string) ($sort['direction'] ?? 'asc')) === 'desc' ? 'DESC' : 'ASC';
            $query->orderByRaw((string) $meta['expression'].' '.$direction);
        }
        if ($sorts === []) $query->orderBy('registrations.id');
    }
    /** @return array{count:int,columns:array<int,array<string,string>>,rows:iterable<int,array<string,mixed>>,warnings:array<int,string>,authority:array<string,mixed>} */
    public function exportDataset(array $definition): array
    {
        $mode = (string) ($definition['mode'] ?? 'detail');
        $fields = array_values(array_unique(array_map('strval', (array) ($definition['fields'] ?? []))));
        $groups = array_values(array_unique(array_map('strval', (array) ($definition['groups'] ?? []))));
        $aggregates = array_values(array_filter((array) ($definition['aggregates'] ?? []), 'is_array'));
        if ($mode === 'detail' && $fields === []) throw ValidationException::withMessages(['fields' => 'Select at least one report field.']);
        if ($mode === 'summary' && $groups === [] && $aggregates === []) throw ValidationException::withMessages(['groups' => 'Summary mode requires at least one group or aggregate.']);

        $metadata = [];
        $sources = ['registrations'];
        foreach (array_values(array_unique(array_merge($fields, $groups))) as $id) {
            $meta = $this->fieldMeta($id); $metadata[$id] = $meta; $sources[] = (string) $meta['source'];
        }
        foreach ($aggregates as $aggregate) {
            $fieldId = (string) ($aggregate['field'] ?? ''); if ($fieldId === '') continue;
            $meta = $this->fieldMeta($fieldId); $metadata[$fieldId] = $meta; $sources[] = (string) $meta['source'];
        }
        foreach ($this->conditionFieldIds((array) ($definition['conditions'] ?? [])) as $fieldId) {
            $meta = $this->fieldMeta($fieldId); $sources[] = (string) $meta['source'];
        }
        foreach ((array) ($definition['sorts'] ?? []) as $sort) {
            if (! is_array($sort) || empty($sort['field'])) continue;
            $meta = $this->fieldMeta((string) $sort['field']); $sources[] = (string) $meta['source'];
        }
        $sources = $this->normalizeSources($sources);
        $authority = $this->authority->resolve($sources);
        $this->assertSourcesReady($sources, $authority);

        $base = $this->baseQuery($sources, $authority);
        $this->applyConditions($base, (array) ($definition['conditions'] ?? []));
        $count = (int) (clone $base)->distinct()->count('registrations.id');
        $labels = (array) ($definition['labels'] ?? []);

        if ($mode === 'summary') {
            $columns = []; $aliases = [];
            $totals = $groups !== [] ? $this->summaryAggregateTotals(clone $base, $aggregates, $metadata) : [];
            foreach ($groups as $index => $id) {
                $meta = $metadata[$id] ?? $this->fieldMeta($id);
                if (! ($meta['groupable'] ?? false)) throw ValidationException::withMessages(['groups' => 'A selected field cannot be grouped.']);
                $alias='g'.$index; $base->addSelect(DB::raw($meta['expression'].' as `'.$alias.'`'))->groupByRaw((string) $meta['expression']);
                $aliases[$id]=$alias; $columns[]=['id'=>$id,'label'=>trim((string)($labels[$id]??'')) ?: (string)$meta['label'],'role'=>'group'];
            }
            foreach ($aggregates as $index => $aggregate) {
                $fieldId=(string)($aggregate['field']??''); $function=strtolower((string)($aggregate['function']??'')); if($fieldId===''||$function==='') continue;
                $meta=$metadata[$fieldId]??$this->fieldMeta($fieldId);
                if(!in_array($function,(array)($meta['aggregates']??[]),true)) throw ValidationException::withMessages(['aggregates'=>'Aggregate function is not allowed for the selected field.']);
                $sqlFunction=match($function){'count'=>'COUNT','count_distinct'=>'COUNT','sum'=>'SUM','avg'=>'AVG','min'=>'MIN','max'=>'MAX',default=>throw ValidationException::withMessages(['aggregates'=>'Unsupported aggregate function.'])};
                $alias='a'.$index; $distinct=$function==='count_distinct'?'DISTINCT ':'';
                $base->addSelect(DB::raw($sqlFunction.'('.$distinct.$meta['expression'].') as `'.$alias.'`'));
                $id='aggregate.'.$index; $aliases[$id]=$alias;
                $columns[]=['id'=>$id,'label'=>trim((string)($aggregate['label']??'')) ?: strtoupper(str_replace('_',' ',$function)).' · '.(string)$meta['label'],'role'=>'aggregate','function'=>$function];
            }
            foreach($aggregates as $index=>$aggregate){$direction=strtolower((string)($aggregate['sort_direction']??''));if(in_array($direction,['asc','desc'],true))$base->orderByRaw('`a'.$index.'` '.strtoupper($direction));} foreach($groups as $id) $base->orderByRaw((string)($metadata[$id]['expression']??$this->fieldMeta($id)['expression']).' ASC');
            $formatter=$this->formatter;
            $rows=(function() use($base,$aliases,$groups,$formatter){ foreach($base->cursor() as $row){$out=[];foreach($aliases as $id=>$alias)$out[$id]=$row->{$alias}??null;$formatted=$formatter->formatRows([$out],$groups);yield $formatted[0]??$out;}})();
            return ['count'=>$count,'columns'=>$columns,'rows'=>$rows,'totals'=>$totals,'total_label'=>$totals!==[]?'GRAND TOTAL':null,'warnings'=>$authority['warnings'],'authority'=>$authority];
        }

        foreach($fields as $index=>$id) $base->addSelect(DB::raw($metadata[$id]['expression'].' as `f'.$index.'`'));
        $this->applySorts($base,(array)($definition['sorts']??[]));
        $columns=[]; foreach($fields as $id)$columns[]=['id'=>$id,'label'=>trim((string)($labels[$id]??'')) ?: (string)$metadata[$id]['label']];
        $formatter=$this->formatter;
        $rows=(function() use($base,$fields,$formatter){foreach($base->cursor() as $row){$out=[];foreach($fields as $index=>$id)$out[$id]=$row->{'f'.$index}??null;$formatted=$formatter->formatRows([$out],$fields);yield $formatted[0]??$out;}})();
        return ['count'=>$count,'columns'=>$columns,'rows'=>$rows,'totals'=>[],'total_label'=>null,'warnings'=>$authority['warnings'],'authority'=>$authority];
    }

    /** @return array<string,mixed> */
    private function fieldMeta(string $id): array
    {
        $meta = $this->registry->get($id);
        $derived = (string) ($meta['derived'] ?? '');
        if (! in_array($derived, ['age_years', 'age_group'], true)) {
            return $meta;
        }

        $ageDate = $this->examinationContext->current()?->age_calculation_date;
        if ($ageDate === null) {
            $database = (string) DB::connection('exam')->getDatabaseName();
            $ageDate = Examination::query()->where('database_name', $database)->value('age_calculation_date');
            $ageDate = $ageDate ? \Illuminate\Support\Carbon::parse((string) $ageDate) : null;
        }
        if ($ageDate === null) {
            throw ValidationException::withMessages([
                'query' => 'Age Calculation Date is not configured for the selected examination; age-derived fields cannot be queried.',
            ]);
        }

        $date = $ageDate->format('Y-m-d');
        $age = "TIMESTAMPDIFF(YEAR, registrations.birth_date, DATE('{$date}'))";
        $meta['expression'] = $derived === 'age_years'
            ? $age
            : "(CASE WHEN registrations.birth_date IS NULL THEN NULL WHEN {$age} < 21 THEN 'under_21' WHEN {$age} <= 23 THEN '21_23' WHEN {$age} <= 26 THEN '24_26' WHEN {$age} <= 29 THEN '27_29' WHEN {$age} <= 32 THEN '30_32' ELSE 'above_32' END)";

        return $meta;
    }

    /** @return array<string,mixed> */
    public function authoritySnapshot(array $definition): array
    {
        $ids = array_merge((array) ($definition['fields'] ?? []), (array) ($definition['groups'] ?? []), $this->conditionFieldIds((array) ($definition['conditions'] ?? [])));
        foreach ((array) ($definition['aggregates'] ?? []) as $aggregate) if (is_array($aggregate) && ! empty($aggregate['field'])) $ids[] = (string) $aggregate['field'];
        foreach ((array) ($definition['sorts'] ?? []) as $sort) if (is_array($sort) && ! empty($sort['field'])) $ids[] = (string) $sort['field'];
        $sources = ['registrations'];
        foreach (array_values(array_unique(array_filter(array_map('strval', $ids)))) as $id) $sources[] = (string) $this->fieldMeta($id)['source'];
        return $this->authority->resolve($this->normalizeSources($sources));
    }

}
