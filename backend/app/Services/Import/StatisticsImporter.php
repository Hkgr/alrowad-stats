<?php

namespace App\Services\Import;

use App\Models\ActivityRecord;
use App\Models\ActivityRecordRevision;
use App\Models\DataSource;
use App\Models\ImportIssue;
use App\Models\ImportRun;
use App\Models\Institution;
use App\Models\MainActivity;
use App\Models\Measure;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\ProjectSectorAssignment;
use App\Models\SourceFile;
use App\Models\SubActivity;
use App\Support\ArabicName;
use Database\Seeders\MeasureSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Imports the statistics workbooks listed in config/statistics_import.php.
 *
 * Re-runnable: every record has a natural key (project, month, office, measure, detail path +
 * occurrence). Re-importing unchanged files changes nothing; a corrected file updates the matching
 * records and writes a revision; rows that disappeared from a file are deactivated (never deleted).
 * Phase 1 sample rows of a project are superseded once its real source is imported.
 * Conflicts are isolated (row or field) and reported; the rest of the data is imported.
 */
class StatisticsImporter
{
    private ImportRun $run;

    private Institution $institution;

    /** @var array<string, mixed> */
    private array $report;

    /** @var array<int, true> record ids written in this run */
    private array $touched = [];

    /** @var array<string, Office> */
    private array $offices = [];

    /** @var list<array> sheets excluded for an unresolved year, checked against other files at the end */
    private array $unresolvedSheets = [];

    public function __construct(
        private readonly StatisticsSheetParser $parser,
        private readonly RowValueResolver $resolver,
    ) {}

    /** @return array<string, mixed> report */
    public function run(Institution $institution, string $root, bool $dryRun = false, ?string $only = null): array
    {
        $this->institution = $institution;
        $this->touched = [];
        $this->unresolvedSheets = [];
        $root = rtrim(str_replace('\\', '/', realpath($root) ?: $root), '/');

        DB::beginTransaction();
        try {
            MeasureSeeder::ensure();
            $this->run = ImportRun::create([
                'institution_id' => $institution->id, 'kind' => 'statistics', 'dry_run' => $dryRun,
                'status' => 'running', 'started_at' => now(),
            ]);
            $this->report = [
                'run_id' => $this->run->id, 'dry_run' => $dryRun, 'root' => $root, 'started_at' => now()->toDateTimeString(),
                'files' => [], 'issues' => [], 'superseded' => 0, 'before' => $this->projectTotals(),
            ];
            $this->offices = Office::where('institution_id', $institution->id)->get()
                ->keyBy(fn (Office $o) => ArabicName::normalize($o->name))->all();

            $config = config('statistics_import.files', []);
            $onDisk = $this->listFiles($root);
            $seenHashes = [];

            foreach ($onDisk as $relative) {
                if ($only !== null && ! str_contains($relative, $only)) {
                    continue;
                }
                $path = "{$root}/{$relative}";
                $hash = hash_file('sha256', $path);
                $entry = &$this->report['files'][$relative];
                $entry = ['sha256' => $hash, 'status' => 'pending'];

                if (! isset($config[$relative])) {
                    $entry['status'] = 'unmapped';
                    $this->issue(null, null, null, 'excluded', 'unmapped_file', "الملف «{$relative}» غير مدرج في config/statistics_import.php؛ لم يُستورد.", ['file' => $relative]);

                    continue;
                }
                if (isset($seenHashes[$hash])) {
                    $entry['status'] = 'duplicate';
                    $this->issue(null, null, null, 'excluded', 'duplicate_file', "محتوى «{$relative}» مطابق تمامًا للملف «{$seenHashes[$hash]}»؛ لم يُستورد مرة ثانية.", ['file' => $relative]);

                    continue;
                }
                $seenHashes[$hash] = $relative;

                try {
                    DB::transaction(function () use ($relative, $path, $hash, $config, &$entry) {
                        $this->importFile($relative, $path, $hash, $config[$relative], $entry);
                    });
                } catch (Throwable $e) {
                    $entry['status'] = 'failed';
                    $this->issue(null, null, null, 'excluded', 'file_failed', "تعذّرت قراءة «{$relative}»: {$e->getMessage()}", ['file' => $relative]);
                }
                unset($entry);
            }

            foreach (array_keys($config) as $relative) {
                if (! in_array($relative, $onDisk, true) && ($only === null || str_contains($relative, $only))) {
                    $this->report['files'][$relative] = ['status' => 'missing'];
                    $this->issue(null, null, null, 'info', 'missing_file', "الملف «{$relative}» مذكور في الإعداد وغير موجود في data/raw.", ['file' => $relative]);
                }
            }

            $this->checkUnresolvedSheets();
            $this->report['after'] = $this->projectTotals();
            $this->report['finished_at'] = now()->toDateTimeString();
            $this->run->update(['status' => 'completed', 'finished_at' => now(), 'summary' => $this->summary()]);

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $this->report;
    }

    /** @return list<string> workbook paths relative to the root, Office lock files ignored */
    private function listFiles(string $root): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            $name = $file->getFilename();
            if (str_starts_with($name, '~$') || strtolower($file->getExtension()) !== 'xlsx') {
                continue;
            }
            $files[] = ltrim(str_replace([$root, '\\'], ['', '/'], str_replace('\\', '/', $file->getPathname())), '/');
        }
        sort($files);

        return $files;
    }

    private function importFile(string $relative, string $path, string $hash, array $config, array &$entry): void
    {
        $source = SourceFile::updateOrCreate(
            ['institution_id' => $this->institution->id, 'path' => $relative],
            ['file_name' => basename($relative), 'sha256' => $hash, 'size' => filesize($path)],
        );

        if (isset($config['skip'])) {
            $parsed = $this->parser->parse($path, (int) ($config['year'] ?? 0));
            $withValues = count(array_filter($parsed['rows'], fn ($r) => $this->resolver->resolve($r['values'], 'registered_benefits')['status'] !== 'missing'));
            $entry['status'] = 'skipped';
            $entry['reason'] = $config['skip'];
            if (($config['expect_empty'] ?? false) && $withValues > 0) {
                $this->issue($source, null, null, 'excluded', 'skipped_file_has_values', "«{$relative}» مُستثنى في الإعداد لكنه يحتوي {$withValues} صفًا بقيم؛ راجع الإعداد.", []);
            } else {
                $this->issue($source, null, null, 'info', 'skipped_file', "«{$relative}» لم يُستورد: {$config['skip']}".($config['expect_empty'] ?? false ? ' (تحقق: لا يوجد أي صف بقيم مدخلة).' : ''), []);
            }

            return;
        }

        $project = $this->project($config['project']);
        $entry += ['project' => $config['project'], 'year' => $config['year'], 'measure' => $config['measure'] ?? Measure::REGISTERED_BENEFITS];
        if (! $project) {
            $entry['status'] = 'excluded';
            $this->issue($source, null, null, 'excluded', 'project_not_found', "المشروع «{$config['project']}» غير موجود في دليل المشاريع؛ لم يُستورد «{$relative}».", []);

            return;
        }

        $measure = Measure::where('code', $entry['measure'])->firstOrFail();
        $dataSource = DataSource::updateOrCreate(
            ['institution_id' => $this->institution->id, 'slug' => 'stats-'.substr(sha1($relative), 0, 16)],
            ['label' => $relative, 'file_name' => basename($relative), 'coverage' => DataSource::COVERAGE_FULL,
                'notes' => 'ملف إحصاءات مستورد كاملًا عبر rowad:import-statistics.'],
        );

        $parsed = $this->parser->parse($path, (int) $config['year']);
        foreach ($parsed['issues'] as $i) {
            $this->issue($source, $i['sheet'], $i['row'], $i['severity'], $i['code'], $i['message'], $i['payload']);
        }

        $stats = ['rows_read' => count($parsed['rows']), 'missing' => 0, 'excluded' => 0, 'created' => 0, 'updated' => 0,
            'unchanged' => 0, 'reactivated' => 0, 'deactivated' => 0, 'total' => 0, 'periods' => [], 'sheets' => count($parsed['sheets'])];
        $occurrences = [];
        $noMonth = ['rows' => 0, 'total' => 0];
        $future = [];

        foreach ($parsed['rows'] as $row) {
            $resolved = $this->resolver->resolve($row['values'], $measure->code);
            if ($resolved['status'] === 'missing') {
                $stats['missing']++;

                continue;
            }
            foreach ($resolved['issues'] as $i) {
                $this->issue($source, $row['sheet'], $row['row'], $i['severity'], $i['code'], $i['message'], []);
            }
            if ($resolved['status'] === 'excluded') {
                $stats['excluded']++;

                continue;
            }
            $values = $resolved['values'];

            // Period: month from the sheet or the «الشهر» column, year from the folder/config.
            if ($row['month'] === null) {
                $noMonth['rows']++;
                $noMonth['total'] += $values['total_count'];
                $stats['excluded']++;

                continue;
            }
            $year = $row['year'];
            if ($row['year_unresolved']) {
                if (($row['total_year'] ?? null) === $year + 1) {
                    $year++;
                } else {
                    $this->unresolvedSheets[$relative.'|'.$row['sheet']] ??= ['file' => $relative, 'source' => $source, 'sheet' => $row['sheet'], 'project' => $project,
                        'month' => $row['month'], 'year' => $year, 'total_year' => $row['total_year'] ?? null, 'rows' => 0, 'total' => 0];
                    $this->unresolvedSheets[$relative.'|'.$row['sheet']]['rows']++;
                    $this->unresolvedSheets[$relative.'|'.$row['sheet']]['total'] += $values['total_count'];
                    $stats['excluded']++;

                    continue;
                }
            }

            // A month that has not happened yet cannot hold real figures (pre-filled template zeros).
            if ($year * 100 + $row['month'] > (int) now()->format('Ym')) {
                $future[$row['sheet']] = ($future[$row['sheet']] ?? ['rows' => 0, 'total' => 0, 'month' => $row['month'], 'year' => $year]);
                $future[$row['sheet']]['rows']++;
                $future[$row['sheet']]['total'] += $values['total_count'];
                $stats['excluded']++;

                continue;
            }

            $text = $row['text'];
            if ($text['office'] === null) {
                $this->issue($source, $row['sheet'], $row['row'], 'excluded', 'office_missing', 'لا يوجد مكتب للصف (خلية فارغة خارج أي دمج)؛ عُزل الصف.', []);
                $stats['excluded']++;

                continue;
            }

            $details = array_filter([
                'specialty' => $text['specialty'],
                'university' => $text['university'],
            ]);
            if ($text['l3'] !== null && $text['l2'] === null) {
                $details['level_3'] = $text['l3'];
                $this->issue($source, $row['sheet'], $row['row'], 'conflict', 'sub_without_main', "نشاط فرعي «{$text['l3']}» بلا نشاط رئيسي في المصدر؛ حُفظ ضمن تفاصيل السجل دون اختراع نشاط رئيسي.", []);
            }

            $period = Period::firstOrCreate(['institution_id' => $this->institution->id, 'year' => $year, 'month' => $row['month']]);
            $office = $this->office($text['office']);
            $category = $text['l1'] !== null ? $this->category($project, $text['l1']) : null;
            $main = $text['l2'] !== null ? $this->mainActivity($project, $category, $text['l2']) : null;
            $sub = ($main && $text['l3'] !== null) ? $this->subActivity($main, $text['l3']) : null;
            $course = $this->course($text['course']);

            ksort($details);
            $path = [
                $category?->name_key ?? '', $main?->name_key ?? '', $sub?->name_key ?? '', $course ?? '',
                json_encode(array_map(fn ($v) => ArabicName::normalize($v), $details), JSON_UNESCAPED_UNICODE),
            ];
            $occurrenceKey = implode('|', [$project->id, $period->id, $office->id, $measure->id, ...$path]);
            $occurrences[$occurrenceKey] = ($occurrences[$occurrenceKey] ?? 0) + 1;
            $detailKey = sha1(json_encode([...$path, $occurrences[$occurrenceKey]], JSON_UNESCAPED_UNICODE));

            $attributes = $values + [
                'category_id' => $category?->id, 'main_activity_id' => $main?->id, 'sub_activity_id' => $sub?->id,
                'course_number' => $course, 'details' => $details ?: null,
                'source_sheet' => $row['sheet'], 'source_row' => $row['row'],
            ];

            $outcome = $this->upsert($project, $period, $office, $measure, $detailKey, $attributes, $source, $dataSource);
            if ($outcome === 'duplicate') {
                $this->issue($source, $row['sheet'], $row['row'], 'excluded', 'duplicate_across_files', 'السجل نفسه (المشروع والشهر والمكتب والنشاط) استُورد من ملف آخر في هذا التشغيل؛ لم يُكرر.', []);
                $stats['excluded']++;

                continue;
            }
            $stats[$outcome]++;
            $stats['total'] += $values['total_count'];
            $stats['periods'][$period->key()] = true;
        }

        foreach ($future as $sheet => $f) {
            $this->issue($source, $sheet, null, 'excluded', 'future_period', "{$f['rows']} صفًا بقيم مدخلة (إجماليها {$f['total']}) لشهر ".Period::MONTHS_AR[$f['month']]." {$f['year']} الذي لم يحن بعد عند الاستيراد؛ لم تُستورد.", $f);
        }

        if ($noMonth['rows'] > 0) {
            $this->issue($source, null, null, 'excluded', 'period_missing', "{$noMonth['rows']} صفًا بقيم (إجماليها {$noMonth['total']}) بلا شهر أو تاريخ في الملف؛ لم تُستورد لأن الفترة غير محسومة.", $noMonth);
        }

        // Rows that left this file since the previous import.
        $stale = ActivityRecord::where('source_file_id', $source->id)->where('is_active', true)
            ->whereNotIn('id', array_keys($this->touched) ?: [0])->get();
        foreach ($stale as $record) {
            $record->update(['is_active' => false, 'import_run_id' => $this->run->id]);
            $this->revision($record, 'deactivated', $record->only(ActivityRecord::VALUE_FIELDS), null);
            $stats['deactivated']++;
        }

        // Phase 1 sample rows of this project are replaced by the real source.
        if ($stats['created'] + $stats['updated'] + $stats['unchanged'] + $stats['reactivated'] > 0) {
            $samples = ActivityRecord::where('project_id', $project->id)->where('is_active', true)
                ->whereIn('data_source_id', DataSource::where('institution_id', $this->institution->id)->where('coverage', DataSource::COVERAGE_SAMPLE)->pluck('id'))
                ->get();
            foreach ($samples as $record) {
                $record->update(['is_active' => false, 'import_run_id' => $this->run->id]);
                $this->revision($record, 'superseded', $record->only(ActivityRecord::VALUE_FIELDS), ['replaced_by' => $relative]);
                $this->report['superseded']++;
            }
        }

        $stats['periods'] = array_keys($stats['periods']);
        sort($stats['periods']);
        $kept = $stats['created'] + $stats['updated'] + $stats['unchanged'] + $stats['reactivated'];
        // A listed file that yields no record (e.g. no month anywhere) is reported as excluded.
        $entry = array_merge($entry, $stats, ['status' => $kept > 0 ? 'imported' : 'excluded']);
    }

    /** @return 'created'|'updated'|'unchanged'|'reactivated'|'duplicate' */
    private function upsert(Project $project, Period $period, Office $office, Measure $measure, string $detailKey, array $attributes, SourceFile $source, DataSource $dataSource): string
    {
        $key = ['project_id' => $project->id, 'period_id' => $period->id, 'office_id' => $office->id, 'measure_id' => $measure->id, 'detail_key' => $detailKey];
        $record = ActivityRecord::where($key)->first();

        if ($record && isset($this->touched[$record->id]) && (int) $record->source_file_id !== (int) $source->id) {
            return 'duplicate';
        }

        $common = ['institution_id' => $this->institution->id, 'source_file_id' => $source->id, 'data_source_id' => $dataSource->id, 'import_run_id' => $this->run->id];

        if (! $record) {
            $record = ActivityRecord::create($key + $attributes + $common + ['is_active' => true]);
            $this->touched[$record->id] = true;
            $this->revision($record, 'created', null, $record->only(ActivityRecord::VALUE_FIELDS));

            return 'created';
        }

        $before = $record->only(ActivityRecord::VALUE_FIELDS);
        $record->fill($attributes);
        $changed = $record->isDirty(ActivityRecord::VALUE_FIELDS);
        $wasInactive = ! $record->is_active;
        $record->fill($common + ['is_active' => true])->save();
        $this->touched[$record->id] = true;

        if ($wasInactive) {
            $this->revision($record, 'reactivated', $before, $record->only(ActivityRecord::VALUE_FIELDS));

            return 'reactivated';
        }
        if ($changed) {
            $this->revision($record, 'updated', $before, $record->only(ActivityRecord::VALUE_FIELDS));

            return 'updated';
        }

        return 'unchanged';
    }

    private function revision(ActivityRecord $record, string $change, ?array $before, ?array $after): void
    {
        ActivityRecordRevision::create([
            'activity_record_id' => $record->id, 'import_run_id' => $this->run->id,
            'change' => $change, 'before' => $before, 'after' => $after,
        ]);
    }

    /** Matches a project by normalized name or by the name it was classified under; never fuzzy. */
    private function project(string $name): ?Project
    {
        $key = ArabicName::normalize($name);
        $projects = Project::where('institution_id', $this->institution->id)->get();
        $matches = $projects->filter(fn (Project $p) => ArabicName::normalize($p->name) === $key);
        if ($matches->isEmpty()) {
            $ids = ProjectSectorAssignment::where('institution_id', $this->institution->id)->get()
                ->filter(fn ($a) => $a->source_name && ArabicName::normalize($a->source_name) === $key)->pluck('project_id')->unique();
            $matches = $projects->whereIn('id', $ids);
        }

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function office(string $name): Office
    {
        $key = ArabicName::normalize($name);
        if (isset($this->offices[$key])) {
            return $this->offices[$key];
        }

        $slugs = config('statistics_import.office_slugs', []);
        $base = $slugs[$name] ?? (collect($slugs)->first(fn ($s, $n) => ArabicName::normalize($n) === $key) ?? (Str::slug($name) ?: 'office'));
        $slug = $base;
        for ($i = 2; Office::where('institution_id', $this->institution->id)->where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $this->offices[$key] = Office::create([
            'institution_id' => $this->institution->id, 'slug' => $slug, 'name' => $name,
            'sort_order' => (int) Office::where('institution_id', $this->institution->id)->max('sort_order') + 1,
        ]);
    }

    private function category(Project $project, string $name): ProjectCategory
    {
        return ProjectCategory::firstOrCreate(
            ['project_id' => $project->id, 'name_key' => ArabicName::normalize($name)],
            ['institution_id' => $this->institution->id, 'name' => $name],
        );
    }

    private function mainActivity(Project $project, ?ProjectCategory $category, string $name): MainActivity
    {
        $identity = ['project_id' => $project->id, 'category_key' => $category?->id ?? 0, 'name_key' => ArabicName::normalize($name)];
        $existing = MainActivity::where($identity)->first();
        if ($existing) {
            return $existing;
        }

        return MainActivity::create($identity + [
            'institution_id' => $this->institution->id, 'category_id' => $category?->id, 'name' => $name,
            'slug' => $this->uniqueSlug($name, fn ($s) => MainActivity::where('project_id', $project->id)->where('slug', $s)->exists()),
        ]);
    }

    private function subActivity(MainActivity $main, string $name): SubActivity
    {
        $identity = ['main_activity_id' => $main->id, 'name_key' => ArabicName::normalize($name)];

        return SubActivity::where($identity)->first() ?? SubActivity::create($identity + [
            'institution_id' => $this->institution->id, 'name' => $name,
            'slug' => $this->uniqueSlug($name, fn ($s) => SubActivity::where('main_activity_id', $main->id)->where('slug', $s)->exists()),
        ]);
    }

    private function uniqueSlug(string $name, callable $taken): string
    {
        $base = Str::limit(Str::slug($name), 90, '') ?: 'activity';
        $slug = $base;
        for ($i = 2; $taken($slug); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }

    /** Course numbers are identifiers, kept as text and never summed. 0 means "no course". */
    private function course(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_numeric($value)) {
            $number = (float) $value;

            return $number == 0 ? null : (string) (floor($number) == $number ? (int) $number : $number);
        }

        return $value;
    }

    /** Sheets whose year could not be settled: report them and compare with what another file holds. */
    private function checkUnresolvedSheets(): void
    {
        foreach ($this->unresolvedSheets as $sheet) {
            $candidate = Period::where('institution_id', $this->institution->id)->where('year', $sheet['year'] + 1)->where('month', $sheet['month'])->first();
            $other = $candidate ? (int) ActivityRecord::where('project_id', $sheet['project']->id)->where('period_id', $candidate->id)
                ->where('is_active', true)->where('source_file_id', '!=', $sheet['source']->id)->sum('total_count') : 0;
            $label = Period::MONTHS_AR[$sheet['month']];
            $totalYear = $sheet['total_year'] ?? 'غير مذكورة';
            $message = "ورقة «{$sheet['sheet']}» ({$label}) تأتي بعد كانون الأول في ملف {$sheet['year']}، بينما تذكر ورقة Total السنة {$totalYear}؛ "
                ."الفترة غير محسومة فلم تُستورد ({$sheet['rows']} صفًا، إجماليها {$sheet['total']}).";
            if ($other > 0) {
                $message .= " للمقارنة: {$label} ".($sheet['year'] + 1)." مستورد من ملف آخر بإجمالي {$other}".($other === $sheet['total'] ? ' (مطابق).' : '.');
            }
            $this->issue($sheet['source'], $sheet['sheet'], null, 'excluded', 'period_unresolved', $message, ['rows' => $sheet['rows'], 'total' => $sheet['total'], 'other_file_total' => $other]);
        }
    }

    /** @return array<string, array{persons: int, households: int, records: int}> */
    private function projectTotals(): array
    {
        $rows = ActivityRecord::query()
            ->join('projects', 'projects.id', '=', 'activity_records.project_id')
            ->join('measures', 'measures.id', '=', 'activity_records.measure_id')
            ->where('activity_records.institution_id', $this->institution->id)
            ->where('activity_records.is_active', true)
            ->groupBy('projects.name', 'measures.code')
            ->selectRaw('projects.name AS project, measures.code AS measure, SUM(activity_records.total_count) AS total, COUNT(*) AS records')
            ->toBase()->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->project] ??= ['persons' => 0, 'households' => 0, 'records' => 0];
            $out[$row->project][$row->measure === 'households_served' ? 'households' : 'persons'] += (int) $row->total;
            $out[$row->project]['records'] += (int) $row->records;
        }
        ksort($out);

        return $out;
    }

    private function summary(): array
    {
        $count = fn (string $status) => count(array_filter($this->report['files'], fn ($f) => ($f['status'] ?? null) === $status));
        $sum = fn (string $key) => array_sum(array_map(fn ($f) => $f[$key] ?? 0, $this->report['files']));

        return [
            'files_imported' => $count('imported'), 'files_skipped' => $count('skipped'), 'files_unmapped' => $count('unmapped'),
            'records_created' => $sum('created'), 'records_updated' => $sum('updated'), 'records_unchanged' => $sum('unchanged'),
            'records_reactivated' => $sum('reactivated'), 'records_deactivated' => $sum('deactivated'),
            'rows_excluded' => $sum('excluded'), 'rows_missing' => $sum('missing'), 'superseded' => $this->report['superseded'],
        ];
    }

    private function issue(?SourceFile $source, ?string $sheet, ?int $row, string $severity, string $code, string $message, array $payload): void
    {
        $file = $source?->path ?? ($payload['file'] ?? null);
        $this->report['issues'][] = compact('file', 'sheet', 'row', 'severity', 'code', 'message');
        ImportIssue::create([
            'import_run_id' => $this->run->id, 'source_file_id' => $source?->id, 'source_sheet' => $sheet,
            'source_row' => $row, 'severity' => $severity, 'code' => $code, 'message' => $message, 'payload' => $payload ?: null,
        ]);
    }

    public function report(): array
    {
        return $this->report;
    }
}
