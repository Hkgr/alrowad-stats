<?php

namespace App\Services\Dashboard;

use App\Models\ActivityRecord;
use App\Models\ImportIssue;
use App\Models\ImportRun;
use App\Models\Institution;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSectorAssignment;

/**
 * What the last statistics import could not use as written, and where the data has holes.
 * Read straight from import_issues and the records; nothing here changes any figure.
 */
class DataQualityService
{
    /** Examples listed per issue code (every excluded row is listed: they are the ones to fix). */
    private const EXAMPLES = 5;

    private const EXCLUDED_LIMIT = 40;

    public const SEVERITIES = [
        'excluded' => 'مستبعد (لم يدخل في الأرقام)',
        'conflict' => 'تعارض (استُورد مع حجب حقل)',
        'resolved' => 'حُلّ تلقائيًا',
        'info' => 'معلومة',
    ];

    public const CODES = [
        'conflicting_counts' => 'عدد الجنس المكتوب يخالف الفئات العمرية ويغيّر الإجمالي',
        'typed_total_mismatch' => 'الإجمالي المكتوب لا يساوي الذكور + الإناث',
        'incomplete_counts' => 'عدد جنس واحد فقط والإجمالي لا يطابقه',
        'no_count' => 'صف بلا عدد أشخاص (قيم أخرى فقط)',
        'future_period' => 'شهر لم يحن بعد',
        'period_unresolved' => 'ورقة بفترة غير محسومة',
        'sheet_without_month' => 'ملف بلا شهر أو تاريخ',
        'disabled_exceeds_total' => 'ذوو الاحتياجات أكثر من الإجمالي (حُجب الرقم)',
        'gender_estimated' => 'نسبة تقديرية للجنس (لم تُحفظ كعدد)',
        'gender_split_conflict' => 'توزيع الجنس يخالف الأعمار (حُفظ الإجمالي فقط)',
        'formula_recomputed' => 'نتيجة صيغة قديمة أُعيد حسابها من المكونات',
        'one_gender_reported' => 'جنس واحد مُدخل والآخر «غير مذكور»',
        'label_from_total' => 'تسمية استُرجعت من ورقة Total',
        'garbled_header' => 'عنوان عمود مشوّه',
        'total_sheet_has_summary_row' => 'ورقة Total فيها صف ملخص',
        'skipped_file' => 'ملف متجاوَز عمدًا',
    ];

    /** @return array<string, mixed> */
    public function build(Institution $institution): array
    {
        $run = ImportRun::where('institution_id', $institution->id)->where('kind', 'statistics')
            ->where('dry_run', false)->where('status', 'completed')->orderByDesc('id')->first();

        return [
            'run' => $run ? ['id' => $run->id, 'finished_at' => $run->finished_at?->toIso8601String()] : null,
            'issues' => $run ? $this->issues($run) : ['severities' => [], 'groups' => []],
            'gaps' => $this->gaps($institution),
            'coverage' => $this->coverage($institution),
        ];
    }

    /** @return array{severities: list<array<string, mixed>>, groups: list<array<string, mixed>>} */
    private function issues(ImportRun $run): array
    {
        $issues = ImportIssue::query()->leftJoin('source_files', 'source_files.id', '=', 'import_issues.source_file_id')
            ->where('import_issues.import_run_id', $run->id)
            ->orderBy('import_issues.id')
            ->get(['import_issues.severity', 'import_issues.code', 'import_issues.message', 'import_issues.source_sheet', 'import_issues.source_row', 'source_files.path as file']);

        $order = array_keys(self::SEVERITIES);
        $groups = $issues->groupBy(fn ($i) => $i->severity.'|'.$i->code)->map(function ($g) {
            $first = $g->first();
            $limit = $first->severity === 'excluded' ? self::EXCLUDED_LIMIT : self::EXAMPLES;

            return [
                'severity' => $first->severity,
                'code' => $first->code,
                'label' => self::CODES[$first->code] ?? $first->code,
                'count' => $g->count(),
                'examples' => $g->take($limit)->map(fn ($i) => [
                    'file' => $i->file, 'sheet' => $i->source_sheet, 'row' => $i->source_row, 'message' => $i->message,
                ])->values()->all(),
            ];
        })->sort(fn ($a, $b) => [array_search($a['severity'], $order), -$a['count']] <=> [array_search($b['severity'], $order), -$b['count']])->values();

        return [
            'severities' => collect($order)->map(fn ($s) => [
                'severity' => $s, 'label' => self::SEVERITIES[$s], 'count' => $issues->where('severity', $s)->count(),
            ])->all(),
            'groups' => $groups->all(),
        ];
    }

    /**
     * Months missing inside each project's own span (first to last month with records), measured
     * against the months that have any record in the institution. A project that simply starts
     * later or ends earlier than the others is not "missing" months.
     *
     * @return list<array<string, mixed>>
     */
    private function gaps(Institution $institution): array
    {
        $byProject = ActivityRecord::query()->where('institution_id', $institution->id)->where('is_active', true)
            ->distinct()->get(['project_id', 'period_id'])->groupBy('project_id');

        if ($byProject->isEmpty()) {
            return [];
        }

        $calendar = Period::whereIn('id', $byProject->flatten(1)->pluck('period_id')->unique())->orderBy('year')->orderBy('month')->get()->values();
        $index = $calendar->pluck('id')->flip();
        $projects = Project::whereIn('id', $byProject->keys())->get()->keyBy('id');

        $out = [];
        foreach ($byProject as $projectId => $rows) {
            $present = $rows->pluck('period_id')->map(fn ($id) => $index[$id])->sort()->values();
            $first = $present->first();
            $last = $present->last();
            $missing = $calendar->filter(fn (Period $p, $i) => $i > $first && $i < $last && ! $present->contains($i));

            if ($missing->isEmpty()) {
                continue;
            }

            $out[] = [
                'slug' => $projects[$projectId]->slug,
                'name' => $projects[$projectId]->name,
                'first' => $calendar[$first]->label(),
                'last' => $calendar[$last]->label(),
                'months_with_data' => $present->count(),
                'missing' => $missing->map(fn (Period $p) => $p->label())->values()->all(),
            ];
        }

        usort($out, fn ($a, $b) => [count($b['missing']), $a['name']] <=> [count($a['missing']), $b['name']]);

        return $out;
    }

    /** @return array<string, mixed> */
    private function coverage(Institution $institution): array
    {
        $year = BeneficiaryDashboardService::latestClassificationYear($institution->id);
        $withData = ActivityRecord::where('institution_id', $institution->id)->where('is_active', true)->distinct()->pluck('project_id');
        $classified = $year === null ? collect() : ProjectSectorAssignment::where('institution_id', $institution->id)->where('reference_year', $year)->pluck('project_id');
        $without = Project::whereIn('id', $classified->diff($withData))->orderBy('name')->pluck('name');

        return [
            'classification_year' => $year,
            'classified_projects' => $classified->count(),
            'projects_with_data' => $withData->count(),
            'projects_without_data' => $without->count(),
            'projects_without_data_names' => $without->values()->all(),
        ];
    }
}
