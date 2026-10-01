<?php

namespace App\Services\Dashboard;

use App\Models\BeneficiaryRecord;
use App\Models\DataSource;
use App\Models\Measure;
use App\Models\Period;
use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Single source of truth for the dashboard numbers.
 *
 * Every query is pinned to one institution and one measure; filters narrow it further.
 * Aggregation follows the measure definition (sum). Figures of different measures are never
 * combined — add a new measure code and a new call instead of widening this query.
 */
class BeneficiaryDashboardService
{
    public function build(DashboardFilters $filters): DashboardData
    {
        $measure = $this->measure();

        // One grouped query without the office filter; the office filter is applied in memory
        // so the "comparison" and "offices" sets come from the very same rows.
        $all = $this->officeRows($filters, $measure);

        $selectedSlug = $filters->office?->slug;
        $comparison = array_map(
            fn (OfficeFigures $row) => $row->withSelected($row->slug === $selectedSlug),
            $all,
        );
        $offices = $selectedSlug === null
            ? $all
            : array_values(array_filter($all, fn (OfficeFigures $row) => $row->slug === $selectedSlug));

        return new DashboardData(
            filters: $filters,
            measure: $measure,
            offices: $offices,
            comparison: $comparison,
            projects: $this->contributing(Project::class, 'project_id', $filters, $measure),
            periods: $this->contributing(Period::class, 'period_id', $filters, $measure, orderBy: ['year', 'month']),
            sources: $this->contributing(DataSource::class, 'data_source_id', $filters, $measure),
        );
    }

    public function measure(): Measure
    {
        return Measure::where('code', Measure::REGISTERED_BENEFITS)->firstOrFail();
    }

    /** @return list<OfficeFigures> ranked by total, highest first. */
    private function officeRows(DashboardFilters $filters, Measure $measure): array
    {
        return $this->scopedRecords($filters, $measure, withOffice: false)
            ->join('offices', function ($join) {
                $join->on('offices.id', '=', 'beneficiary_records.office_id')
                    ->on('offices.institution_id', '=', 'beneficiary_records.institution_id');
            })
            ->groupBy('offices.id', 'offices.slug', 'offices.name', 'offices.sort_order')
            ->selectRaw('offices.slug, offices.name, offices.sort_order, SUM(beneficiary_records.male_count) AS male, SUM(beneficiary_records.female_count) AS female')
            ->get()
            ->map(fn ($row) => new OfficeFigures($row->slug, $row->name, (int) $row->male, (int) $row->female))
            ->sort(fn (OfficeFigures $a, OfficeFigures $b) => [$b->total(), $a->name] <=> [$a->total(), $b->name])
            ->values()
            ->all();
    }

    /**
     * Records of the institution + measure, narrowed by project/period (and office when asked).
     *
     * @return Builder<BeneficiaryRecord>
     */
    private function scopedRecords(DashboardFilters $filters, Measure $measure, bool $withOffice): Builder
    {
        return BeneficiaryRecord::query()
            ->where('beneficiary_records.institution_id', $filters->institution->id)
            ->where('beneficiary_records.measure_id', $measure->id)
            ->when($filters->project, fn (Builder $q, Project $p) => $q->where('beneficiary_records.project_id', $p->id))
            ->when($filters->period, fn (Builder $q, Period $p) => $q->where('beneficiary_records.period_id', $p->id))
            ->when($withOffice && $filters->office, fn (Builder $q) => $q->where('beneficiary_records.office_id', $filters->office->id));
    }

    /**
     * Catalog rows (projects, periods, sources) that actually back the figures in scope.
     *
     * @param  class-string<Model>  $model
     * @param  list<string>  $orderBy
     * @return Collection<int, Model>
     */
    private function contributing(string $model, string $foreignKey, DashboardFilters $filters, Measure $measure, array $orderBy = ['id']): Collection
    {
        $ids = $this->scopedRecords($filters, $measure, withOffice: true)
            ->whereNotNull("beneficiary_records.{$foreignKey}")
            ->distinct()
            ->pluck("beneficiary_records.{$foreignKey}");

        $query = $model::query()->where('institution_id', $filters->institution->id)->whereIn('id', $ids);
        foreach ($orderBy as $column) {
            $query->orderBy($column);
        }

        return $query->get();
    }
}
