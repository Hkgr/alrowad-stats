<?php

namespace App\Services\Classification;

use App\Models\DataSource;
use App\Models\Institution;
use App\Models\Project;
use App\Models\ProjectSectorAssignment;
use App\Models\Sector;
use App\Support\ArabicName;
use App\Support\XlsxReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Imports "project → track" links from the institution's project list for one reference year.
 *
 * - Re-runnable: sectors, projects and links are matched before anything is created.
 * - Matching is exact on normalized names, or explicit via config aliases. No fuzzy merging.
 * - Only names and tracks are read. Statuses and every number in the workbook are ignored.
 * - Anything that cannot be resolved safely is reported and skipped, never guessed.
 */
class ProjectClassificationImporter
{
    /** @var array<string, mixed> */
    private array $report;

    /**
     * @return array<string, mixed> the import report
     */
    public function import(string $path, Institution $institution, int $year, bool $dryRun = false): array
    {
        $reader = new XlsxReader($path);
        $config = config('project_classification');

        $this->report = [
            'file' => basename($path),
            'institution' => $institution->slug,
            'reference_year' => $year,
            'dry_run' => $dryRun,
            'sectors' => [],
            'projects' => ['created' => [], 'matched' => [], 'aliased' => []],
            'assignments' => ['created' => 0, 'changed' => [], 'unchanged' => 0],
            'conflicts' => [],
            'kept_separate' => [],
            'cross_check' => [],
            'notes' => [],
        ];

        $tracks = $this->readPrimarySheet($reader, $config['primary_sheet']);

        DB::beginTransaction();
        try {
            $source = DataSource::updateOrCreate(
                ['institution_id' => $institution->id, 'slug' => "project-list-{$year}"],
                [
                    'label' => "قائمة المشاريع {$year}",
                    'file_name' => basename($path),
                    'reference_url' => null,
                    'coverage' => DataSource::COVERAGE_REFERENCE,
                    'notes' => 'مرجع تصنيف المشروع ← المسار فقط. لا تُستورد منه أعداد أو حالات أو إحصاءات.',
                ],
            );

            $aliases = $this->normalizedKeys($config['aliases'] ?? []);
            $seen = [];

            foreach ($tracks as $track) {
                $sector = $this->upsertSector($institution, $track);

                foreach ($track['projects'] as $rawName) {
                    $key = ArabicName::normalize($rawName);

                    if (isset($seen[$key])) {
                        $this->conflict($rawName, "الاسم مكرر في الملف (ورد أولًا في «{$seen[$key]}»)؛ تُرك الربط الأول.");

                        continue;
                    }
                    $seen[$key] = $track['name'];

                    $project = $this->resolveProject($institution, $rawName, $key, $aliases);
                    if ($project === null) {
                        continue;
                    }

                    $this->upsertAssignment($institution, $project, $sector, $year, $rawName, $source);
                }
            }

            $this->reportKeptSeparate($tracks);
            $this->crossCheck($reader, $config, $tracks);

            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        return $this->report;
    }

    /**
     * @return list<array{order: int, code: ?string, name: string, header_number: ?string, projects: list<string>}>
     */
    private function readPrimarySheet(XlsxReader $reader, string $sheet): array
    {
        $rows = $reader->rows($sheet);

        $headerRow = null;
        foreach ($rows as $number => $cells) {
            if (count(array_filter($cells, fn ($v) => str_starts_with(trim($v), 'مسار'))) >= 2) {
                $headerRow = $number;
                break;
            }
        }
        if ($headerRow === null) {
            throw new RuntimeException("No track header row found in sheet «{$sheet}».");
        }

        $columns = array_keys(array_filter($rows[$headerRow], fn ($v) => str_starts_with(trim($v), 'مسار')));
        usort($columns, fn ($a, $b) => $this->columnIndex($a) <=> $this->columnIndex($b));

        $codeRow = $rows[$headerRow + 1] ?? [];
        $hasCodes = array_intersect_key($codeRow, array_flip($columns)) !== [];
        $firstDataRow = $headerRow + ($hasCodes ? 2 : 1);

        $tracks = [];
        foreach ($columns as $i => $column) {
            $numberColumn = $this->columnLetter($this->columnIndex($column) - 1);
            $headerNumber = $rows[$headerRow][$numberColumn] ?? null;
            $order = $i + 1;

            if ($headerNumber !== null && (int) $headerNumber !== $order) {
                $this->report['notes'][] = "رقم المسار في رأس العمود {$numberColumn} هو «{$headerNumber}» بينما ترتيبه في الملف {$order}؛ اعتُمد الترتيب.";
            }

            $projects = [];
            foreach ($rows as $number => $cells) {
                if ($number >= $firstDataRow && isset($cells[$column])) {
                    $projects[] = trim($cells[$column]);
                }
            }

            $tracks[] = [
                'order' => $order,
                'code' => isset($codeRow[$column]) ? strtoupper(trim($codeRow[$column])) : null,
                'name' => preg_replace('/\s+/u', ' ', trim($rows[$headerRow][$column])),
                'header_number' => $headerNumber,
                'projects' => $projects,
            ];
        }

        return $tracks;
    }

    /** @param array{order: int, code: ?string, name: string} $track */
    private function upsertSector(Institution $institution, array $track): Sector
    {
        $slug = $track['code'] ? strtolower($track['code']) : 'track-'.$track['order'];

        $sector = Sector::firstOrNew(['institution_id' => $institution->id, 'slug' => $slug]);
        $status = $sector->exists ? 'unchanged' : 'created';
        $sector->fill(['code' => $track['code'], 'name' => $track['name'], 'sort_order' => $track['order']]);
        if ($sector->exists && $sector->isDirty()) {
            $status = 'updated';
        }
        $sector->save();

        $this->report['sectors'][] = [
            'order' => $track['order'], 'code' => $track['code'], 'name' => $track['name'],
            'projects' => count($track['projects']), 'status' => $status,
        ];

        return $sector;
    }

    /** @param array<string, string> $aliases normalized name => project slug */
    private function resolveProject(Institution $institution, string $rawName, string $key, array $aliases): ?Project
    {
        if (isset($aliases[$key])) {
            $project = Project::where('institution_id', $institution->id)->where('slug', $aliases[$key])->first();
            if ($project === null) {
                $this->conflict($rawName, "الـalias يشير إلى مشروع غير موجود ({$aliases[$key]}).");

                return null;
            }
            $this->report['projects']['aliased'][] = "{$rawName} ← {$project->name}";

            return $project;
        }

        $candidates = $this->existingByKey($institution)[$key] ?? [];
        if (count($candidates) > 1) {
            $names = Project::whereIn('id', $candidates)->pluck('name')->implode('، ');
            $this->conflict($rawName, "يطابق أكثر من مشروع موجود ({$names}).");

            return null;
        }
        if (count($candidates) === 1) {
            $project = Project::find($candidates[0]);
            $this->report['projects']['matched'][] = $project->name;

            return $project;
        }

        $display = ArabicName::display($rawName);
        $project = Project::create([
            'institution_id' => $institution->id,
            'slug' => $this->uniqueSlug($institution, $display),
            'name' => $display,
        ]);
        $this->report['projects']['created'][] = $display;

        return $project;
    }

    /**
     * Existing projects indexed by normalized name and by the source names they were linked under.
     *
     * @return array<string, list<int>>
     */
    private function existingByKey(Institution $institution): array
    {
        $index = [];
        foreach (Project::where('institution_id', $institution->id)->get(['id', 'name']) as $project) {
            $index[ArabicName::normalize($project->name)][] = $project->id;
        }
        foreach (ProjectSectorAssignment::where('institution_id', $institution->id)->whereNotNull('source_name')->get(['project_id', 'source_name']) as $link) {
            $index[ArabicName::normalize($link->source_name)][] = $link->project_id;
        }

        return array_map(fn ($ids) => array_values(array_unique($ids)), $index);
    }

    private function upsertAssignment(Institution $institution, Project $project, Sector $sector, int $year, string $rawName, DataSource $source): void
    {
        $link = ProjectSectorAssignment::firstOrNew(['project_id' => $project->id, 'reference_year' => $year]);

        if (! $link->exists) {
            $this->report['assignments']['created']++;
        } elseif ((int) $link->sector_id !== (int) $sector->id) {
            $previous = Sector::find($link->sector_id)?->name;
            $this->report['assignments']['changed'][] = "{$project->name}: {$previous} ← {$sector->name}";
        } else {
            $this->report['assignments']['unchanged']++;
        }

        $link->fill([
            'institution_id' => $institution->id,
            'sector_id' => $sector->id,
            'source_name' => $rawName,
            'data_source_id' => $source->id,
        ])->save();
    }

    /** Lists similar names that were intentionally NOT merged (e.g. a hospital vs its rehabilitation project). */
    private function reportKeptSeparate(array $tracks): void
    {
        $names = [];
        foreach ($tracks as $track) {
            foreach ($track['projects'] as $raw) {
                $names[ArabicName::normalize($raw)] = ArabicName::display($raw).' ('.$track['name'].')';
            }
        }

        $keys = array_keys($names);
        foreach ($keys as $a) {
            foreach ($keys as $b) {
                if ($a !== $b && mb_strlen($a) >= 8 && str_contains($b, $a)) {
                    $this->report['kept_separate'][] = "{$names[$a]} ≠ {$names[$b]}";
                }
            }
        }
    }

    private function crossCheck(XlsxReader $reader, array $config, array $tracks): void
    {
        $primary = [];
        foreach ($tracks as $track) {
            foreach ($track['projects'] as $raw) {
                $primary[ArabicName::normalize($raw)] = ['name' => $raw, 'track' => ArabicName::normalize($track['name'])];
            }
        }
        $aliases = [];
        foreach ($config['cross_check_aliases'] ?? [] as $variant => $canonical) {
            $aliases[ArabicName::normalize($variant)] = ArabicName::normalize($canonical);
        }

        foreach ($config['cross_check_sheets'] ?? [] as $sheet => $layout) {
            if (! $reader->hasSheet($sheet)) {
                $this->report['cross_check'][$sheet] = ['missing_sheet' => true];

                continue;
            }

            $result = ['agree' => 0, 'aliased' => 0, 'mismatch' => [], 'unknown' => [], 'not_listed' => []];
            $found = [];

            foreach ($reader->rows($sheet) as $number => $cells) {
                $name = $cells[$layout['name']] ?? null;
                $track = $cells[$layout['track']] ?? null;
                if ($number < $layout['from_row'] || $name === null || $track === null || ! str_starts_with(trim($track), 'مسار')) {
                    continue;
                }

                $key = ArabicName::normalize($name);
                if (! isset($primary[$key]) && isset($aliases[$key])) {
                    $key = $aliases[$key];
                    $result['aliased']++;
                }
                if (! isset($primary[$key])) {
                    $result['unknown'][] = trim($name);

                    continue;
                }

                $found[$key] = true;
                if ($primary[$key]['track'] === ArabicName::normalize($track)) {
                    $result['agree']++;
                } else {
                    $result['mismatch'][] = trim($name).': «'.trim($track).'» في هذه الورقة';
                }
            }

            foreach ($primary as $key => $item) {
                if (! isset($found[$key])) {
                    $result['not_listed'][] = ArabicName::display($item['name']);
                }
            }

            $this->report['cross_check'][$sheet] = $result;
            foreach ($result['mismatch'] as $mismatch) {
                $this->conflict($mismatch, "تعارض في المسار مع ورقة «{$sheet}»؛ اعتُمدت «قائمة المشاريع».");
            }
        }
    }

    private function conflict(string $name, string $reason): void
    {
        $this->report['conflicts'][] = ['name' => $name, 'reason' => $reason];
    }

    /** @param array<string, string> $map */
    private function normalizedKeys(array $map): array
    {
        $out = [];
        foreach ($map as $name => $value) {
            $out[ArabicName::normalize($name)] = $value;
        }

        return $out;
    }

    private function uniqueSlug(Institution $institution, string $name): string
    {
        $base = Str::limit(Str::slug($name), 80, '') ?: 'project';
        $slug = $base;
        $i = 2;
        while (Project::where('institution_id', $institution->id)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $index;
    }

    private function columnLetter(int $index): string
    {
        $letters = '';
        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $index = intdiv($index - 1, 26);
        }

        return $letters;
    }
}
