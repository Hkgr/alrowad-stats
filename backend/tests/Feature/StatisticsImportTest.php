<?php

namespace Tests\Feature;

use App\Models\ActivityRecord;
use App\Models\ActivityRecordRevision;
use App\Models\ImportIssue;
use App\Models\Institution;
use App\Models\MainActivity;
use App\Models\Measure;
use App\Models\Project;
use App\Models\ProjectSectorAssignment;
use App\Models\Sector;
use App\Models\SubActivity;
use App\Services\Import\StatisticsImporter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Support\XlsxFixture;
use Tests\TestCase;

/** The statistics importer on small synthetic workbooks shaped like the institution's files. */
class StatisticsImportTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    private Institution $institution;

    private const HEADER = [
        'A' => 'المكتب', 'B' => 'level 1', 'C' => 'level 2', 'D' => 'level 3', 'E' => 'عدد الشعب', 'F' => 'رقم الدورة',
        'G' => 'ذكور - 18', 'H' => 'إناث - 18', 'I' => 'ذكور+ 18', 'J' => 'إناث + 18', 'K' => 'عدد الذكور',
        'L' => 'عدد الإناث', 'M' => 'عدد ذوي الاحتياجات الخاصة', 'N' => 'العدد الكامل',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->institution = Institution::where('slug', 'rowad')->first();

        $edu = Sector::create(['institution_id' => $this->institution->id, 'slug' => 'edu', 'code' => 'EDU', 'name' => 'مسار التعليم والتمكين', 'sort_order' => 1]);
        foreach (['athr' => 'أثر', 'adahi' => 'أضحيتي', 'ramadan' => 'رمضان الخير'] as $slug => $name) {
            $project = Project::create(['institution_id' => $this->institution->id, 'slug' => $slug, 'name' => $name]);
            ProjectSectorAssignment::create(['institution_id' => $this->institution->id, 'project_id' => $project->id, 'sector_id' => $edu->id, 'reference_year' => 2026]);
        }

        $this->root = sys_get_temp_dir().'/rowad-stats-'.uniqid();
        File::ensureDirectoryExists("{$this->root}/2025");
        File::ensureDirectoryExists("{$this->root}/2026");
        config(['statistics_import.office_slugs' => []]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->root);
        parent::tearDown();
    }

    /** A detail row of a monthly sheet: age split typed, male/female/total as formulas. */
    private function row(int $r, array $labels, ?array $ages, ?int $cachedTotal = null): array
    {
        $cells = $labels;
        if ($ages !== null) {
            [$mu, $fu, $mp, $fp] = $ages;
            $cells += ['G' => $mu, 'H' => $fu, 'I' => $mp, 'J' => $fp];
        }
        $male = $ages ? $ages[0] + $ages[2] : 0;
        $female = $ages ? $ages[1] + $ages[3] : 0;

        return $cells + [
            'K' => ['f' => "SUM(G{$r},I{$r})", 'v' => $male],
            'L' => ['f' => "SUM(H{$r},J{$r})", 'v' => $female],
            'N' => ['f' => "SUM(K{$r},L{$r})", 'v' => $cachedTotal ?? $male + $female],
        ];
    }

    private function athrWorkbook(int $janLiteracyMale = 5): string
    {
        $jan = [
            1 => self::HEADER,
            2 => $this->row(2, ['A' => 'جرابلس', 'B' => 'دعم التعليم', 'C' => 'طلاب منقطعين', 'D' => 'محو أمية', 'F' => 1], [$janLiteracyMale, 5, 0, 0]),
            3 => $this->row(3, ['D' => 'ابتدائي'], [10, 0, 2, 3]),
            4 => $this->row(4, ['D' => 'تاسع'], null),                       // blank inputs → missing
            5 => $this->row(5, ['A' => 'عفرين', 'B' => 'دعم التعليم', 'C' => 'دورات', 'D' => 'لغة'], [0, 0, 0, 0]), // explicit zero
            6 => $this->row(6, ['A' => 'عفرين', 'B' => 'دعم التعليم', 'C' => 'دورات', 'D' => 'لغة'], [1, 1, 0, 0]), // same label again
            7 => ['A' => 'الإجمالي', 'N' => ['f' => 'SUM(N2:N6)', 'v' => 27]],
            '_merges' => ['A2:A4', 'B2:B4', 'C2:C4'],
        ];
        $feb = [
            1 => self::HEADER,
            2 => $this->row(2, ['A' => 'جرابلس', 'B' => 'دعم التعليم', 'C' => 'طلاب منقطعين', 'D' => 'محو أمية'], [2, 2, 0, 0], cachedTotal: 99),
            3 => ['D' => 'ابتدائي', 'G' => 3, 'H' => 1, 'K' => 7, 'L' => ['f' => 'SUM(H3,J3)', 'v' => 1], 'N' => ['f' => 'SUM(K3,L3)', 'v' => 8]],
            4 => $this->row(4, ['A' => 'الباب', 'B' => 'دعم التعليم', 'D' => 'تاسع'], [3, 3, 0, 0]),   // L3 without L2
            '_merges' => ['A2:A3', 'B2:B3', 'C2:C3'],
        ];
        // Flattened copy (also writes the blank row as zeros): must never become extra records.
        $total = [1 => self::HEADER + ['O' => 'الشهر', 'P' => 'رقم الشهر', 'Q' => 'اسم المشروع', 'R' => 'السنة']];
        $r = 2;
        foreach ([['كانون الثاني', 1, 5], ['شباط', 2, 3]] as [$month, $number, $count]) {
            for ($i = 0; $i < $count; $i++, $r++) {
                $total[$r] = ['A' => 'x', 'N' => 0, 'O' => $month, 'P' => $number, 'Q' => 'أثر', 'R' => 2026];
            }
        }

        return XlsxFixture::create(['أثر - Jan' => $jan, 'أثر - Feb' => $feb, 'Total' => $total], "{$this->root}/2026/أثر 2026.xlsx");
    }

    private function import(array $files, bool $dryRun = false): array
    {
        config(['statistics_import.files' => $files]);

        return app(StatisticsImporter::class)->run($this->institution, $this->root, $dryRun);
    }

    private function athrRecords()
    {
        return ActivityRecord::where('project_id', Project::where('slug', 'athr')->value('id'))->where('is_active', true);
    }

    public function test_imports_detail_rows_with_their_activity_hierarchy(): void
    {
        $this->athrWorkbook();
        $report = $this->import(['2026/أثر 2026.xlsx' => ['project' => 'أثر', 'year' => 2026]]);

        // Jan: 2 rows + zero row + repeated label; Feb: recomputed row + row without L2. Blank and conflicting rows skipped.
        $this->assertSame(6, $this->athrRecords()->count());
        $this->assertSame(10 + 15 + 0 + 2 + 4 + 6, (int) $this->athrRecords()->sum('total_count'));
        $this->assertSame('imported', $report['files']['2026/أثر 2026.xlsx']['status']);

        // Main activity belongs to its project and Level 1; sub activities belong to their main.
        $main = MainActivity::where('name', 'طلاب منقطعين')->first();
        $this->assertSame('دعم التعليم', $main->category->name);
        $this->assertSame(['ابتدائي', 'محو أمية'], SubActivity::where('main_activity_id', $main->id)->orderBy('name')->pluck('name')->all());
        $this->assertSame(10 + 15 + 4, (int) $this->athrRecords()->where('main_activity_id', $main->id)->sum('total_count'));

        // The merged office/level cells stay inside their group: «عفرين» rows are not «جرابلس».
        $this->assertSame(1, (int) $this->athrRecords()->whereHas('office', fn ($q) => $q->where('name', 'عفرين'))->where('total_count', 2)->count());
        // The explicit zero is a record; the blank row is not.
        $this->assertSame(1, $this->athrRecords()->where('total_count', 0)->count());
        $this->assertFalse(SubActivity::where('name', 'تاسع')->exists());
    }

    public function test_reimport_is_idempotent_and_total_sheet_adds_nothing(): void
    {
        $this->athrWorkbook();
        $this->import(['2026/أثر 2026.xlsx' => ['project' => 'أثر', 'year' => 2026]]);
        $before = [ActivityRecord::count(), (int) ActivityRecord::where('is_active', true)->sum('total_count'), ActivityRecordRevision::count()];

        $second = $this->import(['2026/أثر 2026.xlsx' => ['project' => 'أثر', 'year' => 2026]]);

        $this->assertSame($before[0], ActivityRecord::count());
        $this->assertSame($before[1], (int) ActivityRecord::where('is_active', true)->sum('total_count'));
        $this->assertSame($before[2], ActivityRecordRevision::count());
        $this->assertSame(0, $second['files']['2026/أثر 2026.xlsx']['created']);
        $this->assertSame(6, $second['files']['2026/أثر 2026.xlsx']['unchanged']);
    }

    public function test_corrected_source_updates_matching_record_and_keeps_history(): void
    {
        $this->athrWorkbook();
        $this->import(['2026/أثر 2026.xlsx' => ['project' => 'أثر', 'year' => 2026]]);
        $record = $this->athrRecords()->where('total_count', 10)->first();

        $this->athrWorkbook(janLiteracyMale: 8);   // the source is corrected: 8 boys instead of 5
        $report = $this->import(['2026/أثر 2026.xlsx' => ['project' => 'أثر', 'year' => 2026]]);

        $this->assertSame(1, $report['files']['2026/أثر 2026.xlsx']['updated']);
        $this->assertSame(13, $record->fresh()->total_count);
        $revision = ActivityRecordRevision::where('activity_record_id', $record->id)->where('change', 'updated')->first();
        $this->assertSame(10, $revision->before['total_count']);
        $this->assertSame(13, $revision->after['total_count']);
        $this->assertSame(6, $this->athrRecords()->count());
    }

    public function test_conflicts_are_isolated_and_reported(): void
    {
        $this->athrWorkbook();
        $report = $this->import(['2026/أثر 2026.xlsx' => ['project' => 'أثر', 'year' => 2026]]);
        $codes = collect($report['issues'])->pluck('code');

        $this->assertContains('formula_recomputed', $codes->all());      // cached 99 → 4
        $this->assertContains('conflicting_counts', $codes->all());      // typed 7 boys vs age split 3
        $this->assertContains('sub_without_main', $codes->all());        // «تاسع» with no Level 2
        $this->assertSame(4, (int) $this->athrRecords()->whereHas('period', fn ($q) => $q->where('month', 2))->whereNotNull('main_activity_id')->sum('total_count'));
        $orphan = $this->athrRecords()->whereNull('main_activity_id')->first();
        $this->assertSame('تاسع', $orphan->details['level_3']);
        $this->assertTrue(ImportIssue::where('code', 'conflicting_counts')->exists());
    }

    public function test_dry_run_saves_nothing(): void
    {
        $this->athrWorkbook();
        $count = ActivityRecord::count();

        $report = $this->import(['2026/أثر 2026.xlsx' => ['project' => 'أثر', 'year' => 2026]], dryRun: true);

        $this->assertTrue($report['dry_run']);
        $this->assertSame($count, ActivityRecord::count());
        $this->assertFalse(MainActivity::exists());
    }

    public function test_real_source_supersedes_the_sample_without_double_counting(): void
    {
        $sheet = [1 => ['A' => 'المكتب', 'B' => 'level 1', 'C' => 'الشهر', 'D' => 'عدد الذكور', 'E' => 'عدد الإناث', 'F' => 'العدد الكامل']];
        $rows = [['جرابلس', 120, 130], ['الباب', 128, 122], ['سوسيان', 70, 80], ['مارع', 119, 131], ['عفرين', 115, 135], ['الأتارب', 82, 68], ['سلقين', 84, 66]];
        foreach ($rows as $i => [$office, $m, $f]) {
            $r = $i + 2;
            $sheet[$r] = ['A' => $office, 'B' => 'نشاط ترفيهي', 'C' => 'أيار', 'D' => $m, 'E' => $f, 'F' => ['f' => "SUM(E{$r}+D{$r})", 'v' => $m + $f]];
        }
        XlsxFixture::create(['لعبة و فرحة 2026' => $sheet], "{$this->root}/2026/لعبة و فرحة 2026.xlsx");

        $report = $this->import(['2026/لعبة و فرحة 2026.xlsx' => ['project' => 'لعبة وفرحة', 'year' => 2026]]);

        $this->assertSame(7, $report['superseded']);
        $this->getJson('/api/v1/dashboard?institution=rowad&project=loba-wa-farha')
            ->assertJsonPath('data.summary.total', 1450)
            ->assertJsonPath('data.summary.male', 718)
            ->assertJsonPath('data.summary.female', 732);
        $this->assertSame(7, ActivityRecord::where('is_active', false)->count());

        // Re-seeding afterwards does not bring the sample back.
        $this->seed(DatabaseSeeder::class);
        $this->getJson('/api/v1/dashboard?institution=rowad&project=loba-wa-farha')->assertJsonPath('data.summary.total', 1450);
    }

    public function test_families_are_a_separate_measure_and_percentage_genders_are_not_counts(): void
    {
        XlsxFixture::create(['أضحيتي' => [
            1 => ['A' => 'المكتب', 'B' => 'level 1', 'C' => 'الشهر', 'D' => 'عدد الأضاحي', 'E' => 'عدد العوائل', 'F' => 'العدد الكامل'],
            2 => ['A' => 'جرابلس', 'C' => 'آيار', 'D' => 14, 'E' => 314, 'F' => ['f' => 'SUM(E2)', 'v' => 314]],
        ]], "{$this->root}/2026/أضحيتي 2026.xlsx");
        XlsxFixture::create(['رمضان' => [
            1 => ['A' => 'المكتب', 'B' => 'level 1', 'C' => 'الشهر', 'D' => 'عدد الذكور', 'E' => 'عدد الإناث', 'F' => 'العدد الكامل'],
            2 => ['A' => 'جرابلس', 'B' => 'توزيع التمر', 'C' => 'شباط', 'D' => ['f' => 'F2*60%', 'v' => 72], 'E' => ['f' => '40%*F2', 'v' => 48], 'F' => 120],
        ]], "{$this->root}/2026/رمضان الخير 2026.xlsx");

        $this->import([
            '2026/أضحيتي 2026.xlsx' => ['project' => 'أضحيتي', 'year' => 2026, 'measure' => 'households_served'],
            '2026/رمضان الخير 2026.xlsx' => ['project' => 'رمضان الخير', 'year' => 2026],
        ]);

        $families = ActivityRecord::where('measure_id', Measure::where('code', Measure::HOUSEHOLDS_SERVED)->value('id'))->first();
        $this->assertSame(314, $families->total_count);
        $this->assertSame(14, $families->items_count);

        $ramadan = ActivityRecord::where('project_id', Project::where('slug', 'ramadan')->value('id'))->first();
        $this->assertSame(120, $ramadan->total_count);
        $this->assertNull($ramadan->male_count);
        $this->assertNull($ramadan->female_count);

        // People and families are reported separately, never summed.
        $this->getJson('/api/v1/dashboard?institution=rowad&project=adahi')
            ->assertJsonPath('data.has_data', false)
            ->assertJsonPath('data.other_measures.0.total', 314)
            ->assertJsonPath('data.other_measures.0.items', 14);
        $this->getJson('/api/v1/dashboard?institution=rowad&project=ramadan')
            ->assertJsonPath('data.summary.total', 120)
            ->assertJsonPath('data.summary.gender_unreported', 120);
    }

    public function test_january_after_december_with_a_contradicting_year_is_not_imported(): void
    {
        $dec = [1 => self::HEADER, 2 => $this->row(2, ['A' => 'جرابلس', 'B' => 'دعم التعليم', 'C' => 'طلاب منقطعين', 'D' => 'محو أمية'], [1, 1, 0, 0])];
        $jan = [1 => self::HEADER, 2 => $this->row(2, ['A' => 'جرابلس', 'B' => 'دعم التعليم', 'C' => 'طلاب منقطعين', 'D' => 'محو أمية'], [4, 4, 0, 0])];
        $total = [
            1 => self::HEADER + ['O' => 'الشهر', 'P' => 'رقم الشهر', 'Q' => 'اسم المشروع', 'R' => 'السنة'],
            2 => ['A' => 'جرابلس', 'B' => 'دعم التعليم', 'C' => 'طلاب منقطعين', 'D' => 'محو أمية', 'G' => 1, 'H' => 1, 'K' => 1, 'L' => 1, 'N' => 2, 'O' => 'كانون الأول', 'P' => 12, 'R' => 2025],
            3 => ['A' => 'جرابلس', 'B' => 'دعم التعليم', 'C' => 'طلاب منقطعين', 'D' => 'محو أمية', 'G' => 4, 'H' => 4, 'K' => 4, 'L' => 4, 'N' => 8, 'O' => 'كانون الثاني', 'P' => 1, 'R' => 2025],
        ];
        XlsxFixture::create(['أثر - كانون الأول' => $dec, 'أثر - كانون الثاني' => $jan, 'Total' => $total], "{$this->root}/2025/أثر.xlsx");

        $report = $this->import(['2025/أثر.xlsx' => ['project' => 'أثر', 'year' => 2025]]);

        $this->assertSame(1, $this->athrRecords()->count());
        $this->assertSame(['2025-12'], $report['files']['2025/أثر.xlsx']['periods']);
        $this->assertContains('period_unresolved', collect($report['issues'])->pluck('code')->all());
    }

    public function test_unlisted_and_lock_files_are_not_imported(): void
    {
        XlsxFixture::create(['x' => [1 => ['A' => 'المكتب']]], "{$this->root}/2026/جديد.xlsx");
        File::put("{$this->root}/2026/~\$قفل.xlsx", 'lock');

        $report = $this->import([]);

        $this->assertSame('unmapped', $report['files']['2026/جديد.xlsx']['status']);
        $this->assertArrayNotHasKey('2026/~$قفل.xlsx', $report['files']);
    }

    public function test_activity_filters_follow_the_parent_child_relation(): void
    {
        $this->athrWorkbook();
        $this->import(['2026/أثر 2026.xlsx' => ['project' => 'أثر', 'year' => 2026]]);
        $main = MainActivity::where('name', 'طلاب منقطعين')->first();
        $sub = SubActivity::where('name', 'ابتدائي')->first();
        $other = MainActivity::where('name', 'دورات')->first();
        $base = '/api/v1/dashboard?institution=rowad&project=athr';

        $this->getJson("{$base}")
            ->assertJsonPath('data.level', 'project')
            ->assertJsonPath('data.activities.main_available', true)
            ->assertJsonPath('data.activities.main.0.slug', $main->slug)
            ->assertJsonPath('data.activities.main.0.total', 29)
            ->assertJsonPath('data.activities.without_main.total', 6);

        $this->getJson("{$base}&main_activity={$main->slug}")
            ->assertJsonPath('data.level', 'main_activity')
            ->assertJsonPath('data.summary.total', 29)
            ->assertJsonPath('data.activities.sub_available', true);

        // Sum of the sub activities equals the main activity (no parent total counted twice).
        $subs = collect($this->getJson("{$base}&main_activity={$main->slug}")->json('data.activities.sub'))->sum('total');
        $this->assertSame(29, $subs);

        $this->getJson("{$base}&main_activity={$main->slug}&sub_activity={$sub->slug}")
            ->assertJsonPath('data.level', 'sub_activity')
            ->assertJsonPath('data.summary.total', 15);

        $this->getJson("/api/v1/dashboard?institution=rowad&main_activity={$main->slug}")->assertStatus(422)->assertJsonValidationErrors('main_activity');
        $this->getJson("{$base}&sub_activity={$sub->slug}")->assertStatus(422)->assertJsonValidationErrors('sub_activity');
        $this->getJson("{$base}&main_activity={$other->slug}&sub_activity={$sub->slug}")->assertStatus(422)->assertJsonValidationErrors('sub_activity');
        $this->getJson("/api/v1/dashboard?institution=rowad&project=loba-wa-farha&main_activity={$main->slug}")->assertStatus(422)->assertJsonValidationErrors('main_activity');
    }
}
