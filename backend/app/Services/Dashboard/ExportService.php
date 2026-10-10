<?php

namespace App\Services\Dashboard;

use App\Models\Period;
use App\Support\XlsxWriter;
use Illuminate\Database\Query\JoinClause;

/**
 * Exports the numbers of the current filters. The detail table is the same active records the
 * dashboard sums (finest grain: month, office, project and activity), so every total in the file
 * equals the figure on screen, and the gender / age / special-needs filters narrow it the same way.
 */
class ExportService
{
    private const HEADER = ['السنة', 'الشهر', 'المسار', 'المشروع', 'المكتب', 'النشاط الرئيسي', 'النشاط الفرعي', 'ذكور', 'إناث', 'الجنس غير مذكور', 'الإجمالي'];

    public function __construct(private readonly BeneficiaryDashboardService $dashboard) {}

    /**
     * Detail rows, oldest month first. male + female + unreported = total on every row.
     *
     * @return list<array{year: int, month: int, sector: string, project: string, office: string, main: string, sub: string, male: int, female: int, unreported: int, total: int}>
     */
    public function rows(DashboardFilters $f): array
    {
        $measure = $this->dashboard->measure();
        $slice = $this->dashboard->sliceFor($f);

        return $this->dashboard->records($f, $measure)
            ->join('projects', fn (JoinClause $j) => $j->on('projects.id', '=', 'activity_records.project_id'))
            ->join('offices', fn (JoinClause $j) => $j->on('offices.id', '=', 'activity_records.office_id'))
            ->leftJoin('sectors', 'sectors.id', '=', 'psa.sector_id')
            ->leftJoin('main_activities as ma', 'ma.id', '=', 'activity_records.main_activity_id')
            ->leftJoin('sub_activities as sa', 'sa.id', '=', 'activity_records.sub_activity_id')
            ->groupBy('periods.year', 'periods.month', 'projects.id', 'projects.name', 'sectors.id', 'sectors.name', 'offices.id', 'offices.name', 'ma.id', 'ma.name', 'sa.id', 'sa.name')
            ->orderBy('periods.year')->orderBy('periods.month')->orderBy('projects.name')->orderBy('offices.name')->orderBy('ma.name')->orderBy('sa.name')
            ->selectRaw('periods.year AS year, periods.month AS month, sectors.name AS sector, projects.name AS project, offices.name AS office, ma.name AS main, sa.name AS sub, '
                ."SUM({$slice['male']}) AS male, SUM({$slice['female']}) AS female, SUM({$slice['total']}) AS total")
            ->toBase()->get()
            ->map(function ($r) {
                $total = (int) $r->total;

                return [
                    'year' => (int) $r->year, 'month' => (int) $r->month,
                    'sector' => $r->sector ?? 'غير مصنف', 'project' => $r->project, 'office' => $r->office,
                    'main' => $r->main ?? '', 'sub' => $r->sub ?? '',
                    'male' => (int) $r->male, 'female' => (int) $r->female,
                    'unreported' => max(0, $total - (int) $r->male - (int) $r->female), 'total' => $total,
                ];
            })->all();
    }

    /** @return list<list<mixed>> header + one row per detail row. */
    public function table(DashboardFilters $f): array
    {
        $table = [self::HEADER];
        foreach ($this->rows($f) as $r) {
            $table[] = [$r['year'], Period::MONTHS_AR[$r['month']], $r['sector'], $r['project'], $r['office'], $r['main'], $r['sub'], $r['male'], $r['female'], $r['unreported'], $r['total']];
        }

        return $table;
    }

    /** Streams the detail table as CSV (UTF-8 with BOM so Excel reads Arabic correctly). */
    public function csv(DashboardFilters $f, $handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");
        foreach ($this->table($f) as $row) {
            // A text cell that starts like a formula is neutralised, so opening the file can never run one.
            fputcsv($handle, array_map(fn ($v) => is_string($v) && preg_match('/^[=+\-@\t\r]/u', $v) ? "'".$v : $v, $row), ',', '"', '');
        }
    }

    /** Writes the workbook: summary, detail, and totals by track / project / office / month. */
    public function workbook(DashboardFilters $f, string $path): void
    {
        $rows = $this->rows($f);
        $data = $this->dashboard->build($f);

        $writer = (new XlsxWriter)
            ->addSheet('الملخص', $this->summary($f, $data, $rows), [38, 48], header: false)
            ->addSheet('التفاصيل', $this->table($f), [8, 14, 30, 36, 14, 30, 30, 10, 10, 16, 12]);

        $group = fn (string $title, callable $key, callable $order, ?callable $label = null) => $this->grouped($title, $rows, $key, $order, $label);
        $writer
            ->addSheet('حسب المسار', ...$group('المسار', fn ($r) => $r['sector'], fn ($a, $b) => $b['total'] <=> $a['total']))
            ->addSheet('حسب المشروع', ...$group('المشروع', fn ($r) => $r['project'], fn ($a, $b) => $b['total'] <=> $a['total']))
            ->addSheet('حسب المكتب', ...$group('المكتب', fn ($r) => $r['office'], fn ($a, $b) => $b['total'] <=> $a['total']))
            ->addSheet('حسب الشهر', ...$group('الشهر', fn ($r) => sprintf('%04d-%02d', $r['year'], $r['month']), fn ($a, $b) => strcmp($a['key'], $b['key']), fn ($key) => $this->monthLabel($key)))
            ->save($path);
    }

    /**
     * One sheet's worth of totals per group (rows × [name, male, female, unreported, total]) and column widths.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<list<mixed>>, 1: list<int>}
     */
    private function grouped(string $title, array $rows, callable $key, callable $order, ?callable $label = null): array
    {
        $groups = [];
        foreach ($rows as $r) {
            $k = (string) $key($r);
            $g = &$groups[$k];
            $g ??= ['key' => $k, 'male' => 0, 'female' => 0, 'unreported' => 0, 'total' => 0];
            foreach (['male', 'female', 'unreported', 'total'] as $field) {
                $g[$field] += $r[$field];
            }
            unset($g);
        }
        usort($groups, $order);

        $table = [[$title, 'ذكور', 'إناث', 'الجنس غير مذكور', 'الإجمالي']];
        foreach ($groups as $g) {
            $table[] = [$label ? $label($g['key']) : $g['key'], $g['male'], $g['female'], $g['unreported'], $g['total']];
        }

        return [$table, [36, 10, 10, 16, 12]];
    }

    private function monthLabel(string $key): string
    {
        [$year, $month] = Period::parseKey($key);

        return Period::MONTHS_AR[$month].' '.$year;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<list<mixed>>
     */
    private function summary(DashboardFilters $f, DashboardData $data, array $rows): array
    {
        $totals = $data->totals;
        $out = [
            ['المؤسسة', $f->institution->name],
            ['تاريخ التصدير', now()->format('Y-m-d H:i')],
            [],
            ['الفلاتر المطبّقة', $this->describe($f) ?: 'بلا فلاتر (كل البيانات)'],
            [],
        ];

        if ($totals === null) {
            $out[] = ['النتيجة', 'لا توجد سجلات تطابق هذه الفلاتر'];
        } else {
            array_push($out,
                ['الاستفادات المسجلة (الإجمالي)', $totals->total],
                ['ذكور', $totals->male],
                ['إناث', $totals->female],
                ['الجنس غير مذكور', $totals->genderUnreported()],
            );
            if (! $f->demographic() && $totals->disabled !== null) {
                $out[] = ['منها ذوو احتياجات خاصة مسجَّلون', $totals->disabled];
            }
            array_push($out,
                ['مشاريع لها بيانات', $totals->projects],
                ['مكاتب لها سجلات', $totals->offices],
                ['صفوف التفصيل', count($rows)],
            );
        }

        foreach ($data->otherMeasures as $m) {
            $out[] = [$m['measure']->name.' ('.$m['measure']->unit_label.')', $m['total']];
        }

        array_push($out,
            [],
            ['ملاحظات', 'الأرقام «استفادات مسجلة» وليست أشخاصًا فريدين: من يستفيد في أكثر من شهر يُسجَّل في كل شهر.'],
            ['', 'الخلية الفارغة في المصدر «غير مذكور» وليست صفرًا؛ ولا تُوزَّع الأجزاء غير المذكورة على الجنسين.'],
        );
        if ($f->demographic()) {
            $out[] = ['', 'فلتر الجنس أو العمر أو ذوي الاحتياجات مفعّل: لا تدخل إلا السجلات التي يذكر مصدرها هذه الفئة، فقد يقل المجموع عن إجمالي المشروع.'];
        }

        return $out;
    }

    /** Active filters as readable Arabic ("المسار: …، الفترة: …"), empty when none. */
    public function describe(DashboardFilters $f): string
    {
        $parts = array_filter([
            $f->unclassified ? 'المسار: غير مصنف' : ($f->sector ? 'المسار: '.$f->sector->name : null),
            $f->project ? 'المشروع: '.$f->project->name : null,
            $f->mainActivity ? 'النشاط الرئيسي: '.$f->mainActivity->name : null,
            $f->subActivity ? 'النشاط الفرعي: '.$f->subActivity->name : null,
            $f->year ? 'السنة: '.$f->year : null,
            $f->period ? 'الفترة: '.$f->period->label() : null,
            $f->office ? 'المكتب: '.$f->office->name : null,
            $f->gender ? 'الجنس: '.($f->gender === 'male' ? 'ذكور فقط' : 'إناث فقط') : null,
            $f->age ? 'العمر: '.($f->age === 'under_18' ? 'أقل من 18 سنة' : '18 سنة فأكثر') : null,
            $f->disabled ? 'ذوو الاحتياجات الخاصة فقط' : null,
        ]);

        return implode('، ', $parts);
    }
}
