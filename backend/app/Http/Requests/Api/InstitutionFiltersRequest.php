<?php

namespace App\Http\Requests\Api;

use App\Models\Institution;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Models\ProjectSectorAssignment;
use App\Models\Sector;
use App\Services\Dashboard\BeneficiaryDashboardService;
use App\Services\Dashboard\DashboardFilters;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Query-string filters shared by /filters and /dashboard.
 *
 * `institution` is mandatory; sector, project, period and office are accepted only when they
 * belong to that institution. When both sector and project are given, the project must be
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
            'sector' => ['bail', 'nullable', 'string', 'max:64', Rule::exists('sectors', 'slug')->where('institution_id', $institutionId)],
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
                $filters = $this->filters();
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

        return $this->resolved = new DashboardFilters(
            institution: $institution,
            sector: $scoped(Sector::class, $input('sector')),
            project: $scoped(Project::class, $input('project')),
            period: $period,
            office: $scoped(Office::class, $input('office')),
            classificationYear: $period?->year ?? BeneficiaryDashboardService::latestClassificationYear($institution->id),
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
