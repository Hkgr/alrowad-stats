import { ChevronDown, ChevronLeft, RotateCcw, SlidersHorizontal, X } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { SelectField, type SelectOption } from '../../../components/ui/SelectField'
import { rangeLabel } from '../../../lib/format'
import { parseOffices, selectionChips } from '../../../lib/regions'
import type { Dashboard, FilterOptions, FilterState } from '../../../types/api'
import { RegionPicker } from './RegionPicker'

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
  /** Replaces the chosen offices (an empty list = all). */
  onOffices: (slugs: string[]) => void
  /** Year, gender, age group and special-needs filters (each change is one history entry). */
  onFilters: (changes: Partial<FilterState>) => void
  onReset: () => void
  hasActiveFilters: boolean
  busy: boolean
}

const GENDER_LABEL: Record<string, string> = { male: 'ذكور فقط', female: 'إناث فقط' }
const AGE_LABEL: Record<string, string> = { under_18: 'أقل من 18 سنة', adult: '18 سنة فأكثر' }

/** Keeps the current value selectable even when the narrowed options no longer list it. */
function withCurrent(list: SelectOption[], current: { value: string; label: string } | null): SelectOption[] {
  return current && !list.some((o) => o.value === current.value) ? [...list, current] : list
}

function useExplorer(props: Props) {
  const { filters, dashboard, options, hasActiveFilters, busy } = props
  const sector = dashboard?.active_sector ?? dashboard?.filters.sector ?? null
  const project = dashboard?.filters.project ?? null
  const main = dashboard?.filters.main_activity ?? null
  const sub = dashboard?.filters.sub_activity ?? null
  const period = dashboard?.filters.period ?? null
  const offices = parseOffices(filters.office)
  const officeNames = Object.fromEntries((dashboard?.filters.offices ?? []).map((o) => [o.slug, o.name]))
  const { gender, age, disabled } = filters
  const sectorHasData = new Map((dashboard?.sectors ?? []).map((row) => [row.slug, row.has_data]))
  const sectorOptions = withCurrent(
    (options?.sectors ?? []).map((row) => ({
      value: row.slug,
      label: sectorHasData.get(row.slug) === false ? `${row.name} (لا بيانات)` : row.name,
    })),
    sector && { value: sector.slug, label: sector.name },
  )
  const projectOptions = withCurrent(
    (options?.projects ?? [])
      .filter((row) => !filters.sector || row.sector?.slug === filters.sector)
      .map((row) => ({ value: row.slug, label: row.has_data ? row.name : `${row.name} (لا بيانات)` })),
    project && { value: project.slug, label: project.name },
  )
  const from = dashboard?.filters.from ?? null
  const to = dashboard?.filters.to ?? null

  const periodOptions = withCurrent((options?.periods ?? []).map((p) => ({ value: p.key, label: p.label })), period && { value: period.key, label: period.label })
  const yearOptions = withCurrent(
    (options?.years ?? []).map((y) => ({ value: String(y), label: String(y) })),
    filters.year ? { value: filters.year, label: filters.year } : null,
  )
  const fromOptions = withCurrent(periodOptions, from && { value: from.key, label: from.label })
  const toOptions = withCurrent(periodOptions, to && { value: to.key, label: to.label })
  const mainOptions = withCurrent(
    (options?.main_activities ?? []).map((a) => ({ value: a.slug, label: a.has_data ? a.name : `${a.name} (لا بيانات)` })),
    main && { value: main.slug, label: main.name },
  )
  const subOptions = withCurrent(
    (options?.sub_activities ?? []).map((a) => ({ value: a.slug, label: a.has_data ? a.name : `${a.name} (لا بيانات)` })),
    sub && { value: sub.slug, label: sub.name },
  )

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
  for (const chip of selectionChips(options?.regions ?? [], offices, officeNames)) {
    chips.push({ key: `office:${chip.key}`, label: chip.label, onRemove: () => props.onOffices(offices.filter((s) => !chip.slugs.includes(s))) })
  }
  if (from || to) chips.push({ key: 'range', label: rangeLabel(from?.label, to?.label) ?? '', onRemove: () => props.onFilters({ from: null, to: null }) })
  if (filters.year) chips.push({ key: 'year', label: `سنة ${filters.year}`, onRemove: () => props.onFilters({ year: null }) })
  if (gender) chips.push({ key: 'gender', label: GENDER_LABEL[gender] ?? gender, onRemove: () => props.onFilters({ gender: null }) })
  if (age) chips.push({ key: 'age', label: AGE_LABEL[age] ?? age, onRemove: () => props.onFilters({ age: null }) })
  if (disabled) chips.push({ key: 'disabled', label: 'ذوو الاحتياجات الخاصة', onRemove: () => props.onFilters({ disabled: null }) })

  return {
    filters, options, hasActiveFilters, busy, project, main, period, offices, officeNames, gender, age, disabled,
    periodOptions, yearOptions, fromOptions, toOptions, sectorOptions, projectOptions, mainOptions, subOptions, trail, chips,
  }
}

/** Where am I: overview › track › project › main › sub activity. Lives above the figures, not inside the filter rail. */
export function ScopeTrail(props: Props) {
  const { trail } = useExplorer(props)
  const crumb = 'inline-flex min-h-9 cursor-pointer items-center rounded-lg px-2 text-sm font-semibold text-ink-soft hover:bg-white hover:text-ink'
  const current = 'truncate px-2 text-sm font-extrabold text-ink'

  return (
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
  )
}

function FilterGroup({ title, hint, children }: { title: string; hint: string; children: ReactNode }) {
  return (
    <section className="px-3.5 py-3.5">
      <header className="mb-2.5">
        <h2 className="text-sm font-extrabold text-ink">{title}</h2>
        <p className="text-xs leading-5 text-ink-muted">{hint}</p>
      </header>
      <div className="space-y-2">{children}</div>
    </section>
  )
}

/**
 * Filters as a right-hand rail (the page is RTL, so this column is first).
 * Three bars: time, place and activity, then the demographic slice.
 * On a narrow screen the same bars collapse behind one button so the figures stay first.
 */
export function FilterRail(props: Props) {
  const model = useExplorer(props)
  const { filters, hasActiveFilters, busy, project, main, offices, officeNames, gender, age, disabled } = model
  const [open, setOpen] = useState(false)

  return (
    <aside aria-label="التصفية">
      <button
        type="button"
        className="flex w-full cursor-pointer items-center gap-2 rounded-2xl border border-line bg-white px-3 py-2.5 text-start shadow-card lg:hidden"
        aria-expanded={open}
        onClick={() => setOpen((value) => !value)}
      >
        <SlidersHorizontal className="size-4 text-brand-ink" aria-hidden="true" />
        <span className="font-extrabold text-ink">التصفية</span>
        {model.chips.length > 0 && (
          <span className="num rounded-full bg-brand-soft px-2 py-0.5 text-xs font-bold text-brand-ink">{model.chips.length}</span>
        )}
        <ChevronDown className={`ms-auto size-4 text-ink-muted transition-transform ${open ? 'rotate-180' : ''}`} aria-hidden="true" />
      </button>

      <div className={`${open ? 'mt-3' : 'hidden'} overflow-hidden rounded-2xl border border-line bg-white shadow-card lg:mt-0 lg:block lg:rounded-none lg:border-0 lg:bg-transparent lg:shadow-none`}>
        <div className="flex items-center justify-between gap-2 px-3.5 py-3">
          <h2 className="text-sm font-extrabold text-ink">
            التصفية
            {model.chips.length > 0 && <span className="num ms-1.5 text-ink-muted">{model.chips.length}</span>}
          </h2>
          {busy && <span className="text-xs font-semibold text-ink-muted">جارٍ التحديث…</span>}
          {hasActiveFilters && (
            <button
              type="button"
              onClick={props.onReset}
              className="inline-flex min-h-8 cursor-pointer items-center gap-1.5 rounded-full px-2.5 text-sm font-bold text-brand-ink hover:bg-brand-soft"
            >
              <RotateCcw className="size-3.5" aria-hidden="true" />
              إعادة الضبط
            </button>
          )}
        </div>

        {model.chips.length > 0 && (
          <div className="flex flex-wrap gap-1.5 px-3.5 pb-3" aria-live="polite">
            {model.chips.map((chip) => (
              <span
                key={chip.key}
                className="inline-flex max-w-full items-center gap-1 rounded-full border bg-white py-0.5 ps-2.5 pe-0.5 text-xs font-semibold text-ink"
                style={{ borderColor: 'color-mix(in srgb, var(--t-primary) 45%, transparent)' }}
              >
                <span className="truncate">{chip.label}</span>
                <button
                  type="button"
                  onClick={chip.onRemove}
                  aria-label={`إزالة: ${chip.label}`}
                  className="grid size-6 shrink-0 cursor-pointer place-items-center rounded-full text-ink-soft hover:bg-paper-deep hover:text-ink"
                >
                  <X className="size-3.5" aria-hidden="true" />
                </button>
              </span>
            ))}
          </div>
        )}

        <div className="divide-y divide-line border-t border-line">
        <FilterGroup title="الوقت" hint="سنة، أو شهر واحد، أو مجال من شهر إلى شهر">
          <div className="grid grid-cols-2 gap-2">
            <SelectField
              variant="bar"
              label="السنة"
              value={filters.year ?? ''}
              options={model.yearOptions}
              allLabel="كل السنوات"
              onChange={(v) =>
                props.onFilters({
                  year: v || null,
                  ...(v && filters.period && !filters.period.startsWith(v) ? { period: null } : {}),
                  ...(v && filters.from && !filters.from.startsWith(v) ? { from: null } : {}),
                  ...(v && filters.to && !filters.to.startsWith(v) ? { to: null } : {}),
                })
              }
            />
            <SelectField variant="bar" label="شهر واحد" value={filters.period ?? ''} options={model.periodOptions} allLabel="كل الشهور" onChange={(v) => props.onPeriod(v || null)} />
            <SelectField
              variant="bar"
              label="من شهر"
              value={filters.from ?? ''}
              options={model.fromOptions}
              allLabel="من أول شهر"
              onChange={(v) => props.onFilters({ from: v || null, period: null, ...(v && filters.to && v > filters.to ? { to: null } : {}) })}
            />
            <SelectField
              variant="bar"
              label="إلى شهر"
              value={filters.to ?? ''}
              options={model.toOptions}
              allLabel="إلى آخر شهر"
              onChange={(v) => props.onFilters({ to: v || null, period: null, ...(v && filters.from && v < filters.from ? { from: null } : {}) })}
            />
          </div>
        </FilterGroup>

        <FilterGroup title="المكان والنشاط" hint="المسار والمشروع، ثم النشاط بعد اختيار مشروع">
          <SelectField
            variant="bar"
            label="المسار"
            value={filters.sector ?? ''}
            options={model.sectorOptions}
            allLabel="كل المسارات"
            onChange={(v) => props.onSector(v || null)}
          />
          <SelectField
            variant="bar"
            label="المشروع"
            value={filters.project ?? ''}
            options={model.projectOptions}
            allLabel={filters.sector ? 'كل مشاريع المسار' : 'كل المشاريع'}
            disabled={model.projectOptions.length === 0}
            onChange={(v) => {
              const chosen = props.options?.projects.find((row) => row.slug === v)
              props.onProject(v || null, chosen?.sector?.slug ?? filters.sector)
            }}
          />
          <RegionPicker variant="bar" regions={model.options?.regions ?? []} selected={offices} names={officeNames} onChange={props.onOffices} />
          <SelectField
            variant="bar"
            label="النشاط الرئيسي"
            value={filters.main_activity ?? ''}
            options={model.mainOptions}
            allLabel={project ? (model.mainOptions.length ? 'كل الأنشطة الرئيسية' : 'لا تتوفر في المصدر') : 'بعد اختيار مشروع'}
            disabled={!project || model.mainOptions.length === 0}
            onChange={(v) => props.onMainActivity(v || null)}
          />
          <SelectField
            variant="bar"
            label="النشاط الفرعي"
            value={filters.sub_activity ?? ''}
            options={model.subOptions}
            allLabel={main ? (model.subOptions.length ? 'كل الأنشطة الفرعية' : 'لا تتوفر في المصدر') : 'بعد اختيار نشاط رئيسي'}
            disabled={!main || model.subOptions.length === 0}
            onChange={(v) => props.onSubActivity(v || null)}
          />
        </FilterGroup>

        <FilterGroup title="الفئة" hint="ذوو الاحتياجات لا يُجمعون مع الجنس أو العمر">
          <div className="grid grid-cols-2 gap-2">
            <SelectField
              variant="bar"
              label="الجنس"
              value={gender ?? ''}
              options={Object.entries(GENDER_LABEL).map(([value, label]) => ({ value, label }))}
              allLabel="الكل"
              disabled={Boolean(disabled)}
              onChange={(v) => props.onFilters({ gender: v || null })}
            />
            <SelectField
              variant="bar"
              label="العمر"
              value={age ?? ''}
              options={Object.entries(AGE_LABEL).map(([value, label]) => ({ value, label }))}
              allLabel="كل الأعمار"
              disabled={Boolean(disabled)}
              onChange={(v) => props.onFilters({ age: v || null })}
            />
          </div>
          <SelectField
            variant="bar"
            label="ذوو الاحتياجات"
            value={disabled ?? ''}
            options={[{ value: '1', label: 'المسجَّلون فقط' }]}
            allLabel="الكل"
            disabled={Boolean(gender || age)}
            onChange={(v) => props.onFilters({ disabled: v || null })}
          />
        </FilterGroup>
        </div>
      </div>
    </aside>
  )
}
