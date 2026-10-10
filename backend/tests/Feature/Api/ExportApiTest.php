<?php

namespace Tests\Feature\Api;

use App\Models\Institution;
use App\Models\Measure;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Support\XlsxReader;
use App\Support\XlsxWriter;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Records;
use Tests\TestCase;

class ExportApiTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = '/api/v1/export?institution=rowad';

    private Institution $institution;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->institution = Institution::where('slug', 'rowad')->first();
    }

    private function addRecords(string $projectName = 'مشروع التصدير'): Project
    {
        $project = Project::create(['institution_id' => $this->institution->id, 'slug' => 'export-'.md5($projectName), 'name' => $projectName]);
        $period = Period::create(['institution_id' => $this->institution->id, 'year' => 2025, 'month' => 10]);
        $common = [
            'institution_id' => $this->institution->id, 'project_id' => $project->id, 'period_id' => $period->id,
            'measure_id' => Measure::where('code', Measure::REGISTERED_BENEFITS)->value('id'), 'office_id' => Office::where('slug', 'afrin')->value('id'),
        ];
        // 30 + 20 with ages and special needs; a second row that only states the total.
        Records::make($common + ['male_under_18' => 10, 'female_under_18' => 5, 'male_adult' => 20, 'female_adult' => 15, 'male_count' => 30, 'female_count' => 20, 'disabled_count' => 4]);
        Records::make($common + ['total_count' => 40]);

        return $project;
    }

    /** @return list<list<string>> CSV rows without the BOM. */
    private function csv(string $query = ''): array
    {
        $response = $this->get(self::BASE.'&format=csv'.$query)->assertOk();
        $this->assertStringStartsWith('text/csv', $response->headers->get('Content-Type'));
        $body = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'UTF-8 BOM so Excel reads Arabic');

        return array_map('str_getcsv', array_filter(explode("\n", substr($body, 3))));
    }

    public function test_csv_rows_add_up_to_the_dashboard_total(): void
    {
        $this->addRecords();

        $rows = $this->csv();
        $this->assertSame(['السنة', 'الشهر', 'المسار', 'المشروع', 'المكتب', 'النشاط الرئيسي', 'النشاط الفرعي', 'ذكور', 'إناث', 'الجنس غير مذكور', 'الإجمالي'], $rows[0]);

        $total = $this->getJson('/api/v1/dashboard?institution=rowad')->json('data.summary.total');
        $this->assertSame(1450 + 90, $total);
        $this->assertSame($total, array_sum(array_column(array_slice($rows, 1), 10)));

        foreach (array_slice($rows, 1) as $row) {
            $this->assertSame((int) $row[10], (int) $row[7] + (int) $row[8] + (int) $row[9], 'male + female + unreported = total');
        }
        $mine = collect(array_slice($rows, 1))->firstWhere(3, 'مشروع التصدير');
        $this->assertSame(['2025', 'تشرين الأول', 'غير مصنف', 'مشروع التصدير', 'عفرين'], array_slice($mine, 0, 5));
        // The two records share a group: 30 + 20 with a gender, 40 whose gender the source does not state.
        $this->assertSame(['30', '20', '40', '90'], array_slice($mine, 7, 4));
    }

    public function test_filters_narrow_the_export_like_the_dashboard(): void
    {
        $project = $this->addRecords();

        $male = $this->csv('&gender=male&project='.$project->slug);
        $this->assertSame(30, (int) $male[1][10]);
        $this->assertCount(2, $male, 'a record that does not report the gender is out, not zero');

        $adult = $this->csv('&age=adult&project='.$project->slug);
        $this->assertSame(35, array_sum(array_column(array_slice($adult, 1), 10)));

        $special = $this->csv('&disabled=1&project='.$project->slug);
        $this->assertSame(4, array_sum(array_column(array_slice($special, 1), 10)));

    }

    public function test_text_that_looks_like_a_formula_is_neutralised_in_csv(): void
    {
        $project = $this->addRecords('=HYPERLINK("http://evil.example")');

        $rows = $this->csv('&project='.$project->slug);

        $this->assertSame('\'=HYPERLINK("http://evil.example")', $rows[1][3]);
    }

    public function test_xlsx_has_summary_detail_and_group_sheets_that_match(): void
    {
        $this->addRecords();

        $response = $this->get(self::BASE)->assertOk();
        $this->assertSame('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $copy = tempnam(sys_get_temp_dir(), 'xlsx-test-');
        copy($response->baseResponse->getFile()->getPathname(), $copy);

        $book = new XlsxReader($copy);
        $this->assertSame(['الملخص', 'التفاصيل', 'حسب المسار', 'حسب المشروع', 'حسب المكتب', 'حسب الشهر'], $book->sheetNames());

        $summary = collect($book->rows('الملخص'))->mapWithKeys(fn ($r) => [$r['A'] ?? '' => $r['B'] ?? '']);
        $this->assertSame('1540', $summary['الاستفادات المسجلة (الإجمالي)']);
        $this->assertSame('بلا فلاتر (كل البيانات)', $summary['الفلاتر المطبّقة']);

        $detail = array_slice($book->rows('التفاصيل'), 1);
        $this->assertSame(1540, array_sum(array_map(fn ($r) => (int) $r['K'], $detail)));

        $offices = collect($book->rows('حسب المكتب'))->skip(1)->mapWithKeys(fn ($r) => [$r['A'] => (int) $r['E']]);
        $onScreen = collect($this->getJson('/api/v1/dashboard?institution=rowad')->json('data.offices'))->pluck('total', 'name');
        $this->assertEquals($onScreen->all(), $offices->all());
        $this->assertSame(1540, $offices->sum());

        $months = collect($book->rows('حسب الشهر'))->skip(1)->pluck('A')->values()->all();
        $this->assertSame(['تشرين الأول 2025', 'أيار 2026'], $months);

        @unlink($copy);
    }

    public function test_an_empty_scope_still_exports_a_summary_that_says_so(): void
    {
        $response = $this->get(self::BASE.'&year=2026&age=adult')->assertOk();
        $book = new XlsxReader($response->baseResponse->getFile()->getPathname());

        $summary = collect($book->rows('الملخص'))->mapWithKeys(fn ($r) => [$r['A'] ?? '' => $r['B'] ?? '']);
        $this->assertSame('لا توجد سجلات تطابق هذه الفلاتر', $summary['النتيجة']);
        $this->assertCount(1, $book->rows('التفاصيل'), 'header only');
    }

    public function test_format_and_filters_are_validated(): void
    {
        $this->getJson(self::BASE.'&format=pdf')->assertStatus(422)->assertJsonValidationErrors('format');
        $this->getJson(self::BASE.'&year=1999')->assertStatus(422)->assertJsonValidationErrors('year');
        $this->getJson(self::BASE.'&disabled=1&gender=male')->assertStatus(422)->assertJsonValidationErrors('disabled');
        $this->getJson('/api/v1/export')->assertStatus(422)->assertJsonValidationErrors('institution');
    }

    public function test_xlsx_writer_keeps_markup_and_drops_forbidden_characters(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx-writer-');
        (new XlsxWriter)
            ->addSheet('أ/ب:ج?*[x]', [['اسم', 'قيمة'], ['<b>"a" & \'b\'</b>'."\x01\x0B", 12], ['=1+1', 1.5]])
            ->save($path);

        $book = new XlsxReader($path);
        $this->assertSame(['أ ب ج   x'], $book->sheetNames());
        $rows = $book->rows($book->sheetNames()[0]);
        $this->assertSame('<b>"a" & \'b\'</b>', $rows[2]['A']);
        $this->assertSame('12', $rows[2]['B']);
        $this->assertSame('=1+1', $rows[3]['A'], 'stored as text, never a formula');
        @unlink($path);
    }
}
