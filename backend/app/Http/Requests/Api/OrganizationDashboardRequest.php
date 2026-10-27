<?php

namespace App\Http\Requests\Api;

use App\Models\Organization\OrgCenter;
use App\Models\Organization\OrgProject;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Filters for the organization dashboard. A year and a year range are exclusive.
 * A project has to belong to the chosen program, and a center to the chosen region.
 */
class OrganizationDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $empty = [];
        foreach (['program', 'project', 'region', 'center', 'year', 'from', 'to'] as $key) {
            if ($this->query($key) === '') {
                $empty[$key] = null;
            }
        }
        if ($empty !== []) {
            $this->merge($empty);
        }
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'program' => ['nullable', 'string', 'max:64', Rule::exists('org_programs', 'slug')],
            'project' => ['nullable', 'string', 'max:96', Rule::exists('org_projects', 'slug')],
            'region' => ['nullable', 'string', 'max:64', Rule::exists('org_regions', 'slug')],
            'center' => ['nullable', 'string', 'max:96', Rule::exists('org_centers', 'slug')],
            'year' => ['nullable', 'integer', 'between:1990,2100'],
            'from' => ['nullable', 'integer', 'between:1990,2100'],
            'to' => ['nullable', 'integer', 'between:1990,2100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'program.exists' => 'البرنامج المحدد غير موجود.',
            'project.exists' => 'المشروع المحدد غير موجود.',
            'region.exists' => 'المنطقة المحددة غير موجودة.',
            'center.exists' => 'المركز المحدد غير موجود.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $year = $this->filled('year');
            $range = $this->filled('from') || $this->filled('to');
            if ($year && $range) {
                $validator->errors()->add('year', 'اختر سنة واحدة أو مجالاً، لا الاثنين معاً.');
            }
            if ($this->filled('from') && $this->filled('to') && (int) $this->input('from') > (int) $this->input('to')) {
                $validator->errors()->add('from', 'بداية المجال بعد نهايته.');
            }

            if ($this->filled('program') && $this->filled('project')) {
                $belongs = OrgProject::query()
                    ->where('slug', $this->input('project'))
                    ->whereHas('program', fn ($query) => $query->where('slug', $this->input('program')))
                    ->exists();
                if (! $belongs) {
                    $validator->errors()->add('project', 'المشروع لا ينتمي إلى هذا البرنامج.');
                }
            }

            if ($this->filled('region') && $this->filled('center')) {
                $belongs = OrgCenter::query()
                    ->where('slug', $this->input('center'))
                    ->whereHas('region', fn ($query) => $query->where('slug', $this->input('region')))
                    ->exists();
                if (! $belongs) {
                    $validator->errors()->add('center', 'المركز لا ينتمي إلى هذه المنطقة.');
                }
            }
        });
    }

    /** @return array{program: ?string, project: ?string, region: ?string, center: ?string, year: ?int, from: ?int, to: ?int} */
    public function filters(): array
    {
        $data = $this->validated();

        return [
            'program' => $data['program'] ?? null,
            'project' => $data['project'] ?? null,
            'region' => $data['region'] ?? null,
            'center' => $data['center'] ?? null,
            'year' => isset($data['year']) ? (int) $data['year'] : null,
            'from' => isset($data['from']) ? (int) $data['from'] : null,
            'to' => isset($data['to']) ? (int) $data['to'] : null,
        ];
    }
}
