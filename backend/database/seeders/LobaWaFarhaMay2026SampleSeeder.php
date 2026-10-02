<?php

namespace Database\Seeders;

use App\Models\ActivityRecord;
use App\Models\DataSource;
use App\Models\Institution;
use App\Models\Measure;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSectorAssignment;
use App\Models\Sector;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Phase 1 seed: ONE slice of the source files — project "لعبة وفرحة", May 2026, seven offices.
 *
 * This is an extract of the vetted sample, not an import of the institution's files.
 * Rows come from data/raw/2026/لعبة و فرحة 2026.xlsx (sheet columns: المكتب, level 1, الشهر,
 * عدد الذكور, عدد الإناث, العدد الكامل). The workbook's "level 1" value (نشاط ترفيهي) is kept
 * verbatim as source_category; it is a source label, not a sector.
 *
 * The track comes from the 2026 project list (data/Projects List_2026_09-29.xlsx): "مشروع لعبة
 * وفرحة" is under track 2 "مسار الثقافة والرياضة والتسلية والفنون" (code CUL). The seeder keeps
 * that single 2026 link so the sample works on its own; `php artisan rowad:import-classification`
 * imports the full list and matches this same project instead of duplicating it.
 *
 * Re-running is safe: rows are created only when missing, and never once the real workbook has
 * been imported (the importer supersedes these sample rows).
 */
class LobaWaFarhaMay2026SampleSeeder extends Seeder
{
    /** [office, male, female, total as written in the source] */
    private const ROWS = [
        ['جرابلس', 120, 130, 250],
        ['الباب', 128, 122, 250],
        ['سوسيان', 70, 80, 150],
        ['مارع', 119, 131, 250],
        ['عفرين', 115, 135, 250],
        ['الأتارب', 82, 68, 150],
        ['سلقين', 84, 66, 150],
    ];

    private const EXPECTED = ['male' => 718, 'female' => 732, 'total' => 1450, 'offices' => 7];

    public function run(): void
    {
        $this->assertSourceIsConsistent();

        $this->call(MeasureSeeder::class);

        DB::transaction(function () {
            $measure = Measure::where('code', Measure::REGISTERED_BENEFITS)->firstOrFail();

            $institution = Institution::updateOrCreate(
                ['slug' => 'rowad'],
                ['name' => 'مؤسسة الرواد', 'is_active' => true],
            );

            $sector = Sector::updateOrCreate(
                ['institution_id' => $institution->id, 'slug' => 'cul'],
                ['code' => 'CUL', 'name' => 'مسار الثقافة والرياضة والتسلية والفنون', 'sort_order' => 2],
            );

            $project = Project::updateOrCreate(
                ['institution_id' => $institution->id, 'slug' => 'loba-wa-farha'],
                ['name' => 'لعبة وفرحة', 'source_category' => 'نشاط ترفيهي'],
            );

            ProjectSectorAssignment::updateOrCreate(
                ['project_id' => $project->id, 'reference_year' => 2026],
                ['institution_id' => $institution->id, 'sector_id' => $sector->id, 'source_name' => 'مشروع لعبة وفرحة'],
            );

            $period = Period::updateOrCreate(
                ['institution_id' => $institution->id, 'year' => 2026, 'month' => 5],
            );

            $source = DataSource::updateOrCreate(
                ['institution_id' => $institution->id, 'slug' => 'loba-wa-farha-2026-may-sample'],
                [
                    'label' => 'لعبة وفرحة 2026 — عينة أيار',
                    'file_name' => 'لعبة و فرحة 2026.xlsx',
                    'reference_url' => 'https://docs.google.com/spreadsheets/d/14F0rwVfdqn_XCY1J3EO3eABOoxxkOEcXEH0w7RmtG0E/edit',
                    'coverage' => DataSource::COVERAGE_SAMPLE,
                    'notes' => 'مقطع أول مأخوذ من ملف المشروع: شهر أيار 2026 فقط لسبعة مكاتب. '
                        .'ليس استيرادًا كاملًا لملفات المؤسسة لعامي 2025 و2026.',
                ],
            );

            // Once the real workbook has been imported (rowad:import-statistics), the sample is
            // superseded: never recreate or re-activate it, so nothing is counted twice.
            $realSource = ActivityRecord::where('project_id', $project->id)->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('data_source_id')->orWhere('data_source_id', '!=', $source->id))
                ->exists();

            foreach (self::ROWS as $order => [$officeName, $male, $female]) {
                $office = Office::firstOrCreate(
                    ['institution_id' => $institution->id, 'slug' => $this->officeSlug($officeName)],
                    ['name' => $officeName, 'sort_order' => $order + 1],
                );

                if ($realSource) {
                    continue;
                }

                ActivityRecord::firstOrCreate(
                    [
                        'project_id' => $project->id,
                        'office_id' => $office->id,
                        'period_id' => $period->id,
                        'measure_id' => $measure->id,
                        'detail_key' => self::detailKey(),
                    ],
                    [
                        'institution_id' => $institution->id,
                        'data_source_id' => $source->id,
                        'male_count' => $male,
                        'female_count' => $female,
                        'total_count' => $male + $female,
                        'is_active' => true,
                    ],
                );
            }
        });
    }

    /** Same key the migration gave the phase 1 rows: no activity path, occurrence 1. */
    public static function detailKey(): string
    {
        return sha1(json_encode(['', '', '', '', '', 1]));
    }

    /** Fails loudly if the embedded rows stop matching the reference totals. */
    private function assertSourceIsConsistent(): void
    {
        $male = $female = $total = 0;

        foreach (self::ROWS as [$office, $m, $f, $t]) {
            if ($m + $f !== $t) {
                throw new RuntimeException("Source row for {$office} is inconsistent: {$m} + {$f} != {$t}.");
            }
            $male += $m;
            $female += $f;
            $total += $t;
        }

        $actual = ['male' => $male, 'female' => $female, 'total' => $total, 'offices' => count(self::ROWS)];

        if ($actual !== self::EXPECTED) {
            throw new RuntimeException('Sample totals differ from the reference: '.json_encode($actual));
        }
    }

    /** Stable ASCII slug for the seven known offices (URLs stay shareable). */
    private function officeSlug(string $name): string
    {
        return [
            'جرابلس' => 'jarabulus',
            'الباب' => 'al-bab',
            'سوسيان' => 'sosian',
            'مارع' => 'marea',
            'عفرين' => 'afrin',
            'الأتارب' => 'atarib',
            'سلقين' => 'salqin',
        ][$name];
    }
}
