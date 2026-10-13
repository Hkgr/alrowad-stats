<?php

namespace Tests\Feature\Api;

use App\Models\Institution;
use App\Models\Measure;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Support\XlsxReader;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Records;
use Tests\TestCase;

class PeriodRangeApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/dashboard?institution=rowad';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // The seeded sample owns 2026-05 (1,450). This project adds 10 / 20 / 30 / 40 in four other months.
        $institution = Institution::where('slug', 'rowad')->first();
        $project = Project::create(['institution_id' => $institution->id, 'slug' => 'range', 'name' => 'مشروع المجال']);
        foreach ([[2025, 10, 10], [2025, 11, 20], [2025, 12, 30], [2026, 1, 40]] as [$year, $month, $count]) {
            Records::make([
                'institution_id' => $institution->id, 'project_id' => $project->id,
                'period_id' => Period::create(['institution_id' => $institution->id, 'year' => $year, 'month' => $month])->id,
                'measure_id' => Measure::where('code', Measure::REGISTERED_BENEFITS)->value('id'),
                'office_id' => Office::where('slug', 'jarabulus')->value('id'), 'male_count' => $count, 'female_count' => 0,
            ]);
        }
    }

    private function total(string $query): ?int
    {
        return $this->getJson(self::BASE.$query)->assertOk()->json('data.summary.total');
    }

    public function test_a_range_counts_both_ends_and_either_end_may_be_open(): void
    {
        $this->assertSame(1450 + 100, $this->total(''));
        $this->assertSame(50, $this->total('&project=range&from=2025-11&to=2025-12'), 'both ends included');
        $this->assertSame(10 + 20, $this->total('&project=range&to=2025-11'), 'open start');
        $this->assertSame(20 + 30 + 40, $this->total('&project=range&from=2025-11'), 'open end');
        $this->assertSame(30 + 40, $this->total('&project=range&from=2025-12&to=2026-01'), 'across the year boundary');
        $this->assertSame(20, $this->total('&project=range&from=2025-11&to=2025-11'), 'a single month');
        $this->assertNull($this->total('&project=range&from=2026-02&to=2026-04'), 'months without records: no data, not zero');
    }

    public function test_the_range_narrows_every_breakdown_and_names_its_ends(): void
    {
        $data = $this->getJson(self::BASE.'&from=2025-11&to=2026-01')->assertOk()
            ->assertJsonPath('data.filters.from', ['key' => '2025-11', 'label' => 'تشرين الثاني 2025'])
            ->assertJsonPath('data.filters.to', ['key' => '2026-01', 'label' => 'كانون الثاني 2026'])
            ->json('data');

        $this->assertSame(90, $data['summary']['total']);
        $this->assertSame(['2025-11', '2025-12', '2026-01'], array_column($data['periods'], 'key'));
        $this->assertSame(90, array_sum(array_column($data['offices'], 'total')));
        $this->assertSame(90, array_sum(array_column($data['projects'], 'total')));
        $this->assertNull($this->getJson(self::BASE)->json('data.filters.from'));
    }

    public function test_it_combines_with_the_other_filters(): void
    {
        $this->assertSame(50, $this->total('&project=range&from=2025-11&to=2025-12&gender=male'));
        $this->assertSame(0, $this->total('&project=range&from=2025-11&to=2025-12&gender=female'), 'females are written as an explicit 0: a zero, not an absence');
    }

    public function test_the_month_choices_ignore_the_range_so_it_can_always_be_changed(): void
    {
        $options = $this->getJson('/api/v1/filters?institution=rowad&from=2025-11&to=2025-11')->assertOk();

        $this->assertSame(['2025-10', '2025-11', '2025-12', '2026-01', '2026-05'], array_column($options->json('data.periods'), 'key'));
        $this->assertSame([2025, 2026], $options->json('data.years'));
    }

    public function test_analytics_and_export_follow_the_range(): void
    {
        $stack = $this->getJson('/api/v1/analytics?institution=rowad&from=2025-11&to=2025-12')->assertOk()->json('data.monthly_stack');
        $this->assertSame(['2025-11', '2025-12'], array_column($stack['periods'], 'key'));

        $csv = $this->get('/api/v1/export?institution=rowad&format=csv&from=2025-11&to=2025-12')->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", substr($csv, 3))));
        $this->assertSame(50, array_sum(array_column(array_slice($rows, 1), 10)));
    }

    public function test_the_export_summary_states_the_range(): void
    {
        $response = $this->get('/api/v1/export?institution=rowad&from=2025-11');
        $book = new XlsxReader($response->baseResponse->getFile()->getPathname());
        $summary = collect($book->rows('الملخص'))->mapWithKeys(fn ($r) => [$r['A'] ?? '' => $r['B'] ?? '']);

        $this->assertSame('الأشهر: من تشرين الثاني 2025 إلى آخر شهر', $summary['الفلاتر المطبّقة']);
    }

    public function test_the_range_is_validated(): void
    {
        $this->getJson(self::BASE.'&from=2026-02&to=2025-11')->assertStatus(422)->assertJsonValidationErrors('to');
        $this->getJson(self::BASE.'&from=2025-11&period=2025-12')->assertStatus(422)->assertJsonValidationErrors('period');
        $this->getJson(self::BASE.'&to=2025-11&period=2025-10')->assertStatus(422)->assertJsonValidationErrors('period');
        $this->getJson(self::BASE.'&year=2025&from=2026-01')->assertStatus(422)->assertJsonValidationErrors('from');
        $this->getJson(self::BASE.'&year=2026&to=2025-12')->assertStatus(422)->assertJsonValidationErrors('to');
        $this->getJson(self::BASE.'&from=2025-13')->assertStatus(422)->assertJsonValidationErrors('from');
        $this->getJson(self::BASE.'&to=nope')->assertStatus(422)->assertJsonValidationErrors('to');
        // Inside a single year it is fine.
        $this->getJson(self::BASE.'&year=2025&from=2025-10&to=2025-11')->assertOk()->assertJsonPath('data.summary.total', 30);
    }
}
