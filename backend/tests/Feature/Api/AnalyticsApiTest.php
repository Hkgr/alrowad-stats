<?php

namespace Tests\Feature\Api;

use App\Models\ImportIssue;
use App\Models\ImportRun;
use App\Models\Institution;
use App\Models\Measure;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Records;
use Tests\TestCase;

class AnalyticsApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/analytics?institution=rowad';

    private Institution $institution;

    private int $measure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->institution = Institution::where('slug', 'rowad')->first();
        $this->measure = Measure::where('code', Measure::REGISTERED_BENEFITS)->value('id');
    }

    private function project(string $slug): Project
    {
        return Project::create(['institution_id' => $this->institution->id, 'slug' => $slug, 'name' => "مشروع {$slug}"]);
    }

    private function period(int $year, int $month): Period
    {
        return Period::firstOrCreate(['institution_id' => $this->institution->id, 'year' => $year, 'month' => $month]);
    }

    private function record(Project $project, string $office, Period $period, array $values): void
    {
        Records::make($values + [
            'institution_id' => $this->institution->id, 'project_id' => $project->id, 'measure_id' => $this->measure,
            'office_id' => Office::where('slug', $office)->value('id'), 'period_id' => $period->id,
        ]);
    }

    public function test_heatmap_lists_projects_by_office_and_leaves_missing_cells_absent(): void
    {
        $project = $this->project('matrix');
        $this->record($project, 'jarabulus', $this->period(2025, 10), ['male_count' => 30, 'female_count' => 10]);
        $this->record($project, 'afrin', $this->period(2025, 10), ['male_count' => 5, 'female_count' => 5]);

        $heatmap = $this->getJson(self::BASE)->assertOk()->json('data.heatmap');

        $row = collect($heatmap['rows'])->firstWhere('slug', 'matrix');
        $this->assertSame(50, $row['total']);
        $this->assertSame(['jarabulus', 'afrin'], array_keys($row['cells']));
        $this->assertEquals(['value' => 40, 'share' => 80.0], $row['cells']['jarabulus']);
        $this->assertArrayNotHasKey('marea', $row['cells']);
        $this->assertContains('jarabulus', array_column($heatmap['offices'], 'slug'));

        // Inside a project the offices table already says it: no matrix.
        $this->getJson(self::BASE.'&project=matrix')->assertOk()->assertJsonPath('data.heatmap', null);
    }

    public function test_monthly_stack_splits_months_by_track_and_adds_up_to_the_total(): void
    {
        $project = $this->project('unlisted');
        $this->record($project, 'jarabulus', $this->period(2025, 10), ['male_count' => 20, 'female_count' => 20]);

        $stack = $this->getJson(self::BASE)->assertOk()->json('data.monthly_stack');

        $this->assertSame('sector', $stack['group']);
        $this->assertSame(['2025-10', '2026-05'], array_column($stack['periods'], 'key'));
        $this->assertSame(1490, array_sum(array_column($stack['series'], 'total')));
        $unclassified = collect($stack['series'])->firstWhere('key', 'unclassified');
        $this->assertSame([40, null], $unclassified['values']);

        // Inside a track the series are its projects.
        $this->getJson(self::BASE.'&sector=cul')->assertOk()->assertJsonPath('data.monthly_stack.group', 'project');
    }

    public function test_pyramid_separates_age_and_gender_and_keeps_the_unreported_part(): void
    {
        $project = $this->project('ages');
        $period = $this->period(2025, 10);
        $this->record($project, 'jarabulus', $period, ['male_under_18' => 10, 'female_under_18' => 5, 'male_adult' => 20, 'female_adult' => 15, 'male_count' => 30, 'female_count' => 20]);
        $this->record($project, 'jarabulus', $period, ['male_count' => 25, 'female_count' => 25]);

        $this->getJson(self::BASE.'&project=ages')->assertOk()
            ->assertJsonPath('data.pyramid.under_18', ['male' => 10, 'female' => 5])
            ->assertJsonPath('data.pyramid.adult', ['male' => 20, 'female' => 15])
            ->assertJsonPath('data.pyramid.total', 100)
            ->assertJsonPath('data.pyramid.unreported', 50)
            ->assertJsonPath('data.pyramid.coverage', 50);

        $this->getJson(self::BASE.'&project=ages&gender=female')->assertOk()
            ->assertJsonPath('data.pyramid.under_18', ['male' => 0, 'female' => 5])
            ->assertJsonPath('data.pyramid.genders', ['female']);
        $this->getJson(self::BASE.'&project=ages&age=adult')->assertOk()->assertJsonPath('data.pyramid', null);
        $this->getJson(self::BASE.'&project=ages&disabled=1')->assertOk()->assertJsonPath('data.pyramid', null);
        // The seeded sample has no age cells at all.
        $this->getJson(self::BASE.'&year=2026')->assertOk()->assertJsonPath('data.pyramid', null);
    }

    public function test_a_course_is_one_project_and_number_even_when_it_runs_in_several_offices_and_months(): void
    {
        $project = $this->project('courses');
        foreach ([[2025, 10, 'jarabulus'], [2025, 11, 'jarabulus'], [2025, 10, 'afrin']] as [$y, $m, $office]) {
            $this->record($project, $office, $this->period($y, $m), ['course_number' => '1', 'sections_count' => 2, 'male_count' => 10, 'female_count' => 10]);
        }
        $this->record($project, 'jarabulus', $this->period(2025, 10), ['course_number' => '2', 'male_count' => 5, 'female_count' => 5]);

        $courses = $this->getJson(self::BASE.'&project=courses')->assertOk()->json('data.courses');

        $this->assertSame(2, $courses['courses']);
        $this->assertSame(70, $courses['total']);
        $this->assertSame(35, $courses['average_per_course']);
        $this->assertEquals(100.0, $courses['coverage']);
        // Sections count only where they are reported: 3 records × 2 sections, 60 benefits.
        $this->assertEquals(['total' => 6, 'records' => 3, 'average_per_record' => 2.0, 'benefits_per_section' => 10.0], $courses['sections']);
        $this->assertSame([['number' => '1', 'offices' => 2, 'months' => 2, 'total' => 60], ['number' => '2', 'offices' => 1, 'months' => 1, 'total' => 10]], $courses['list']);

        $overview = $this->getJson(self::BASE)->assertOk()->json('data.courses');
        $this->assertSame([], $overview['list']);
        $this->assertSame('courses', $overview['by_project'][0]['slug']);
        $this->assertSame(2, $overview['by_project'][0]['courses']);

        $this->getJson(self::BASE.'&project='.$this->project('empty')->slug)->assertOk()->assertJsonPath('data.courses', null);
    }

    public function test_knowledge_groups_by_specialty_and_university_and_counts_missing_values_apart(): void
    {
        $project = $this->project('knowledge');
        $period = $this->period(2025, 10);
        $this->record($project, 'jarabulus', $period, ['male_count' => 6, 'female_count' => 4, 'details' => ['specialty' => 'طب بشري', 'university' => 'جامعة حلب']]);
        $this->record($project, 'afrin', $period, ['male_count' => 3, 'female_count' => 2, 'details' => ['specialty' => 'طب بشري', 'university' => '']]);
        $this->record($project, 'marea', $period, ['male_count' => 1, 'female_count' => 1, 'details' => ['specialty' => 'حقوق', 'university' => 'جامعة حلب']]);

        $knowledge = $this->getJson(self::BASE.'&project=knowledge')->assertOk()->json('data.knowledge');

        $this->assertSame(17, $knowledge['total']);
        $this->assertSame([['name' => 'طب بشري', 'total' => 15], ['name' => 'حقوق', 'total' => 2]], $knowledge['specialties']);
        $this->assertSame([['name' => 'جامعة حلب', 'total' => 12]], $knowledge['universities']);
        $this->assertSame(5, $knowledge['university_unreported']);
        $this->assertSame(0, $knowledge['specialty_unreported']);

        $this->getJson(self::BASE.'&year=2026')->assertOk()->assertJsonPath('data.knowledge', null);
    }

    public function test_demographic_filters_apply_to_the_matrix_and_the_stack(): void
    {
        $project = $this->project('mix');
        $this->record($project, 'jarabulus', $this->period(2025, 10), ['male_count' => 8, 'female_count' => 2]);
        $this->record($project, 'afrin', $this->period(2025, 10), ['total_count' => 30]);

        $response = $this->getJson(self::BASE.'&gender=male&year=2025')->assertOk();

        $row = collect($response->json('data.heatmap.rows'))->firstWhere('slug', 'mix');
        $this->assertSame(8, $row['total']);
        $this->assertSame(['jarabulus'], array_keys($row['cells']));
        $this->assertSame(8, $response->json('data.monthly_stack.series.0.total'));
    }

    public function test_filters_are_validated_like_the_dashboard(): void
    {
        $this->getJson('/api/v1/analytics')->assertStatus(422)->assertJsonValidationErrors('institution');
        $this->getJson(self::BASE.'&disabled=1&gender=male')->assertStatus(422)->assertJsonValidationErrors('disabled');
    }

    public function test_data_quality_reports_issues_gaps_and_projects_without_data(): void
    {
        $run = ImportRun::create(['institution_id' => $this->institution->id, 'kind' => 'statistics', 'dry_run' => false, 'status' => 'completed', 'finished_at' => now()]);
        ImportRun::create(['institution_id' => $this->institution->id, 'kind' => 'statistics', 'dry_run' => true, 'status' => 'completed']);
        foreach ([['excluded', 'no_count', 'لا عدد'], ['excluded', 'no_count', 'لا عدد'], ['conflict', 'gender_estimated', 'نسبة تقديرية'], ['resolved', 'formula_recomputed', 'أُعيد']] as [$severity, $code, $message]) {
            ImportIssue::create(['import_run_id' => $run->id, 'severity' => $severity, 'code' => $code, 'message' => $message, 'source_sheet' => 'أيلول', 'source_row' => 7]);
        }

        // Starts in October, skips November (another project has it) and returns in December.
        $gappy = $this->project('gappy');
        $this->record($gappy, 'jarabulus', $this->period(2025, 10), ['male_count' => 1, 'female_count' => 1]);
        $this->record($gappy, 'jarabulus', $this->period(2025, 12), ['male_count' => 1, 'female_count' => 1]);
        $this->record($this->project('steady'), 'jarabulus', $this->period(2025, 11), ['male_count' => 1, 'female_count' => 1]);

        $data = $this->getJson('/api/v1/data-quality?institution=rowad')->assertOk()->json('data');

        $this->assertSame($run->id, $data['run']['id']);
        $counts = collect($data['issues']['severities'])->pluck('count', 'severity');
        $this->assertSame(2, $counts['excluded']);
        $this->assertSame(1, $counts['conflict']);
        $this->assertSame(1, $counts['resolved']);
        $this->assertSame(0, $counts['info']);
        $first = $data['issues']['groups'][0];
        $this->assertSame(['excluded', 'no_count', 2], [$first['severity'], $first['code'], $first['count']]);
        $this->assertCount(2, $first['examples']);

        $gap = collect($data['gaps'])->firstWhere('slug', 'gappy');
        $this->assertSame(['تشرين الثاني 2025'], $gap['missing']);
        $this->assertSame(2, $gap['months_with_data']);
        // A project that merely starts later than the others is not "missing" months.
        $this->assertNull(collect($data['gaps'])->firstWhere('slug', 'steady'));

        $this->assertSame(3, $data['coverage']['projects_with_data']);
    }

    public function test_data_quality_without_an_import_is_empty_not_an_error(): void
    {
        $this->getJson('/api/v1/data-quality?institution=rowad')->assertOk()
            ->assertJsonPath('data.run', null)
            ->assertJsonPath('data.issues.groups', []);
    }
}
