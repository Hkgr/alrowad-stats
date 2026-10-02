<?php

namespace App\Console\Commands;

use App\Models\Institution;
use App\Services\Classification\ClassificationReportWriter;
use App\Services\Classification\ProjectClassificationImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportProjectClassification extends Command
{
    protected $signature = 'rowad:import-classification
        {file : Path to the project list workbook (.xlsx)}
        {--institution=rowad : Institution slug}
        {--year=2026 : Reference year the list describes}
        {--dry-run : Show the result without saving}';

    protected $description = 'Import project → track (sector) links from the project list. Re-runnable; reads names and tracks only.';

    public function handle(ProjectClassificationImporter $importer, ClassificationReportWriter $writer): int
    {
        $institution = Institution::where('slug', $this->option('institution'))->first();
        if ($institution === null) {
            $this->error('Unknown institution: '.$this->option('institution'));

            return self::FAILURE;
        }

        $year = (int) $this->option('year');
        $report = $importer->import($this->argument('file'), $institution, $year, (bool) $this->option('dry-run'));

        $this->table(['#', 'Code', 'Track', 'Projects', 'Status'], array_map(
            fn ($s) => [$s['order'], $s['code'], $s['name'], $s['projects'], $s['status']],
            $report['sectors'],
        ));

        $p = $report['projects'];
        $a = $report['assignments'];
        $this->line(sprintf(
            'Projects: %d created, %d matched, %d via alias. Links: %d created, %d unchanged, %d changed.',
            count($p['created']), count($p['matched']), count($p['aliased']), $a['created'], $a['unchanged'], count($a['changed']),
        ));
        $this->line(sprintf('Unresolved conflicts: %d. Similar names kept separate: %d.', count($report['conflicts']), count($report['kept_separate'])));

        // A dry run never overwrites the report of the last real import.
        $suffix = $report['dry_run'] ? '.dry-run' : '';
        $path = storage_path("app/reports/project-classification-{$year}{$suffix}.md");
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $writer->markdown($report));
        $this->info(($report['dry_run'] ? 'Dry run — nothing saved. ' : '')."Report: {$path}");

        return self::SUCCESS;
    }
}
