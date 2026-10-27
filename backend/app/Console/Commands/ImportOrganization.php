<?php

namespace App\Console\Commands;

use App\Services\Organization\OrganizationImporter;
use Illuminate\Console\Command;

class ImportOrganization extends Command
{
    protected $signature = 'rowad:import-organization {--file= : Workbook path. Default: the xlsx file directly inside data/raw}';

    protected $description = 'Import the organization workbook (programs, projects, regions, centers, 2017–2024). Re-runnable.';

    public function handle(OrganizationImporter $importer): int
    {
        $path = $this->option('file') ?: $this->defaultFile();
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $summary = $importer->import($path);
        $this->table(
            ['Programs', 'Projects', 'Regions', 'Centers', 'Rows', 'Beneficiaries'],
            [[$summary['programs'], $summary['projects'], $summary['regions'], $summary['centers'], $summary['facts'], number_format($summary['beneficiaries'])]],
        );
        $this->info('Organization figures saved. The center sheet is the beneficiary record.');

        return self::SUCCESS;
    }

    private function defaultFile(): string
    {
        $root = base_path(config('organization_import.root'));
        $files = glob($root.DIRECTORY_SEPARATOR.'*.xlsx') ?: [];
        if (count($files) !== 1) {
            $this->error('Put the organization workbook directly in data/raw, or pass --file.');

            return '';
        }

        return $files[0];
    }
}
