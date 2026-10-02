<?php

namespace App\Http\Requests\Api;

use App\Models\Institution;
use App\Models\MainActivity;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSectorAssignment;
use App\Models\Sector;
use App\Models\SubActivity;
use App\Services\Dashboard\BeneficiaryDashboardService;
use App\Services\Dashboard\DashboardFilters;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Query-string filters shared by /filters and /dashboard.
 *
 * `institution` is mandatory; sector (or "unclassified"), project, main_activity, sub_activity,
 * period and office are accepted only when they belong to that institution, the main activity to
 * the project and the sub activity to the main activity. When both sector and project are given, the project must be
 * classified in that sector for the classification year. Anything else is a 422.
 */
class InstitutionFiltersRequest extends FormRequest
{
    private ?Institution $institution = null;

    private ?DashboardFilters $resolved = null;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $institutionId = $this->resolveInstitution()?->id ?? 0;

        return [
            'institution' => ['bail', 'required', 'string', 'max:64', Rule::exists('institutions', 'slug')->where('is_active', true)],
            'sector' => ['bail', 'nullable', 'string', 'max:64', function (string $attribute, mixed $value, Closure $fail) use ($institutionId) {
                if ($value !== DashboardFilters::UNCLASSIFIED && ! Sector::where('institution_id', $institutionId)->where('slug', $value)->exists()) {
                    $fail('المسار المحدد لا ينتمي إلى هذه المؤسسة.');
                }
            }],
            'main_activity' => ['bail', 'nullable', 'string', 'max:120'],
            'sub_activity' => ['bail', 'nullable', 'string', 'max:120'],
            'project' => ['bail', 'nullable', 'string', 'max:96', Rule::exists('projects', 'slug')->where('institution_id', $institutionId)],
            'office' => ['bail', 'nullable', 'string', 'max:96', Rule::exists('offices', 'slug')->where('institution_id', $institutionId)],
            'period' => [
                'bail', 'nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                function (string $attribute, mixed $value, Closure $fail) use ($institutionId) {
                    [$year, $month] = Period::parseKey($value);
                    if (! Period::where('institution_id', $institutionId)->where('year', $year)->where('month', $month)->exists()) {
                        $fail('الفترة المحددة غير موجودة لهذه المؤسسة.');
                    }
                },
            ],
        ];
    }

    /** @return array<int, Closure> */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }
                $input = fn (string $key) => ($v = $this->query($key)) === null || $v === '' ? null : (string) $v;
                if ($input('main_activity') !== null && $input('project') === null) {
                    $validator->errors()->add('main_activity', 'حدّد المشروع قبل النشاط الرئيسي.');

                    return;
                }
                if ($input('sub_activity') !== null && $input('main_activity') === null) {
                    $validator->errors()->add('sub_activity', 'حدّد النشاط الرئيسي قبل النشاط الفرعي.');

                    return;
                }

                $filters = $this->filters();
                if ($input('main_activity') !== null && $filters->mainActivity === null) {
                    $validator->errors()->add('main_activity', 'النشاط الرئيسي المحدد لا يتبع هذا المشروع.');

                    return;
                }
                if ($input('sub_activity') !== null && $filters->subActivity === null) {
                    $validator->errors()->add('sub_activity', 'النشاط الفرعي المحدد لا يتبع هذا النشاط الرئيسي.');

                    return;
                }

                if ($filters->sector && $filters->project) {
                    $belongs = ProjectSectorAssignment::where('project_id', $filters->project->id)
                        ->where('sector_id', $filters->sector->id)
                        ->where('reference_year', $filters->classificationYear)
                        ->exists();
                    if (! $belongs) {
                        $validator->errors()->add('project', 'المشروع المحدد لا يتبع هذا المسار.');
                    }
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'institution.required' => 'يجب تحديد المؤسسة.',
            'institution.exists' => 'المؤسسة المحددة غير موجودة.',
            'sector.exists' => 'المسار المحدد لا ينتمي إلى هذه المؤسسة.',
            'project.exists' => 'المشروع المحدد لا ينتمي إلى هذه المؤسسة.',
            'office.exists' => 'المكتب المحدد لا ينتمي إلى هذه المؤسسة.',
            'period.regex' => 'صيغة الفترة يجب أن تكون YYYY-MM مثل 2026-05.',
        ];
    }

    /** Resolves the validated filters into models. Call only after the rules passed. */
    public function filters(): DashboardFilters
    {
        if ($this->resolved) {
            return $this->resolved;
        }

        $institution = $this->resolveInstitution();
        $input = fn (string $key) => ($v = $this->query($key)) === null || $v === '' ? null : (string) $v;

        $period = null;
        if ($key = $input('period')) {
            [$year, $month] = Period::parseKey($key);
            $period = Period::where('institution_id', $institution->id)->where('year', $year)->where('month', $month)->first();
        }

        $scoped = fn (string $model, ?string $slug) => $slug === null ? null
            : $model::where('institution_id', $institution->id)->where('slug', $slug)->first();

        $sectorSlug = $input('sector');
        $project = $scoped(Project::class, $input('project'));
        // Main activity slugs are unique inside their project, sub activity slugs inside their main.
        $main = $project && $input('main_activity') !== null
            ? MainActivity::where('project_id', $project->id)->where('slug', $input('main_activity'))->first() : null;
        $sub = $main && $input('sub_activity') !== null
            ? SubActivity::where('main_activity_id', $main->id)->where('slug', $input('sub_activity'))->first() : null;

        return $this->resolved = new DashboardFilters(
            institution: $institution,
            sector: $sectorSlug === DashboardFilters::UNCLASSIFIED ? null : $scoped(Sector::class, $sectorSlug),
            project: $project,
            period: $period,
            office: $scoped(Office::class, $input('office')),
            classificationYear: $period?->year ?? BeneficiaryDashboardService::latestClassificationYear($institution->id),
            unclassified: $sectorSlug === DashboardFilters::UNCLASSIFIED,
            mainActivity: $main,
            subActivity: $sub,
        );
    }

    private function resolveInstitution(): ?Institution
    {
        $slug = $this->query('institution');

        if (! is_string($slug) || $slug === '') {
            return null;
        }

        return $this->institution ??= Institution::where('slug', $slug)->where('is_active', true)->first();
    }
}
