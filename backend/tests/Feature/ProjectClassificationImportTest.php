<?php

namespace Tests\Feature;

use App\Models\BeneficiaryRecord;
use App\Models\Institution;
use App\Models\Measure;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSectorAssignment;
use App\Models\Sector;
use App\Services\Classification\ProjectClassificationImporter;
use App\Support\ArabicName;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\XlsxFixture;
use Tests\TestCase;

class ProjectClassificationImportTest extends TestCase
{
    use RefreshDatabase;

    private string $file;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        // Same shape as the institution's list: number | name | status per track, codes on the next row.
        $this->file = XlsxFixture::create([
            'قائمة المشاريع' => [
                1 => ['A' => 'عنوان'],
                6 => ['A' => '2', 'B' => 'مسار التعليم والتمكين', 'C' => 'Project Status',
                    'D' => '2', 'E' => 'مسار الثقافة والرياضة والتسلية والفنون', 'F' => 'Project Status',
                    'G' => '3', 'H' => 'مسار التنمية والإسكان', 'I' => 'Project Status',
                    'J' => '4', 'K' => 'مسار الصحة', 'L' => 'Project Status'],
                7 => ['B' => 'EDU', 'E' => 'CUL', 'H' => 'DEV', 'K' => 'HLT'],
                8 => ['A' => '1', 'B' => 'مشروع رواد العلم', 'C' => 'Active',
                    'D' => '1', 'E' => 'مشروع لعبة و فرحة', 'F' => 'On Hold',
                    'G' => '1', 'H' => 'مشروع تأهيل مشفى الرازي', 'I' => 'Closed',
                    'J' => '1', 'K' => 'مشروع  مشفى الرازي ', 'L' => '1234'],
                9 => ['A' => '2', 'B' => 'مشروع رواد العلم', 'C' => 'Active',
                    'J' => '2', 'K' => 'مشروع خدمات المشفى', 'L' => 'Active'],
            ],
            'مشاريع حسب الزمن' => [
                5 => ['B' => 'مسار التعليم و التمكين', 'D' => 'مشروع رواد العلم', 'E' => '999'],
                6 => ['B' => 'مسار الصحة', 'D' => 'مشروع لعبة وفرحة'],
                7 => ['B' => 'مسار الصحة', 'D' => 'مشروع غير موجود'],
            ],
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
        parent::tearDown();
    }

    private function import(bool $dryRun = false): array
    {
        return app(ProjectClassificationImporter::class)
            ->import($this->file, Institution::where('slug', 'rowad')->first(), 2026, $dryRun);
    }

    public function test_it_links_projects_to_tracks_and_matches_the_existing_project(): void
    {
        $report = $this->import();

        $this->assertSame(4, Sector::count());
        $this->assertSame(['edu', 'cul', 'dev', 'hlt'], Sector::orderBy('sort_order')->pluck('slug')->all());

        // "لعبة و فرحة" matches the seeded project instead of creating a second one.
        $this->assertSame(1, Project::where('name', 'like', '%فرحة%')->count());
        $this->assertSame('مسار الثقافة والرياضة والتسلية والفنون', Project::where('slug', 'loba-wa-farha')->first()->sectorIn(2026)->name);
        $this->assertCount(1, $report['projects']['aliased']);
        $this->assertCount(4, $report['projects']['created']);
    }

    public function test_reimport_creates_no_duplicates(): void
    {
        $this->import();
        $projects = Project::count();
        $links = ProjectSectorAssignment::count();

        $second = $this->import();

        $this->assertSame($projects, Project::count());
        $this->assertSame($links, ProjectSectorAssignment::count());
        $this->assertSame([], $second['projects']['created']);
        $this->assertSame(0, $second['assignments']['created']);
    }

    public function test_similar_names_are_never_merged(): void
    {
        $this->import();

        $names = Project::pluck('name')->all();
        $this->assertContains('تأهيل مشفى الرازي', $names);
        $this->assertContains('مشفى الرازي', $names);
        $this->assertContains('خدمات المشفى', $names);
        $this->assertSame('dev', Project::where('name', 'تأهيل مشفى الرازي')->first()->sectorIn(2026)->slug);
        $this->assertSame('hlt', Project::where('name', 'مشفى الرازي')->first()->sectorIn(2026)->slug);
    }

    public function test_conflicts_are_reported_not_guessed(): void
    {
        $report = $this->import();
        $reasons = array_column($report['conflicts'], 'reason');

        // Duplicate row in the list, and a track that disagrees with a secondary sheet.
        $this->assertTrue(collect($reasons)->contains(fn ($r) => str_contains($r, 'مكرر')));
        $this->assertTrue(collect($reasons)->contains(fn ($r) => str_contains($r, 'تعارض في المسار')));
        // The primary sheet wins; the secondary sheet does not re-classify the project.
        $this->assertSame('cul', Project::where('slug', 'loba-wa-farha')->first()->sectorIn(2026)->slug);
        $this->assertSame(['غير موجود'], array_map(fn ($n) => ArabicName::display($n), $report['cross_check']['مشاريع حسب الزمن']['unknown']));
        $this->assertNotEmpty($report['notes']);
    }

    public function test_only_names_and_tracks_are_imported(): void
    {
        $this->import();

        // Statuses and numbers in the workbook never become records.
        $this->assertSame(7, BeneficiaryRecord::count());
        $this->assertSame(718, (int) BeneficiaryRecord::sum('male_count'));
        $this->assertSame(732, (int) BeneficiaryRecord::sum('female_count'));
        $this->assertSame(1, Period::count());
    }

    public function test_dry_run_saves_nothing(): void
    {
        $before = [Project::count(), Sector::count(), ProjectSectorAssignment::count()];

        $report = $this->import(dryRun: true);

        $this->assertTrue($report['dry_run']);
        $this->assertSame($before, [Project::count(), Sector::count(), ProjectSectorAssignment::count()]);
    }

    public function test_a_2026_classification_does_not_label_2025_records(): void
    {
        $this->import();
        $institution = Institution::where('slug', 'rowad')->first();
        $project = Project::where('slug', 'loba-wa-farha')->first();
        $period2025 = Period::create(['institution_id' => $institution->id, 'year' => 2025, 'month' => 5]);
        BeneficiaryRecord::create([
            'institution_id' => $institution->id, 'project_id' => $project->id,
            'office_id' => Office::where('slug', 'afrin')->value('id'), 'period_id' => $period2025->id,
            'measure_id' => Measure::value('id'), 'male_count' => 5, 'female_count' => 5,
        ]);

        // The 2026 sector only covers 2026 records...
        $this->getJson('/api/v1/dashboard?institution=rowad&sector=cul')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1450);

        // ...and the 2025 record is reported as unclassified.
        $this->getJson('/api/v1/dashboard?institution=rowad')
            ->assertJsonPath('data.summary.total', 1460)
            ->assertJsonPath('data.unclassified.total', 10);
    }

    public function test_command_runs_and_writes_a_report(): void
    {
        $this->artisan('rowad:import-classification', ['file' => $this->file, '--dry-run' => true])
            ->assertSuccessful();

        $this->assertFileExists(storage_path('app/reports/project-classification-2026.dry-run.md'));
    }
}
