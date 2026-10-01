import { Filter, RotateCcw, X } from 'lucide-react'
import { SelectField, type SelectOption } from '../../../components/ui/SelectField'
import type { Dashboard, FilterKey, FilterOptions, FilterState, Institution } from '../../../types/api'

interface FilterBarProps {
  institutions: Institution[]
  options: FilterOptions | undefined
  dashboard: Dashboard | undefined
  filters: FilterState
  onChange: (key: FilterKey, value: string | null) => void
  onReset: () => void
  hasActiveFilters: boolean
  busy: boolean
}

/** Keeps the currently selected value selectable even when it has no data in the narrowed options. */
function withCurrent(list: SelectOption[], current: SelectOption | null): SelectOption[] {
  if (!current || list.some((item) => item.value === current.value)) return list
  return [...list, current]
}

export function FilterBar({ institutions, options, dashboard, filters, onChange, onReset, hasActiveFilters, busy }: FilterBarProps) {
  const applied = dashboard?.filters

  const projects = withCurrent(
    (options?.projects ?? []).map((p) => ({ value: p.slug, label: p.name })),
    applied?.project ? { value: applied.project.slug, label: applied.project.name } : null,
  )
  const periods = withCurrent(
    (options?.periods ?? []).map((p) => ({ value: p.key, label: p.label })),
    applied?.period ? { value: applied.period.key, label: applied.period.label } : null,
  )
  const offices = withCurrent(
    (options?.offices ?? []).map((o) => ({ value: o.slug, label: o.name })),
    applied?.office ? { value: applied.office.slug, label: applied.office.name } : null,
  )

  const chips: { key: FilterKey; label: string; value: string }[] = []
  const labelOf = (list: SelectOption[], value: string | null) => list.find((o) => o.value === value)?.label ?? value
  if (filters.project) chips.push({ key: 'project', label: 'المشروع', value: labelOf(projects, filters.project) ?? '' })
  if (filters.period) chips.push({ key: 'period', label: 'الفترة', value: labelOf(periods, filters.period) ?? '' })
  if (filters.office) chips.push({ key: 'office', label: 'المكتب', value: labelOf(offices, filters.office) ?? '' })

  return (
    <section aria-label="الفلاتر" className="rounded-2xl border border-line bg-surface p-4 shadow-card sm:p-5">
      <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <SelectField
          label="المؤسسة"
          value={filters.institution ?? ''}
          options={institutions.map((i) => ({ value: i.slug, label: i.name }))}
          onChange={(value) => onChange('institution', value)}
          disabled={institutions.length === 0}
        />
        <SelectField
          label="المشروع"
          value={filters.project ?? ''}
          options={projects}
          allLabel="كل المشاريع"
          onChange={(value) => onChange('project', value || null)}
        />
        <SelectField
          label="الفترة"
          value={filters.period ?? ''}
          options={periods}
          allLabel="كل الفترات"
          onChange={(value) => onChange('period', value || null)}
        />
        <SelectField
          label="المكتب"
          value={filters.office ?? ''}
          options={offices}
          allLabel="كل المكاتب"
          onChange={(value) => onChange('office', value || null)}
        />
      </div>

      <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-line pt-4" aria-live="polite">
        <span className="flex items-center gap-1.5 text-sm font-bold text-ink-muted">
          <Filter className="size-4" aria-hidden={true} />
          الفلاتر النشطة
        </span>

        {chips.length === 0 ? (
          <span className="text-sm text-ink-muted">لا توجد فلاتر نشطة — تُعرض كل البيانات المتاحة.</span>
        ) : (
          chips.map((chip) => (
            <span
              key={chip.key}
              className="inline-flex items-center gap-2 rounded-full border border-brand/40 bg-brand-soft py-1 ps-3.5 pe-1 text-sm font-semibold text-navy-900"
            >
              <span className="text-ink-muted">{chip.label}:</span>
              {chip.value}
              <button
                type="button"
                onClick={() => onChange(chip.key, null)}
                aria-label={`إزالة فلتر ${chip.label}: ${chip.value}`}
                className="grid size-7 cursor-pointer place-items-center rounded-full text-navy-800 hover:bg-brand/25"
              >
                <X className="size-4" aria-hidden={true} />
              </button>
            </span>
          ))
        )}

        {busy && <span className="text-xs font-semibold text-brand-strong">جارٍ التحديث…</span>}

        <button
          type="button"
          onClick={onReset}
          disabled={!hasActiveFilters}
          className="ms-auto inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg px-3 text-sm font-bold text-navy-700 hover:bg-navy-100 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent"
        >
          <RotateCcw className="size-4" aria-hidden={true} />
          إعادة ضبط
        </button>
      </div>
    </section>
  )
}
