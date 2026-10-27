<?php

namespace App\Services\Organization;

use App\Models\Organization\OrgCenter;
use App\Models\Organization\OrgCenterYear;
use App\Models\Organization\OrgProgram;
use App\Models\Organization\OrgProgramYear;
use App\Models\Organization\OrgProject;
use App\Models\Organization\OrgProjectYear;
use App\Models\Organization\OrgRegion;
use App\Models\Organization\OrgRegionYear;
use App\Support\ArabicName;
use App\Support\XlsxReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads the organization workbook. Beneficiary figures come from the center sheet (one row per
 * project, center and year). Blank center rows and the repeated "Year" subtotal are skipped.
 * The other three sheets supply the rollup checks and the 2024 snapshots.
 */
class OrganizationImporter
{
    /** @var array<int, string> */
    private const YEARS = [2017, 2018, 2019, 2020, 2021, 2022, 2023, 2024];

    /** @var array<string, string> program slug => display name */
    private array $programNames = [];

    public function import(string $path): array
    {
        $reader = new XlsxReader($path);
        $programs = $this->programs($reader);
        $projects = $this->projects($reader, $programs);
        $regions = $this->regions($reader);
        $centers = $this->centers($reader, $projects, $regions);

        $projectTotal = 0;
        foreach ($projects as $project) {
            $projectTotal += array_sum($project['years']);
        }
        $centerTotal = 0;
        foreach ($centers['facts'] as $fact) {
            $centerTotal += $fact['beneficiaries'];
        }
        if ($centerTotal !== $projectTotal) {
            throw new RuntimeException("مجموع المراكز ({$centerTotal}) لا يطابق مجموع المشاريع ({$projectTotal}).");
        }
        foreach ($regions as $slug => $region) {
            $fromCenters = 0;
            foreach ($centers['facts'] as $fact) {
                if ($fact['region'] === $slug) {
                    $fromCenters += $fact['beneficiaries'];
                }
            }
            $stated = array_sum($region['years']);
            if ($fromCenters !== $stated) {
                throw new RuntimeException("منطقة {$region['name']}: المراكز {$fromCenters} والورقة {$stated}.");
            }
        }

        DB::transaction(function () use ($programs, $projects, $regions, $centers) {
            OrgCenterYear::query()->delete();
            OrgCenter::query()->delete();
            OrgProjectYear::query()->delete();
            OrgRegionYear::query()->delete();
            OrgProgramYear::query()->delete();
            OrgProject::query()->delete();
            OrgRegion::query()->delete();
            OrgProgram::query()->delete();

            $programIds = [];
            foreach ($programs as $slug => $program) {
                $programIds[$slug] = OrgProgram::create([
                    'slug' => $slug,
                    'code' => $program['code'],
                    'name' => $program['name'],
                    'sort_order' => $program['sort'],
                ])->id;
                foreach ($program['project_counts'] as $year => $count) {
                    if ($count === null) {
                        continue;
                    }
                    OrgProgramYear::create([
                        'program_id' => $programIds[$slug],
                        'year' => $year,
                        'projects_count' => $count,
                    ]);
                }
            }

            $projectIds = [];
            foreach ($projects as $key => $project) {
                $projectIds[$key] = OrgProject::create([
                    'program_id' => $programIds[$project['program']],
                    'slug' => $project['slug'],
                    'name' => $project['name'],
                    'disabled_count' => $project['disabled'],
                    'staff_count' => $project['staff'],
                    'sort_order' => $project['sort'],
                ])->id;
                foreach ($project['years'] as $year => $count) {
                    OrgProjectYear::create([
                        'project_id' => $projectIds[$key],
                        'year' => $year,
                        'beneficiaries' => $count,
                    ]);
                }
            }

            $regionIds = [];
            foreach ($regions as $slug => $region) {
                $regionIds[$slug] = OrgRegion::create([
                    'slug' => $slug,
                    'name' => $region['name'],
                    'disabled_count' => $region['disabled'],
                    'staff_count' => $region['staff'],
                    'offices_count' => $region['offices'],
                    'sort_order' => $region['sort'],
                ])->id;
                foreach ($region['years'] as $year => $count) {
                    OrgRegionYear::create([
                        'region_id' => $regionIds[$slug],
                        'year' => $year,
                        'beneficiaries' => $count,
                        'projects_count' => $region['project_counts'][$year] ?? null,
                    ]);
                }
            }

            $centerIds = [];
            foreach ($centers['places'] as $slug => $place) {
                $centerIds[$slug] = OrgCenter::create([
                    'region_id' => $regionIds[$place['region']] ?? null,
                    'slug' => $slug,
                    'name' => $place['name'],
                    'sort_order' => $place['sort'],
                ])->id;
            }
            foreach ($centers['facts'] as $fact) {
                OrgCenterYear::create([
                    'project_id' => $projectIds[$fact['project']],
                    'center_id' => $centerIds[$fact['center']],
                    'year' => $fact['year'],
                    'beneficiaries' => $fact['beneficiaries'],
                ]);
            }
        });

        return [
            'programs' => count($programs),
            'projects' => count($projects),
            'regions' => count($regions),
            'centers' => count($centers['places']),
            'facts' => count($centers['facts']),
            'beneficiaries' => $centerTotal,
        ];
    }

    /** @return array<string, array{name: string, code: string, sort: int, project_counts: array<int, ?int>}> */
    private function programs(XlsxReader $reader): array
    {
        $sheet = $this->sheet($reader, 'احصائيات البرامج');
        $codes = $this->normalizedMap(config('organization_import.program_codes'));
        $out = [];
        $sort = 0;
        foreach ($reader->rows($sheet) as $number => $cells) {
            if ($number === 1 || ! isset($cells['A'])) {
                continue;
            }
            $name = ArabicName::display($cells['A']);
            $key = ArabicName::normalize($name);
            if (in_array($key, ['المجموع', 'الاجمالي', 'توتال'], true)) {
                continue;
            }
            $code = $codes[$key] ?? null;
            if ($code === null) {
                throw new RuntimeException("برنامج غير معروف في ورقة البرامج: {$name}");
            }
            $slug = $this->slug($name, array_keys($out));
            $this->programNames[$slug] = $name;
            $out[$slug] = [
                'name' => $name,
                'code' => $code,
                'sort' => $sort++,
                'project_counts' => $this->yearValues($cells, ['L', 'M', 'N', 'O', 'P', 'Q', 'R', 'S'], keepNull: true),
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{name: string, code: string}>  $programs
     * @return array<string, array{program: string, slug: string, name: string, years: array<int, int>, disabled: int, staff: int, sort: int}>
     */
    private function projects(XlsxReader $reader, array $programs): array
    {
        $sheet = $this->sheet($reader, 'المشاريع');
        $byName = [];
        foreach ($programs as $slug => $program) {
            $byName[ArabicName::normalize($program['name'])] = $slug;
        }
        $out = [];
        $sort = 0;
        $carriedProgram = '';
        foreach ($reader->rows($sheet) as $number => $cells) {
            if ($number === 1 || ! isset($cells['B'])) {
                continue;
            }
            if (isset($cells['A']) && trim($cells['A']) !== '') {
                $carriedProgram = $cells['A'];
            }
            $projectLabel = ArabicName::normalize($cells['B']);
            if ($projectLabel === '' || str_contains($projectLabel, 'توتال') || in_array($projectLabel, ['المجموع', 'الاجمالي'], true)) {
                continue;
            }
            $programName = $this->alias(config('organization_import.program_aliases'), $carriedProgram);
            $program = $byName[ArabicName::normalize($programName)] ?? null;
            if ($program === null) {
                throw new RuntimeException('مشروع بلا برنامج معروف: '.trim($cells['B']));
            }
            $name = ArabicName::display($cells['B']);
            $key = $program.'|'.ArabicName::normalize($name);
            $out[$key] = [
                'program' => $program,
                'slug' => $this->slug($name, array_column($out, 'slug')),
                'name' => $name,
                'years' => $this->yearValues($cells, ['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J']),
                'disabled' => $this->num($cells['L'] ?? null) ?? 0,
                'staff' => $this->num($cells['M'] ?? null) ?? 0,
                'sort' => $sort++,
            ];
        }

        return $out;
    }

    /** @return array<string, array{name: string, years: array<int, int>, project_counts: array<int, ?int>, disabled: int, staff: int, offices: int, sort: int}> */
    private function regions(XlsxReader $reader): array
    {
        $sheet = $this->sheet($reader, 'احصائيات منطقه');
        $out = [];
        $sort = 0;
        foreach ($reader->rows($sheet) as $number => $cells) {
            if ($number === 1 || ! isset($cells['A'])) {
                continue;
            }
            $name = ArabicName::display($cells['A']);
            if (in_array(ArabicName::normalize($name), ['المجموع', 'الاجمالي', 'توتال'], true)) {
                continue;
            }
            $out[$this->slug($name, array_keys($out))] = [
                'name' => $name,
                'years' => $this->yearValues($cells, ['B', 'C', 'D', 'E', 'F', 'G', 'H', 'I']),
                'project_counts' => $this->yearValues($cells, ['M', 'N', 'O', 'P', 'Q', 'R', 'S', 'T'], keepNull: true),
                'disabled' => $this->num($cells['K'] ?? null) ?? 0,
                'offices' => $this->num($cells['L'] ?? null) ?? 0,
                'staff' => $this->num($cells['U'] ?? null) ?? 0,
                'sort' => $sort++,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, array{program: string, name: string}>  $projects
     * @param  array<string, array{name: string}>  $regions
     * @return array{places: array<string, array{name: string, region: string, sort: int}>, facts: list<array{project: string, center: string, region: string, year: int, beneficiaries: int}>}
     */
    private function centers(XlsxReader $reader, array $projects, array $regions): array
    {
        $sheet = $this->sheet($reader, 'مراكز');
        $projectKey = [];
        foreach ($projects as $key => $project) {
            $projectKey[ArabicName::normalize($project['name'])] = $key;
        }
        $regionByName = [];
        foreach ($regions as $slug => $region) {
            $regionByName[ArabicName::normalize($region['name'])] = $slug;
        }
        $regionOfCenter = $this->normalizedMap(config('organization_import.center_regions'));
        $skip = array_map(fn ($name) => ArabicName::normalize($name), config('organization_import.skip_centers'));

        $places = [];
        $facts = [];
        $factIndex = [];
        $program = '';
        $project = '';
        foreach ($reader->rows($sheet) as $number => $cells) {
            if ($number === 1) {
                continue;
            }
            if (isset($cells['A']) && trim($cells['A']) !== '') {
                $program = $this->alias(config('organization_import.program_aliases'), $cells['A']);
            }
            if (isset($cells['B']) && trim($cells['B']) !== '') {
                $project = $this->alias(config('organization_import.project_aliases'), $cells['B']);
            }
            $centerName = trim($cells['C'] ?? '');
            if ($centerName === '' || in_array(ArabicName::normalize($centerName), $skip, true)) {
                continue;
            }
            $years = $this->yearValues($cells, ['K', 'J', 'I', 'H', 'G', 'F', 'E', 'D']);
            if (array_sum($years) === 0 && ! $this->hasAny($cells, ['K', 'J', 'I', 'H', 'G', 'F', 'E', 'D'])) {
                continue;
            }
            $projectId = $projectKey[ArabicName::normalize($project)] ?? null;
            if ($projectId === null || $projects[$projectId]['program'] !== $this->programSlug($program, $projects)) {
                throw new RuntimeException("مركز بلا مشروع مطابق: {$program} / {$project} / {$centerName}");
            }
            $regionName = $regionOfCenter[ArabicName::normalize($centerName)] ?? ArabicName::display($centerName);
            $region = $regionByName[ArabicName::normalize($regionName)] ?? null;
            if ($region === null) {
                throw new RuntimeException("مركز بلا منطقة: {$centerName}");
            }
            $center = $this->placeSlug($places, ArabicName::display($centerName), $region);
            foreach ($years as $year => $count) {
                if (! $this->hasAny($cells, $this->columnForYear($year))) {
                    continue;
                }
                $factKey = $projectId.'|'.$center.'|'.$year;
                if (isset($factIndex[$factKey])) {
                    $facts[$factIndex[$factKey]]['beneficiaries'] += $count;

                    continue;
                }
                $factIndex[$factKey] = count($facts);
                $facts[] = [
                    'project' => $projectId,
                    'center' => $center,
                    'region' => $region,
                    'year' => $year,
                    'beneficiaries' => $count,
                ];
            }
        }

        return ['places' => $places, 'facts' => $facts];
    }

    /** @param  array<string, array{program: string, name: string}>  $projects */
    private function programSlug(string $programName, array $projects): ?string
    {
        $wanted = ArabicName::normalize($programName);
        foreach ($projects as $project) {
            if (ArabicName::normalize($this->programNameOf($project['program'])) === $wanted) {
                return $project['program'];
            }
        }

        return null;
    }

    private function programNameOf(string $slug): string
    {
        return $this->programNames[$slug] ?? $slug;
    }

    /**
     * The center sheet names a program in words, while projects are keyed by the program slug.
     * Remember the display name while the programs are parsed.
     *
     * @param  array<string, string>  $columns
     * @return array<int, int|null>
     */
    private function yearValues(array $cells, array $columns, bool $keepNull = false): array
    {
        $out = [];
        foreach (self::YEARS as $index => $year) {
            $value = $this->num($cells[$columns[$index]] ?? null);
            if ($value === null && ! $keepNull) {
                $value = 0;
            }
            $out[$year] = $value;
        }

        return $out;
    }

    /** @param  list<string>  $columns */
    private function hasAny(array $cells, array $columns): bool
    {
        foreach ($columns as $column) {
            if ($this->num($cells[$column] ?? null) !== null) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function columnForYear(int $year): array
    {
        $columns = [2017 => 'K', 2018 => 'J', 2019 => 'I', 2020 => 'H', 2021 => 'G', 2022 => 'F', 2023 => 'E', 2024 => 'D'];

        return [$columns[$year]];
    }

    private function num(?string $value): ?int
    {
        if ($value === null || trim($value) === '' || ! is_numeric(str_replace([',', ' '], '', trim($value)))) {
            return null;
        }

        return (int) round((float) str_replace([',', ' '], '', trim($value)));
    }

    /** @param  array<string, string>  $map */
    private function alias(array $map, string $name): string
    {
        $key = ArabicName::normalize($name);
        foreach ($map as $from => $to) {
            if (ArabicName::normalize($from) === $key) {
                return ArabicName::display($to);
            }
        }

        return ArabicName::display($name);
    }

    /** @param  array<string, string>  $map
     * @return array<string, string>
     */
    private function normalizedMap(array $map): array
    {
        $out = [];
        foreach ($map as $name => $value) {
            $out[ArabicName::normalize($name)] = $value;
        }

        return $out;
    }

    private function sheet(XlsxReader $reader, string $needle): string
    {
        $needle = ArabicName::normalize($needle);
        foreach ($reader->sheetNames() as $name) {
            $normalized = ArabicName::normalize($name);
            if ($needle === 'المشاريع' && str_contains($normalized, 'مشاريع') && str_contains($normalized, 'توتال')) {
                return $name;
            }
            if ($needle === 'مراكز' && str_contains($normalized, 'مراكز')) {
                return $name;
            }
            if (str_contains($normalized, $needle)) {
                return $name;
            }
        }

        throw new RuntimeException("ورقة غير موجودة: {$needle}");
    }

    /**
     * One place keeps a single slug even when the center sheet repeats it on every project row.
     *
     * @param  array<string, array{name: string, region: string, sort: int}>  $places
     */
    private function placeSlug(array &$places, string $name, string $region): string
    {
        $wanted = ArabicName::normalize($name);
        foreach ($places as $slug => $place) {
            if (ArabicName::normalize($place['name']) === $wanted) {
                return $slug;
            }
        }

        $slug = $this->slug($name, array_keys($places));
        $places[$slug] = ['name' => $name, 'region' => $region, 'sort' => count($places)];

        return $slug;
    }

    /** @param  list<string>  $taken */
    private function slug(string $name, array $taken): string
    {
        $map = [
            'ا' => 'a', 'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h', 'خ' => 'kh',
            'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh', 'ص' => 's',
            'ض' => 'd', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q',
            'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h', 'و' => 'w', 'ي' => 'y',
        ];
        $slug = '';
        foreach (preg_split('//u', ArabicName::normalize($name), -1, PREG_SPLIT_NO_EMPTY) as $char) {
            if ($char === ' ' || $char === '-') {
                $slug .= '-';
            } elseif (isset($map[$char])) {
                $slug .= $map[$char];
            } elseif (preg_match('/[a-z0-9]/', $char)) {
                $slug .= $char;
            }
        }
        $base = trim(preg_replace('/-+/', '-', $slug) ?? '', '-');
        $base = Str::limit($base !== '' ? $base : 'item', 80, '');
        $candidate = $base;
        $i = 2;
        while (in_array($candidate, $taken, true)) {
            $candidate = "{$base}-{$i}";
            $i++;
        }

        return $candidate;
    }
}
