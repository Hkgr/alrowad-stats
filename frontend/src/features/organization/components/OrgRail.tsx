import { ChevronDown, RotateCcw, SlidersHorizontal, X } from 'lucide-react'
import { useState, type ReactNode } from 'react'
import { SelectField } from '../../../components/ui/SelectField'
import type { OrgDashboard, OrgFilters } from '../types'

function Group({ title, hint, children }: { title: string; hint: string; children: ReactNode }) {
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

interface Props {
  filters: OrgFilters
  data: OrgDashboard | undefined
  busy: boolean
  hasActiveFilters: boolean
  onYear: (year: string | null) => void
  onRange: (changes: Partial<OrgFilters>) => void
  onProgram: (slug: string | null) => void
  onProject: (slug: string | null, programSlug?: string | null) => void
  onRegion: (slug: string | null) => void
  onCenter: (slug: string | null, regionSlug?: string | null) => void
  onReset: () => void
}

export function OrgRail(props: Props) {
  const { filters, data, busy, hasActiveFilters } = props
  const [open, setOpen] = useState(false)
  const options = data?.options
  const years = (options?.years ?? []).map((year) => ({ value: String(year), label: String(year) }))
  const chips: { key: string; label: string; onRemove: () => void }[] = []
  if (data?.scope.program && filters.program) chips.push({ key: 'program', label: data.scope.program.name, onRemove: () => props.onProgram(null) })
  if (data?.scope.project) chips.push({ key: 'project', label: data.scope.project.name, onRemove: () => props.onProject(null) })
  if (data?.scope.region && filters.region) chips.push({ key: 'region', label: data.scope.region.name, onRemove: () => props.onRegion(null) })
  if (data?.scope.center) chips.push({ key: 'center', label: data.scope.center.name, onRemove: () => props.onCenter(null) })
  if (filters.year) chips.push({ key: 'year', label: filters.year, onRemove: () => props.onYear(null) })
  if (filters.from || filters.to) {
    const label = filters.from && filters.to ? `${filters.from} – ${filters.to}` : filters.from ? `من ${filters.from}` : `حتى ${filters.to}`
    chips.push({ key: 'range', label, onRemove: () => props.onRange({ from: null, to: null }) })
  }

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
        {chips.length > 0 && <span className="num rounded-full bg-brand-soft px-2 py-0.5 text-xs font-bold text-brand-ink">{chips.length}</span>}
        <ChevronDown className={`ms-auto size-4 text-ink-muted transition-transform ${open ? 'rotate-180' : ''}`} aria-hidden="true" />
      </button>

      <div className={`${open ? 'mt-3' : 'hidden'} overflow-hidden rounded-2xl border border-line bg-white shadow-card lg:mt-0 lg:block lg:rounded-none lg:border-0 lg:bg-transparent lg:shadow-none`}>
        <div className="flex items-center justify-between gap-2 px-3.5 py-3">
          <h2 className="text-sm font-extrabold text-ink">
            التصفية
            {chips.length > 0 && <span className="num ms-1.5 text-ink-muted">{chips.length}</span>}
          </h2>
          {busy && <span className="text-xs font-semibold text-ink-muted">جارٍ التحديث…</span>}
          {hasActiveFilters && (
            <button type="button" onClick={props.onReset} className="inline-flex min-h-8 cursor-pointer items-center gap-1.5 rounded-full px-2.5 text-sm font-bold text-brand-ink hover:bg-brand-soft">
              <RotateCcw className="size-3.5" aria-hidden="true" />
              إعادة الضبط
            </button>
          )}
        </div>

        {chips.length > 0 && (
          <div className="flex flex-wrap gap-1.5 px-3.5 pb-3">
            {chips.map((chip) => (
              <span key={chip.key} className="inline-flex max-w-full items-center gap-1 rounded-full border bg-white py-0.5 ps-2.5 pe-0.5 text-xs font-semibold text-ink" style={{ borderColor: 'color-mix(in srgb, var(--t-primary) 45%, transparent)' }}>
                <span className="truncate">{chip.label}</span>
                <button type="button" onClick={chip.onRemove} aria-label={`إزالة: ${chip.label}`} className="grid size-6 shrink-0 cursor-pointer place-items-center rounded-full text-ink-soft hover:bg-paper-deep hover:text-ink">
                  <X className="size-3.5" aria-hidden="true" />
                </button>
              </span>
            ))}
          </div>
        )}

        <div className="divide-y divide-line border-t border-line">
        <Group title="السنوات" hint="سنة واحدة، أو من سنة إلى سنة">
          <SelectField variant="bar" label="السنة" value={filters.year ?? ''} options={years} allLabel="كل السنوات" onChange={(value) => props.onYear(value || null)} />
          <div className="grid grid-cols-2 gap-2">
            <SelectField
              variant="bar"
              label="من سنة"
              value={filters.from ?? ''}
              options={years}
              allLabel="من أول سنة"
              onChange={(value) => props.onRange({ from: value || null, year: null, ...(value && filters.to && value > filters.to ? { to: null } : {}) })}
            />
            <SelectField
              variant="bar"
              label="إلى سنة"
              value={filters.to ?? ''}
              options={years}
              allLabel="إلى آخر سنة"
              onChange={(value) => props.onRange({ to: value || null, year: null, ...(value && filters.from && value < filters.from ? { from: null } : {}) })}
            />
          </div>
        </Group>

        <Group title="البرنامج والمشروع" hint="المشروع يتبع البرنامج المختار">
          <SelectField
            variant="bar"
            label="البرنامج"
            value={filters.program ?? ''}
            options={(options?.programs ?? []).map((row) => ({ value: row.slug, label: row.name }))}
            allLabel="كل البرامج"
            onChange={(value) => props.onProgram(value || null)}
          />
          <SelectField
            variant="bar"
            label="المشروع"
            value={filters.project ?? ''}
            options={(options?.projects ?? []).map((row) => ({ value: row.slug, label: row.name }))}
            allLabel={filters.program ? 'كل مشاريع البرنامج' : 'كل المشاريع'}
            onChange={(value) => {
              const chosen = options?.projects.find((row) => row.slug === value)
              props.onProject(value || null, chosen?.program_slug)
            }}
          />
        </Group>

        <Group title="المنطقة والمركز" hint="المركز يتبع المنطقة المختارة">
          <SelectField
            variant="bar"
            label="المنطقة"
            value={filters.region ?? ''}
            options={(options?.regions ?? []).map((row) => ({ value: row.slug, label: row.name }))}
            allLabel="كل المناطق"
            onChange={(value) => props.onRegion(value || null)}
          />
          <SelectField
            variant="bar"
            label="المركز"
            value={filters.center ?? ''}
            options={(options?.centers ?? []).map((row) => ({ value: row.slug, label: row.name }))}
            allLabel={filters.region ? 'كل مراكز المنطقة' : 'كل المراكز'}
            onChange={(value) => {
              const chosen = options?.centers.find((row) => row.slug === value)
              props.onCenter(value || null, chosen?.region_slug)
            }}
          />
        </Group>
        </div>
      </div>
    </aside>
  )
}
