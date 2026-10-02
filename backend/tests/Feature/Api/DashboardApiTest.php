<?php

namespace Tests\Feature\Api;

use App\Models\BeneficiaryRecord;
use App\Models\Institution;
use App\Models\Measure;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSectorAssignment;
use App\Models\Sector;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/dashboard?institution=rowad';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_health_endpoint_reports_database_connection(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJson(['status' => 'ok', 'database' => 'connected']);
    }

    public function test_reference_totals_are_computed_from_records(): void
    {
        $this->getJson(self::BASE)
            ->assertOk()
            ->assertJsonPath('data.has_data', true)
            ->assertJsonPath('data.summary.male', 718)
            ->assertJsonPath('data.summary.female', 732)
            ->assertJsonPath('data.summary.total', 1450)
            ->assertJsonPath('data.summary.offices_count', 7)
            ->assertJsonPath('data.summary.male_share', 49.5)
            ->assertJsonPath('data.summary.female_share', 50.5)
            ->assertJsonCount(7, 'data.offices')
            ->assertJsonPath('data.measure.unit_label', 'استفادة مسجلة');
    }

    public function test_offices_are_ranked_by_total(): void
    {
        $totals = collect($this->getJson(self::BASE)->json('data.offices'))->pluck('total')->all();

        $this->assertSame([250, 250, 250, 250, 150, 150, 150], $totals);
    }

    public function test_office_filter_narrows_every_figure(): void
    {
        $response = $this->getJson(self::BASE.'&office=jarabulus')->assertOk();

        $response
            ->assertJsonPath('data.summary.male', 120)
            ->assertJsonPath('data.summary.female', 130)
            ->assertJsonPath('data.summary.total', 250)
            ->assertJsonPath('data.summary.offices_count', 1)
            ->assertJsonCount(1, 'data.offices')
            ->assertJsonPath('data.offices.0.slug', 'jarabulus')
            ->assertJsonPath('data.filters.office.name', 'جرابلس');

        // The comparison set keeps all offices so charts can highlight the selection.
        $comparison = collect($response->json('data.comparison'));
        $this->assertCount(7, $comparison);
        $this->assertSame(['jarabulus'], $comparison->where('selected', true)->pluck('slug')->all());
    }

    public function test_project_and_period_filters_use_existing_values(): void
    {
        $this->getJson(self::BASE.'&project=loba-wa-farha&period=2026-05')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 1450)
            ->assertJsonPath('data.level', 'project')
            ->assertJsonPath('data.active_sector.slug', 'cul')
            ->assertJsonPath('data.periods.0.label', 'أيار 2026')
            ->assertJsonPath('data.periods.0.total', 1450)
            ->assertJsonPath('data.sources.0.coverage', 'sample');
    }

    public function test_absence_of_data_is_reported_as_null_not_zero(): void
    {
        $institution = Institution::where('slug', 'rowad')->first();
        Period::create(['institution_id' => $institution->id, 'year' => 2026, 'month' => 6]);

        $this->getJson(self::BASE.'&period=2026-06')
            ->assertOk()
            ->assertJsonPath('data.has_data', false)
            ->assertJsonPath('data.summary.total', null)
            ->assertJsonPath('data.summary.male', null)
            ->assertJsonPath('data.summary.offices_count', null)
            ->assertJsonCount(0, 'data.offices');
    }

    public function test_filter_options_list_only_periods_and_offices_that_have_data(): void
    {
        $institution = Institution::where('slug', 'rowad')->first();
        Period::create(['institution_id' => $institution->id, 'year' => 2026, 'month' => 6]);
        Office::create(['institution_id' => $institution->id, 'slug' => 'empty-office', 'name' => 'مكتب بلا بيانات']);

        $response = $this->getJson('/api/v1/filters?institution=rowad&project=loba-wa-farha')->assertOk();

        $this->assertSame(['2026-05'], collect($response->json('data.periods'))->pluck('key')->all());
        $this->assertCount(7, $response->json('data.offices'));
        $this->assertNotContains('empty-office', collect($response->json('data.offices'))->pluck('slug')->all());
        $this->assertCount(1, $response->json('data.projects'));
        $this->assertSame('cul', $response->json('data.projects.0.sector.slug'));
        $this->assertTrue($response->json('data.projects.0.has_data'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(7, BeneficiaryRecord::count());
        $this->assertSame(7, Office::count());
        $this->assertSame(1, Project::count());
        $this->assertSame(1, Sector::count());
        $this->assertSame(1, ProjectSectorAssignment::count());
        $this->assertSame(1, Period::count());
        $this->assertSame(1, Institution::count());
        $this->assertSame(1, Measure::count());
        $this->getJson(self::BASE)->assertJsonPath('data.summary.total', 1450);
    }

    public function test_project_track_comes_from_the_documented_project_list(): void
    {
        $project = Project::where('slug', 'loba-wa-farha')->first();

        $this->assertSame('مسار الثقافة والرياضة والتسلية والفنون', $project->sectorIn(2026)->name);
        // The link is a 2026 reference; it does not apply to other years.
        $this->assertNull($project->sectorIn(2025));
        // The workbook's own "level 1" label is kept separately and is not treated as the sector.
        $this->assertSame('نشاط ترفيهي', $project->source_category);
    }

    public function test_project_sector_is_optional(): void
    {
        $institution = Institution::where('slug', 'rowad')->first();
        $project = Project::create(['institution_id' => $institution->id, 'slug' => 'unclassified', 'name' => 'غير مصنف']);

        $this->assertNull($project->sectorIn(2026));
    }

    public function test_institution_is_required_and_must_exist(): void
    {
        $this->getJson('/api/v1/dashboard')->assertStatus(422)->assertJsonValidationErrors('institution');
        $this->getJson('/api/v1/dashboard?institution=unknown')->assertStatus(422)->assertJsonValidationErrors('institution');
        $this->getJson('/api/v1/filters')->assertStatus(422)->assertJsonValidationErrors('institution');
    }

    public function test_period_format_is_validated(): void
    {
        $this->getJson(self::BASE.'&period=2026-13')->assertStatus(422)->assertJsonValidationErrors('period');
        $this->getJson(self::BASE.'&period=2025-05')->assertStatus(422)->assertJsonValidationErrors('period');
    }

    public function test_filters_from_another_institution_are_rejected_and_data_never_leaks(): void
    {
        $this->createOtherInstitutionWithData();

        // Rowad's dashboard is unaffected by the other institution's records.
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('data.summary.total', 1450);

        // Rowad cannot be combined with the other institution's project / office / period.
        $this->getJson(self::BASE.'&project=other-project')->assertStatus(422)->assertJsonValidationErrors('project');
        $this->getJson(self::BASE.'&office=other-office')->assertStatus(422)->assertJsonValidationErrors('office');
        $this->getJson(self::BASE.'&period=2026-07')->assertStatus(422)->assertJsonValidationErrors('period');

        // ...and the reverse: the other institution cannot use Rowad's office.
        $this->getJson('/api/v1/dashboard?institution=other&office=jarabulus')->assertStatus(422)->assertJsonValidationErrors('office');

        // The other institution sees only its own numbers.
        $this->getJson('/api/v1/dashboard?institution=other')
            ->assertOk()
            ->assertJsonPath('data.summary.total', 30)
            ->assertJsonCount(1, 'data.offices');
    }

    public function test_database_rejects_records_that_cross_institutions(): void
    {
        $other = $this->createOtherInstitutionWithData();
        $rowad = Institution::where('slug', 'rowad')->first();

        $this->expectException(QueryException::class);

        BeneficiaryRecord::create([
            'institution_id' => $rowad->id,
            'project_id' => $other['project']->id,
            'office_id' => Office::where('slug', 'jarabulus')->value('id'),
            'period_id' => Period::where('institution_id', $rowad->id)->value('id'),
            'measure_id' => Measure::value('id'),
            'male_count' => 1,
            'female_count' => 1,
        ]);
    }

    public function test_sector_filter_and_sector_cards(): void
    {
        $this->getJson(self::BASE.'&sector=cul')
            ->assertOk()
            ->assertJsonPath('data.level', 'sector')
            ->assertJsonPath('data.summary.total', 1450)
            ->assertJsonPath('data.summary.classified_projects', 1)
            ->assertJsonPath('data.summary.projects_with_data', 1)
            ->assertJsonPath('data.sectors.0.slug', 'cul')
            ->assertJsonPath('data.sectors.0.selected', true)
            ->assertJsonPath('data.sectors.0.total', 1450);
    }

    public function test_sector_without_data_is_absent_not_zero_and_keeps_its_projects(): void
    {
        [$sector, $project] = $this->classifiedProjectWithoutData();

        $this->getJson(self::BASE.'&sector='.$sector->slug)
            ->assertOk()
            ->assertJsonPath('data.has_data', false)
            ->assertJsonPath('data.summary.total', null)
            ->assertJsonPath('data.summary.classified_projects', 1)
            ->assertJsonPath('data.summary.projects_with_data', 0)
            ->assertJsonPath('data.projects.0.slug', $project->slug)
            ->assertJsonPath('data.projects.0.has_data', false)
            ->assertJsonPath('data.projects.0.total', null);

        // The project itself can be opened and reports absence.
        $this->getJson(self::BASE.'&project='.$project->slug)
            ->assertOk()
            ->assertJsonPath('data.level', 'project')
            ->assertJsonPath('data.has_data', false)
            ->assertJsonPath('data.active_sector.slug', $sector->slug);

        // It is searchable through the filter options.
        $slugs = collect($this->getJson('/api/v1/filters?institution=rowad')->json('data.projects'))->pluck('slug');
        $this->assertContains($project->slug, $slugs->all());
    }

    public function test_project_must_belong_to_the_selected_sector(): void
    {
        [$sector] = $this->classifiedProjectWithoutData();

        $this->getJson(self::BASE.'&sector='.$sector->slug.'&project=loba-wa-farha')
            ->assertStatus(422)
            ->assertJsonValidationErrors('project');
        $this->getJson(self::BASE.'&sector=unknown')->assertStatus(422)->assertJsonValidationErrors('sector');
    }

    public function test_offices_are_counted_distinct_across_projects(): void
    {
        $institution = Institution::where('slug', 'rowad')->first();
        $cul = Sector::where('slug', 'cul')->first();
        $second = Project::create(['institution_id' => $institution->id, 'slug' => 'second', 'name' => 'مشروع ثان']);
        ProjectSectorAssignment::create(['institution_id' => $institution->id, 'project_id' => $second->id, 'sector_id' => $cul->id, 'reference_year' => 2026]);
        foreach (['jarabulus', 'afrin'] as $slug) {
            BeneficiaryRecord::create([
                'institution_id' => $institution->id, 'project_id' => $second->id,
                'office_id' => Office::where('slug', $slug)->value('id'), 'period_id' => Period::value('id'),
                'measure_id' => Measure::value('id'), 'male_count' => 1, 'female_count' => 1,
            ]);
        }

        $this->getJson(self::BASE.'&sector=cul')
            ->assertJsonPath('data.summary.offices_count', 7)
            ->assertJsonPath('data.summary.projects_with_data', 2)
            ->assertJsonPath('data.summary.total', 1454)
            ->assertJsonPath('data.sectors.0.offices_count', 7);
    }

    /** @return array{0: Sector, 1: Project} */
    private function classifiedProjectWithoutData(): array
    {
        $institution = Institution::where('slug', 'rowad')->first();
        $sector = Sector::create(['institution_id' => $institution->id, 'slug' => 'hlt', 'code' => 'HLT', 'name' => 'مسار الصحة', 'sort_order' => 4]);
        $project = Project::create(['institution_id' => $institution->id, 'slug' => 'clinic', 'name' => 'مشفى']);
        ProjectSectorAssignment::create(['institution_id' => $institution->id, 'project_id' => $project->id, 'sector_id' => $sector->id, 'reference_year' => 2026]);

        return [$sector, $project];
    }

    /** @return array{institution: Institution, project: Project} */
    private function createOtherInstitutionWithData(): array
    {
        $institution = Institution::create(['slug' => 'other', 'name' => 'مؤسسة أخرى']);
        $project = Project::create(['institution_id' => $institution->id, 'slug' => 'other-project', 'name' => 'مشروع آخر']);
        $office = Office::create(['institution_id' => $institution->id, 'slug' => 'other-office', 'name' => 'مكتب آخر']);
        $period = Period::create(['institution_id' => $institution->id, 'year' => 2026, 'month' => 7]);

        BeneficiaryRecord::create([
            'institution_id' => $institution->id,
            'project_id' => $project->id,
            'office_id' => $office->id,
            'period_id' => $period->id,
            'measure_id' => Measure::value('id'),
            'male_count' => 10,
            'female_count' => 20,
        ]);

        return ['institution' => $institution, 'project' => $project];
    }
}
