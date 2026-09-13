<?php

namespace App\Services\Reporting\DynamicQuery;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DynamicQueryCompiler
{
    public function __construct(
        private readonly SemanticFieldRegistry $registry,
        private readonly DynamicReportAuthority $authority,
        private readonly SemanticValueFormatter $formatter,
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
            $meta = $this->registry->get($id);
            $metadata[$id] = $meta;
            $sources[] = (string) $meta['source'];
        }
        foreach ($aggregates as $aggregate) {
            $fieldId = (string) ($aggregate['field'] ?? '');
            if ($fieldId === '') continue;
            $meta = $this->registry->get($fieldId);
            $metadata[$fieldId] = $meta;
            $sources[] = (string) $meta['source'];
        }
        foreach ($this->conditionFieldIds((array) ($definition['conditions'] ?? [])) as $fieldId) {
            $meta = $this->registry->get($fieldId);
            $sources[] = (string) $meta['source'];
        }
        foreach ((array) ($definition['sorts'] ?? []) as $sort) {
            if (! is_array($sort) || empty($sort['field'])) continue;
            $meta = $this->registry->get((string) $sort['field']);
            $sources[] = (string) $meta['source'];
        }
        $sources = array_values(array_unique($sources));

        $authority = $this->authority->resolve($sources);
        $this->assertSourcesReady($sources, $authority);

        $base = $this->baseQuery($sources, $authority);
        $this->applyConditions($base, (array) ($definition['conditions'] ?? []));
        $countQuery = clone $base;
        $count = (int) $countQuery->distinct()->count('registrations.id');

        $allowedSizes = (array) config('dynamic-reports.preview_sizes', [5, 10, 20]);
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

        return ['count' => $count, 'rows' => $rows, 'columns' => $columns, 'warnings' => $authority['warnings']];
    }

    /** @return array{count:int,rows:array<int,array<string,mixed>>,columns:array<int,array<string,string>>,warnings:array<int,string>} */
    private function summaryPreview(Builder $base, array $groups, array $aggregates, array $metadata, array $definition, int $count, int $size, array $warnings): array
    {
        $columns = [];
        $aliases = [];
        foreach ($groups as $index => $id) {
            $meta = $metadata[$id] ?? $this->registry->get($id);
            if (! ($meta['groupable'] ?? false)) throw ValidationException::withMessages(['groups' => 'A selected field cannot be grouped.']);
            $alias = 'g'.$index;
            $base->addSelect(DB::raw($meta['expression'].' as `'.$alias.'`'))->groupBy($meta['expression']);
            $aliases[$id] = $alias;
            $columns[] = ['id' => $id, 'label' => (string) $meta['label']];
        }
        foreach ($aggregates as $index => $aggregate) {
            $fieldId = (string) ($aggregate['field'] ?? '');
            $function = strtolower((string) ($aggregate['function'] ?? ''));
            if ($fieldId === '' || $function === '') continue;
            $meta = $metadata[$fieldId] ?? $this->registry->get($fieldId);
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
            $columns[] = ['id' => $id, 'label' => $label];
        }
        foreach ($groups as $id) {
            $base->orderBy((string) ($metadata[$id]['expression'] ?? $this->registry->get($id)['expression']));
        }
        $rows = $base->limit($size)->get()->map(function ($row) use ($aliases): array {
            $out = [];
            foreach ($aliases as $id => $alias) $out[$id] = $row->{$alias} ?? null;
            return $out;
        })->all();
        $rows = $this->formatter->formatRows($rows, $groups);

        return ['count' => $count, 'rows' => $rows, 'columns' => $columns, 'warnings' => $warnings];
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
        if (in_array('allocation', $sources, true)) {
            $query->leftJoin('allocation_a5_candidate_results', function ($join) use ($authority): void {
                $join->on('allocation_a5_candidate_results.registration_id', '=', 'registrations.id')
                    ->where('allocation_a5_candidate_results.allocation_a5_run_id', '=', $authority['allocation_a5_run_id']);
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
        $meta = $this->registry->get((string) $condition['field']);
        $operator = (string) $condition['operator'];
        if (! in_array($operator, (array) ($meta['operators'] ?? []), true)) {
            throw ValidationException::withMessages(['conditions' => 'Unsupported condition operator.']);
        }
        $column = (string) $meta['expression'];
        $value = $condition['value'] ?? null;
        $value2 = $condition['value2'] ?? null;
        $method = $boolean === 'or' ? 'orWhere' : 'where';

        match ($operator) {
            'eq' => $query->{$method}($column, '=', $value),
            'neq' => $query->{$method}($column, '!=', $value),
            'gt' => $query->{$method}($column, '>', $value),
            'gte' => $query->{$method}($column, '>=', $value),
            'lt' => $query->{$method}($column, '<', $value),
            'lte' => $query->{$method}($column, '<=', $value),
            'contains' => $query->{$method}($column, 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], (string) $value).'%'),
            'starts_with' => $query->{$method}($column, 'like', str_replace(['%', '_'], ['\\%', '\\_'], (string) $value).'%'),
            'between' => $query->{$boolean === 'or' ? 'orWhereBetween' : 'whereBetween'}($column, [$value, $value2]),
            'in' => $query->{$boolean === 'or' ? 'orWhereIn' : 'whereIn'}($column, is_array($value) ? $value : array_filter(array_map('trim', explode(',', (string) $value)), fn ($v) => $v !== '')),
            'is_blank' => $query->{$method}(fn ($q) => $q->whereNull($column)->orWhere($column, '')),
            'is_not_blank' => $query->{$method}(fn ($q) => $q->whereNotNull($column)->where($column, '!=', '')),
            default => throw ValidationException::withMessages(['conditions' => 'Unsupported condition operator.']),
        };
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
        ];
        foreach ($required as $source => [$key, $label]) {
            if (in_array($source, $sources, true) && empty($authority[$key])) {
                throw ValidationException::withMessages(['query' => $label.' source is not finalized/current.']);
            }
        }
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
            $meta = $this->registry->get((string) $sort['field']);
            if (! ($meta['sortable'] ?? false)) continue;
            $direction = strtolower((string) ($sort['direction'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';
            $query->orderBy((string) $meta['expression'], $direction);
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
            $meta = $this->registry->get($id); $metadata[$id] = $meta; $sources[] = (string) $meta['source'];
        }
        foreach ($aggregates as $aggregate) {
            $fieldId = (string) ($aggregate['field'] ?? ''); if ($fieldId === '') continue;
            $meta = $this->registry->get($fieldId); $metadata[$fieldId] = $meta; $sources[] = (string) $meta['source'];
        }
        foreach ($this->conditionFieldIds((array) ($definition['conditions'] ?? [])) as $fieldId) {
            $meta = $this->registry->get($fieldId); $sources[] = (string) $meta['source'];
        }
        foreach ((array) ($definition['sorts'] ?? []) as $sort) {
            if (! is_array($sort) || empty($sort['field'])) continue;
            $meta = $this->registry->get((string) $sort['field']); $sources[] = (string) $meta['source'];
        }
        $sources = array_values(array_unique($sources));
        $authority = $this->authority->resolve($sources);
        $this->assertSourcesReady($sources, $authority);

        $base = $this->baseQuery($sources, $authority);
        $this->applyConditions($base, (array) ($definition['conditions'] ?? []));
        $count = (int) (clone $base)->distinct()->count('registrations.id');
        $labels = (array) ($definition['labels'] ?? []);

        if ($mode === 'summary') {
            $columns = []; $aliases = [];
            foreach ($groups as $index => $id) {
                $meta = $metadata[$id] ?? $this->registry->get($id);
                if (! ($meta['groupable'] ?? false)) throw ValidationException::withMessages(['groups' => 'A selected field cannot be grouped.']);
                $alias='g'.$index; $base->addSelect(DB::raw($meta['expression'].' as `'.$alias.'`'))->groupBy($meta['expression']);
                $aliases[$id]=$alias; $columns[]=['id'=>$id,'label'=>trim((string)($labels[$id]??'')) ?: (string)$meta['label']];
            }
            foreach ($aggregates as $index => $aggregate) {
                $fieldId=(string)($aggregate['field']??''); $function=strtolower((string)($aggregate['function']??'')); if($fieldId===''||$function==='') continue;
                $meta=$metadata[$fieldId]??$this->registry->get($fieldId);
                if(!in_array($function,(array)($meta['aggregates']??[]),true)) throw ValidationException::withMessages(['aggregates'=>'Aggregate function is not allowed for the selected field.']);
                $sqlFunction=match($function){'count'=>'COUNT','count_distinct'=>'COUNT','sum'=>'SUM','avg'=>'AVG','min'=>'MIN','max'=>'MAX',default=>throw ValidationException::withMessages(['aggregates'=>'Unsupported aggregate function.'])};
                $alias='a'.$index; $distinct=$function==='count_distinct'?'DISTINCT ':'';
                $base->addSelect(DB::raw($sqlFunction.'('.$distinct.$meta['expression'].') as `'.$alias.'`'));
                $id='aggregate.'.$index; $aliases[$id]=$alias;
                $columns[]=['id'=>$id,'label'=>trim((string)($aggregate['label']??'')) ?: strtoupper(str_replace('_',' ',$function)).' · '.(string)$meta['label']];
            }
            foreach($groups as $id) $base->orderBy((string)($metadata[$id]['expression']??$this->registry->get($id)['expression']));
            $formatter=$this->formatter;
            $rows=(function() use($base,$aliases,$groups,$formatter){ foreach($base->cursor() as $row){$out=[];foreach($aliases as $id=>$alias)$out[$id]=$row->{$alias}??null;$formatted=$formatter->formatRows([$out],$groups);yield $formatted[0]??$out;}})();
            return ['count'=>$count,'columns'=>$columns,'rows'=>$rows,'warnings'=>$authority['warnings'],'authority'=>$authority];
        }

        foreach($fields as $index=>$id) $base->addSelect(DB::raw($metadata[$id]['expression'].' as `f'.$index.'`'));
        $this->applySorts($base,(array)($definition['sorts']??[]));
        $columns=[]; foreach($fields as $id)$columns[]=['id'=>$id,'label'=>trim((string)($labels[$id]??'')) ?: (string)$metadata[$id]['label']];
        $formatter=$this->formatter;
        $rows=(function() use($base,$fields,$formatter){foreach($base->cursor() as $row){$out=[];foreach($fields as $index=>$id)$out[$id]=$row->{'f'.$index}??null;$formatted=$formatter->formatRows([$out],$fields);yield $formatted[0]??$out;}})();
        return ['count'=>$count,'columns'=>$columns,'rows'=>$rows,'warnings'=>$authority['warnings'],'authority'=>$authority];
    }

    /** @return array<string,mixed> */
    public function authoritySnapshot(array $definition): array
    {
        $ids = array_merge((array) ($definition['fields'] ?? []), (array) ($definition['groups'] ?? []), $this->conditionFieldIds((array) ($definition['conditions'] ?? [])));
        foreach ((array) ($definition['aggregates'] ?? []) as $aggregate) if (is_array($aggregate) && ! empty($aggregate['field'])) $ids[] = (string) $aggregate['field'];
        foreach ((array) ($definition['sorts'] ?? []) as $sort) if (is_array($sort) && ! empty($sort['field'])) $ids[] = (string) $sort['field'];
        $sources = ['registrations'];
        foreach (array_values(array_unique(array_filter(array_map('strval', $ids)))) as $id) $sources[] = (string) $this->registry->get($id)['source'];
        return $this->authority->resolve(array_values(array_unique($sources)));
    }

}
