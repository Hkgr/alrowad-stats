<?php

namespace App\Http\Requests\Api;

use App\Models\Institution;
use App\Models\Office;
use App\Models\Period;
use App\Models\Project;
use App\Services\Dashboard\DashboardFilters;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Query-string filters shared by /filters and /dashboard.
 *
 * `institution` is mandatory; project, period and office are accepted only when they belong
 * to that institution, otherwise the request is rejected with a 422.
 */
class InstitutionFiltersRequest extends FormRequest
{
    private ?Institution $institution = null;

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
            'project' => ['bail', 'nullable', 'string', 'max:96', Rule::exists('projects', 'slug')->where('institution_id', $institutionId)],
            'office' => ['bail', 'nullable', 'string', 'max:96', Rule::exists('offices', 'slug')->where('institution_id', $institutionId)],
            'period' => [
                'bail', 'nullable', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/',
                function (string $attribute, mixed $value, Closure $fail) use ($institutionId) {
                    [$year, $month] = Period::parseKey($value);
                    $exists = Period::where('institution_id', $institutionId)->where('year', $year)->where('month', $month)->exists();
                    if (! $exists) {
                        $fail('الفترة المحددة غير موجودة لهذه المؤسسة.');
                    }
                },
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'institution.required' => 'يجب تحديد المؤسسة.',
            'institution.exists' => 'المؤسسة المحددة غير موجودة.',
            'project.exists' => 'المشروع المحدد لا ينتمي إلى هذه المؤسسة.',
            'office.exists' => 'المكتب المحدد لا ينتمي إلى هذه المؤسسة.',
            'period.regex' => 'صيغة الفترة يجب أن تكون YYYY-MM مثل 2026-05.',
        ];
    }

    /** Resolves the validated filters into models. Call only after validation passed. */
    public function filters(): DashboardFilters
    {
        $institution = $this->resolveInstitution();
        $validated = $this->validated();

        $period = null;
        if (! empty($validated['period'])) {
            [$year, $month] = Period::parseKey($validated['period']);
            $period = Period::where('institution_id', $institution->id)->where('year', $year)->where('month', $month)->first();
        }

        return new DashboardFilters(
            institution: $institution,
            project: empty($validated['project']) ? null
                : Project::where('institution_id', $institution->id)->where('slug', $validated['project'])->first(),
            period: $period,
            office: empty($validated['office']) ? null
                : Office::where('institution_id', $institution->id)->where('slug', $validated['office'])->first(),
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
