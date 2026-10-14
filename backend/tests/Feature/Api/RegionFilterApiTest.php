<?php

namespace Tests\Feature\Api;

use App\Models\Institution;
use App\Models\Measure;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Models\Region;
use App\Services\Regions\RegionCatalog;
use App\Support\XlsxReader;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Records;
use Tests\TestCase;

class RegionFilterApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/dashboard?institution=rowad';

    private Institution $institution;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->institution = Institution::where('slug', 'rowad')->first();
    }

    /** The seed has 7 offices and 250/250/… each; these totals are read from the API, not assumed. */
    private function officeTotals(): array
    {
        return collect($this->getJson(self::BASE)->json('data.offices'))->pluck('total', 'slug')->all();
    }

    /** An office with one record of $count benefits (male only), in a fresh month so it is its own row. */
    private function office(string $slug, string $name, int $count): Office
    {
        $office = Office::create(['institution_id' => $this->institution->id, 'slug' => $slug, 'name' => $name, 'sort_order' => 50]);
        $project = Project::firstOrCreate(['institution_id' => $this->institution->id, 'slug' => 'regions'], ['name' => 'مشروع المناطق']);
        Records::make([
            'institution_id' => $this->institution->id, 'project_id' => $project->id, 'office_id' => $office->id,
            'period_id' => Period::firstOrCreate(['institution_id' => $this->institution->id, 'year' => 2025, 'month' => 10])->id,
            'measure_id' => Measure::where('code', Measure::REGISTERED_BENEFITS)->value('id'), 'male_count' => $count, 'female_count' => 0,
        ]);

        return $office;
    }

    public function test_several_offices_can_be_chosen_at_once(): void
    {
        $totals = $this->officeTotals();

        $response = $this->getJson(self::BASE.'&office=jarabulus,afrin')->assertOk()
            ->assertJsonPath('data.summary.total', $totals['jarabulus'] + $totals['afrin'])
            ->assertJsonPath('data.summary.offices_count', 2)
            ->assertJsonCount(2, 'data.filters.offices')
            ->assertJsonCount(2, 'data.offices');

        $this->assertSame(['jarabulus', 'afrin'], collect($response->json('data.filters.offices'))->pluck('slug')->all());
        // The comparison keeps every office and flags the chosen ones.
        $this->assertSame(['afrin', 'jarabulus'], collect($response->json('data.comparison'))->where('selected', true)->pluck('slug')->sort()->values()->all());
        $this->assertCount(7, $response->json('data.comparison'));

        // One office (the old form of the link) still works, and no office means all of them.
        $this->getJson(self::BASE.'&office=afrin')->assertOk()->assertJsonPath('data.summary.total', $totals['afrin']);
        $this->getJson(self::BASE)->assertOk()->assertJsonPath('data.filters.offices', []);
    }

    public function test_the_offices_filter_narrows_the_other_views_too(): void
    {
        $totals = $this->officeTotals();
        $two = $totals['marea'] + $totals['atarib'];

        $periods = $this->getJson(self::BASE.'&office=marea,atarib')->json('data.periods');
        $this->assertSame($two, array_sum(array_column($periods, 'total')));

        $csv = $this->get('/api/v1/export?institution=rowad&format=csv&office=marea,atarib')->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", substr($csv, 3))));
        $this->assertSame($two, array_sum(array_column(array_slice($rows, 1), 11)));
        $this->assertEqualsCanonicalizing(['مارع', 'الأتارب'], array_values(array_unique(array_column(array_slice($rows, 1), 5))));

        // The project × office matrix ignores the office filter, so the whole picture stays visible.
        $matrix = $this->getJson('/api/v1/analytics?institution=rowad&office=marea')->json('data.heatmap');
        $this->assertGreaterThan(1, count($matrix['offices']));
    }

    public function test_office_choices_are_validated(): void
    {
        $this->getJson(self::BASE.'&office=afrin,nowhere')->assertStatus(422)->assertJsonValidationErrors('office');
        $this->getJson(self::BASE.'&office='.implode(',', array_map(fn ($i) => "o{$i}", range(1, 41))))->assertStatus(422)->assertJsonValidationErrors('office');
        $this->getJson(self::BASE.'&office=afrin,,afrin')->assertOk()->assertJsonCount(1, 'data.filters.offices');
    }

    public function test_filter_options_group_offices_by_region_with_their_counts(): void
    {
        $this->office('idlib', 'إدلب', 5);
        $this->office('damascus', 'دمشق', 3);
        $this->office('brand-new', 'مكتب جديد', 7);

        $regions = collect($this->getJson('/api/v1/filters?institution=rowad')->assertOk()->json('data.regions'))->keyBy('slug');

        // Aleppo: the six seeded offices that belong to it (Salqin is Idlib's).
        $this->assertSame(6, $regions['aleppo']['offices_count']);
        $this->assertEqualsCanonicalizing(['jarabulus', 'al-bab', 'sosian', 'marea', 'afrin', 'atarib'], array_column($regions['aleppo']['offices'], 'slug'));
        $this->assertTrue($regions['aleppo']['has_offices']);

        $this->assertSame(2, $regions['idlib']['offices_count']);
        $this->assertEqualsCanonicalizing(['idlib', 'salqin'], array_column($regions['idlib']['offices'], 'slug'));

        // A region without offices: one stand-in entry to pick, and a count of zero.
        $this->assertFalse($regions['damascus']['has_offices']);
        $this->assertSame(0, $regions['damascus']['offices_count']);
        $this->assertSame(['damascus'], array_column($regions['damascus']['offices'], 'slug'));

        // An office the catalog does not mention is not lost.
        $this->assertSame(['brand-new'], array_column($regions['none']['offices'], 'slug'));
        $this->assertSame('بدون منطقة', $regions['none']['name']);

        // Regions none of whose offices exist are not offered.
        $this->assertArrayNotHasKey('raqqa', $regions->all());
        // The offices of every region keep the order: regions first as configured.
        $this->assertSame(['aleppo', 'idlib', 'damascus', 'none'], $regions->keys()->all());
    }

    public function test_options_say_which_offices_have_records_in_the_scope(): void
    {
        // A 2025 month that exists but where none of the seeded offices has a record.
        Period::create(['institution_id' => $this->institution->id, 'year' => 2025, 'month' => 10]);

        $regions = collect($this->getJson('/api/v1/filters?institution=rowad&year=2025')->json('data.regions'))->keyBy('slug');

        // The seeded sample is all 2026: no office has data in 2025, but they stay listed.
        $this->assertFalse($regions['aleppo']['has_data']);
        $this->assertCount(6, $regions['aleppo']['offices']);

        $regions = collect($this->getJson('/api/v1/filters?institution=rowad&year=2026')->json('data.regions'))->keyBy('slug');
        $this->assertTrue($regions['aleppo']['has_data']);
    }

    public function test_the_export_has_a_region_column_and_a_region_sheet(): void
    {
        $this->office('damascus', 'دمشق', 3);
        $this->office('brand-new', 'مكتب جديد', 7);
        $totals = $this->officeTotals();
        $aleppo = array_sum(array_intersect_key($totals, array_flip(['jarabulus', 'al-bab', 'sosian', 'marea', 'afrin', 'atarib'])));

        $response = $this->get('/api/v1/export?institution=rowad');
        $book = new XlsxReader($response->baseResponse->getFile()->getPathname());

        $byRegion = collect($book->rows('حسب المنطقة'))->skip(1)->mapWithKeys(fn ($r) => [$r['A'] => (int) $r['E']]);
        $this->assertSame($aleppo + $totals['salqin'], $byRegion['حلب'] + $byRegion['إدلب']);
        $this->assertSame($aleppo, $byRegion['حلب']);
        $this->assertSame(3, $byRegion['دمشق']);
        $this->assertSame(7, $byRegion['بدون منطقة']);
        $this->assertSame(array_sum($totals), $byRegion->sum(), 'every record lands in exactly one region row');

        $detail = collect($book->rows('التفاصيل'));
        $this->assertSame('المنطقة', $detail[1]['E']);
        $this->assertContains('حلب', $detail->skip(1)->pluck('E')->all());
    }

    public function test_the_export_summary_names_the_chosen_offices(): void
    {
        $response = $this->get('/api/v1/export?institution=rowad&office=afrin,marea');
        $book = new XlsxReader($response->baseResponse->getFile()->getPathname());
        $summary = collect($book->rows('الملخص'))->mapWithKeys(fn ($r) => [$r['A'] ?? '' => $r['B'] ?? '']);

        $this->assertSame('المكتب / المنطقة: مارع، عفرين', $summary['الفلاتر المطبّقة']);
    }

    public function test_syncing_the_regions_is_idempotent_and_skips_offices_that_do_not_exist_yet(): void
    {
        $before = [Region::count(), Office::whereNotNull('region_id')->count()];

        app(RegionCatalog::class)->sync($this->institution);
        $this->artisan('rowad:sync-regions')->assertSuccessful();

        $this->assertSame($before, [Region::count(), Office::whereNotNull('region_id')->count()]);
        $this->assertSame(7, Region::count());
        $this->assertSame(7, $before[1], 'the seeded offices are all linked');

        // An office created later (e.g. by the importer) joins its region at once; one the config does not list stays without.
        $azaz = Office::create(['institution_id' => $this->institution->id, 'slug' => 'azaz', 'name' => 'اعزاز', 'sort_order' => 60]);
        $this->assertSame('aleppo', $azaz->fresh()->region->slug);
        $other = Office::create(['institution_id' => $this->institution->id, 'slug' => 'somewhere', 'name' => 'مكان', 'sort_order' => 61]);
        $this->assertNull($other->fresh()->region_id);
    }
}
