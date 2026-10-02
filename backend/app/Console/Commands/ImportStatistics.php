<?php

namespace App\Console\Commands;

use App\Models\ActivityRecord;
use App\Models\Institution;
use App\Models\MainActivity;
use App\Models\Measure;
use App\Models\Project;
use App\Models\ProjectSectorAssignment;
use App\Models\SubActivity;
use App\Services\Import\StatisticsImporter;
use App\Services\Import\StatisticsReportWriter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportStatistics extends Command
{
    protected $signature = 'rowad:import-statistics
        {--institution=rowad : Institution slug}
        {--root= : Folder of the workbooks (default: config statistics_import.root)}
        {--only= : Import only files whose relative path contains this text}
        {--dry-run : Run everything in a transaction and roll it back}';

    protected $description = 'Import the statistics workbooks (data/raw) into activity records. Re-runnable, with a report.';

    public function handle(StatisticsImporter $importer, StatisticsReportWriter $writer): int
    {
        $institution = Institution::where('slug', $this->option('institution'))->first();
        if (! $institution) {
            $this->error('Unknown institution: '.$this->option('institution'));

            return self::FAILURE;
        }

        $root = $this->option('root') ?: base_path(config('statistics_import.root'));
        if (! is_dir($root)) {
            $this->error("Folder not found: {$root}");

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $report = $importer->run($institution, $root, $dryRun, $this->option('only'));

        $this->table(['File', 'Status', 'Rows', 'Missing', 'Excluded', 'New', 'Updated', 'Same', 'Total'], array_map(
            fn ($f, $file) => [$file, $f['status'], $f['rows_read'] ?? '', $f['missing'] ?? '', $f['excluded'] ?? '', $f['created'] ?? '', $f['updated'] ?? '', $f['unchanged'] ?? '', $f['total'] ?? ''],
            $report['files'], array_keys($report['files']),
        ));

        $counts = array_count_values(array_column($report['issues'], 'severity'));
        $this->line('Issues: '.json_encode($counts));
        $this->line("Sample rows superseded: {$report['superseded']}");

        $extra = $dryRun ? [] : $this->coverage($institution);
        $path = storage_path('app/reports/statistics-import'.($dryRun ? '.dry-run' : '').'.md');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $writer->markdown($report, $extra));
        $this->info(($dryRun ? 'Dry run — nothing saved. ' : '')."Report: {$path}");

        return self::SUCCESS;
    }

    /** Projects, periods and activities as they stand after the import. @return array<string, list<string>> */
    private function coverage(Institution $institution): array
    {
        $active = ActivityRecord::where('activity_records.institution_id', $institution->id)->where('activity_records.is_active', true);
        $withData = (clone $active)->distinct()->pluck('activity_records.project_id')->all();
        $persons = Measure::where('code', Measure::REGISTERED_BENEFITS)->value('id');
        $periods = (clone $active)->where('activity_records.measure_id', $persons)->join('periods', 'periods.id', '=', 'activity_records.period_id')
            ->selectRaw('periods.year, periods.month, SUM(activity_records.total_count) AS total, COUNT(*) AS records')
            ->groupBy('periods.year', 'periods.month')->orderBy('periods.year')->orderBy('periods.month')->toBase()->get();

        $classified = ProjectSectorAssignment::where('institution_id', $institution->id)->where('reference_year', 2026)->pluck('project_id')->all();
        $without = Project::where('institution_id', $institution->id)->whereIn('id', $classified)->whereNotIn('id', $withData)->orderBy('name')->pluck('name');

        $lines = [
            'مشاريع لديها سجلات: '.count($withData).' من '.Project::where('institution_id', $institution->id)->count(),
            'سجلات فعالة: '.number_format((clone $active)->count()),
            'أنشطة رئيسية: '.MainActivity::where('institution_id', $institution->id)->count().' · أنشطة فرعية: '.SubActivity::where('institution_id', $institution->id)->count(),
            '',
            '| الفترة | سجلات (استفادات مسجلة) | الاستفادات المسجلة |', '|---|---|---|',
        ];
        foreach ($periods as $p) {
            $lines[] = sprintf('| %04d-%02d | %s | %s |', $p->year, $p->month, number_format($p->records), number_format($p->total));
        }

        return [
            'التغطية بعد الاستيراد' => $lines,
            'مشاريع مصنفة بلا ملف أرقام أو بلا قيم' => $without->map(fn ($n) => "- {$n}")->all() ?: ['لا يوجد.'],
        ];
    }
}
