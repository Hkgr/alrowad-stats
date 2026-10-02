import { ChevronLeft, RotateCcw, X } from 'lucide-react'
import { SelectField, type SelectOption } from '../../../components/ui/SelectField'
import type { Dashboard, FilterOptions, FilterState } from '../../../types/api'

interface Props {
  filters: FilterState
  dashboard: Dashboard | undefined
  options: FilterOptions | undefined
  onOverview: () => void
  onSector: (slug: string | null) => void
  onProject: (slug: string | null, sectorSlug: string | null) => void
  onMainActivity: (slug: string | null) => void
  onSubActivity: (slug: string | null) => void
  onPeriod: (key: string | null) => void
  onOffice: (slug: string | null) => void
  onReset: () => void
  hasActiveFilters: boolean
  busy: boolean
}

/** Keeps the current value selectable even when the narrowed options no longer list it. */
function withCurrent(list: SelectOption[], current: { value: string; label: string } | null): SelectOption[] {
  return current && !list.some((o) => o.value === current.value) ? [...list, current] : list
}

/**
 * Where am I + what is applied: breadcrumb (overview › track › project › main › sub activity),
 * the small filters (period, office, main activity, sub activity), removable chips and one reset.
 */
export function ExplorerBar(props: Props) {
  const { filters, dashboard, options, hasActiveFilters, busy } = props
  const sector = dashboard?.active_sector ?? dashboard?.filters.sector ?? null
  const project = dashboard?.filters.project ?? null
  const main = dashboard?.filters.main_activity ?? null
  const sub = dashboard?.filters.sub_activity ?? null
  const period = dashboard?.filters.period ?? null
  const office = dashboard?.filters.office ?? null

  const periodOptions = withCurrent((options?.periods ?? []).map((p) => ({ value: p.key, label: p.label })), period && { value: period.key, label: period.label })
  const officeOptions = withCurrent((options?.offices ?? []).map((o) => ({ value: o.slug, label: o.name })), office && { value: office.slug, label: office.name })
  const mainOptions = withCurrent(
    (options?.main_activities ?? []).map((a) => ({ value: a.slug, label: a.has_data ? a.name : `${a.name} (لا بيانات)` })),
    main && { value: main.slug, label: main.name },
  )
  const subOptions = withCurrent(
    (options?.sub_activities ?? []).map((a) => ({ value: a.slug, label: a.has_data ? a.name : `${a.name} (لا بيانات)` })),
    sub && { value: sub.slug, label: sub.name },
  )

  const crumb = 'inline-flex min-h-9 cursor-pointer items-center rounded-lg px-2 text-sm font-semibold text-ink-soft hover:bg-paper-deep hover:text-ink'
  const current = 'truncate px-2 text-sm font-extrabold text-ink'
  const trail: { key: string; label: string; go?: () => void }[] = [
    { key: 'overview', label: 'النظرة العامة', go: props.onOverview },
  ]
  if (sector) trail.push({ key: 'sector', label: sector.name, go: () => props.onSector(sector.slug) })
  if (project) trail.push({ key: 'project', label: project.name, go: () => props.onProject(project.slug, filters.sector) })
  if (main) trail.push({ key: 'main', label: main.name, go: () => props.onMainActivity(main.slug) })
  if (sub) trail.push({ key: 'sub', label: sub.name })

  const chips: { key: string; label: string; onRemove: () => void }[] = []
  if (sector && filters.sector) chips.push({ key: 'sector', label: sector.name, onRemove: () => props.onSector(null) })
  if (project) chips.push({ key: 'project', label: project.name, onRemove: () => props.onProject(null, filters.sector) })
  if (main) chips.push({ key: 'main', label: `نشاط رئيسي: ${main.name}`, onRemove: () => props.onMainActivity(null) })
  if (sub) chips.push({ key: 'sub', label: `نشاط فرعي: ${sub.name}`, onRemove: () => props.onSubActivity(null) })
  if (period) chips.push({ key: 'period', label: period.label, onRemove: () => props.onPeriod(null) })
  if (office) chips.push({ key: 'office', label: `مكتب ${office.name}`, onRemove: () => props.onOffice(null) })

  return (
    <div className="space-y-3">
      <nav aria-label="مسار التصفح" className="min-w-0">
        <ol className="flex flex-wrap items-center gap-0.5">
          {trail.map((item, index) => {
            const last = index === trail.length - 1
            return (
              <li key={item.key} className="flex min-w-0 items-center gap-0.5">
                {index > 0 && <ChevronLeft className="size-4 shrink-0 text-ink-muted" aria-hidden="true" />}
                {last || !item.go ? (
                  <span aria-current="page" className={`${current} max-w-[18rem]`}>
                    {item.label}
                  </span>
                ) : (
                  <button type="button" onClick={item.go} className={`${crumb} max-w-[16rem] truncate`}>
                    {item.label}
                  </button>
                )}
              </li>
            )
          })}
        </ol>
      </nav>

      <div className="grid grid-cols-2 gap-2 md:grid-cols-4" role="group" aria-label="الفلاتر">
        <SelectField label="الفترة" value={filters.period ?? ''} options={periodOptions} allLabel="كل الفترات" onChange={(v) => props.onPeriod(v || null)} />
        <SelectField label="المكتب" value={filters.office ?? ''} options={officeOptions} allLabel="كل المكاتب" onChange={(v) => props.onOffice(v || null)} />
        <SelectField
          label="النشاط الرئيسي"
          value={filters.main_activity ?? ''}
          options={mainOptions}
          allLabel={project ? (mainOptions.length ? 'كل الأنشطة الرئيسية' : 'لا تتوفر في المصدر') : 'بعد اختيار مشروع'}
          disabled={!project || mainOptions.length === 0}
          onChange={(v) => props.onMainActivity(v || null)}
        />
        <SelectField
          label="النشاط الفرعي"
          value={filters.sub_activity ?? ''}
          options={subOptions}
          allLabel={main ? (subOptions.length ? 'كل الأنشطة الفرعية' : 'لا تتوفر في المصدر') : 'بعد اختيار نشاط رئيسي'}
          disabled={!main || subOptions.length === 0}
          onChange={(v) => props.onSubActivity(v || null)}
        />
      </div>

      {(chips.length > 0 || busy) && (
        <div className="flex flex-wrap items-center gap-2" aria-live="polite">
          {chips.map((chip) => (
            <span
              key={chip.key}
              className="inline-flex max-w-full items-center gap-1 rounded-full border bg-white py-0.5 ps-3 pe-0.5 text-sm font-semibold text-ink"
              style={{ borderColor: 'color-mix(in srgb, var(--t-primary) 45%, transparent)' }}
            >
              <span className="truncate">{chip.label}</span>
              <button
                type="button"
                onClick={chip.onRemove}
                aria-label={`إزالة: ${chip.label}`}
                className="grid size-7 shrink-0 cursor-pointer place-items-center rounded-full text-ink-soft hover:bg-paper-deep hover:text-ink"
              >
                <X className="size-3.5" aria-hidden="true" />
              </button>
            </span>
          ))}
          {hasActiveFilters && (
            <button
              type="button"
              onClick={props.onReset}
              className="inline-flex min-h-8 cursor-pointer items-center gap-1.5 rounded-full px-3 text-sm font-bold text-brand-ink hover:bg-brand-soft"
            >
              <RotateCcw className="size-3.5" aria-hidden="true" />
              إعادة الضبط
            </button>
          )}
          {busy && <span className="text-xs font-semibold text-ink-muted">جارٍ التحديث…</span>}
        </div>
      )}
    </div>
  )
}
