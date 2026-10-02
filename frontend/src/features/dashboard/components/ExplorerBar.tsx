import { ChevronLeft, RotateCcw, X } from 'lucide-react'
import { SelectField } from '../../../components/ui/SelectField'
import type { Dashboard, FilterOptions, FilterState } from '../../../types/api'

interface Props {
  filters: FilterState
  dashboard: Dashboard | undefined
  options: FilterOptions | undefined
  onOverview: () => void
  onSector: (slug: string | null) => void
  onPeriod: (key: string | null) => void
  onOffice: (slug: string | null) => void
  onClearProject: () => void
  onReset: () => void
  hasActiveFilters: boolean
  busy: boolean
}

/**
 * Where am I + what is applied: breadcrumb (overview › sector › project), the small time/office
 * filters, removable chips for every active choice and one reset.
 */
export function ExplorerBar({
  filters,
  dashboard,
  options,
  onOverview,
  onSector,
  onPeriod,
  onOffice,
  onClearProject,
  onReset,
  hasActiveFilters,
  busy,
}: Props) {
  const sector = dashboard?.active_sector ?? dashboard?.filters.sector ?? null
  const project = dashboard?.filters.project ?? null
  const period = dashboard?.filters.period ?? null
  const office = dashboard?.filters.office ?? null

  const periodOptions = (options?.periods ?? []).map((p) => ({ value: p.key, label: p.label }))
  if (period && !periodOptions.some((o) => o.value === period.key)) periodOptions.push({ value: period.key, label: period.label })
  const officeOptions = (options?.offices ?? []).map((o) => ({ value: o.slug, label: o.name }))
  if (office && !officeOptions.some((o) => o.value === office.slug)) officeOptions.push({ value: office.slug, label: office.name })

  const crumb = 'inline-flex min-h-9 cursor-pointer items-center rounded-lg px-2 text-sm font-semibold text-ink-soft hover:bg-paper-deep hover:text-ink'

  const chips: { key: string; label: string; onRemove: () => void }[] = []
  if (sector && filters.sector) chips.push({ key: 'sector', label: sector.name, onRemove: () => onSector(null) })
  if (project) chips.push({ key: 'project', label: project.name, onRemove: onClearProject })
  if (period) chips.push({ key: 'period', label: period.label, onRemove: () => onPeriod(null) })
  if (office) chips.push({ key: 'office', label: `مكتب ${office.name}`, onRemove: () => onOffice(null) })

  return (
    <div className="space-y-3">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <nav aria-label="مسار التصفح" className="min-w-0">
          <ol className="flex flex-wrap items-center gap-0.5">
            <li>
              {filters.sector || filters.project ? (
                <button type="button" onClick={onOverview} className={crumb}>
                  النظرة العامة
                </button>
              ) : (
                <span aria-current="page" className="px-2 text-sm font-extrabold text-ink">
                  النظرة العامة
                </span>
              )}
            </li>
            {sector && (
              <li className="flex min-w-0 items-center gap-0.5">
                <ChevronLeft className="size-4 shrink-0 text-ink-muted" aria-hidden="true" />
                {project ? (
                  <button type="button" onClick={() => onSector(sector.slug)} className={`${crumb} max-w-[16rem] truncate`}>
                    {sector.name}
                  </button>
                ) : (
                  <span aria-current="page" className="truncate px-2 text-sm font-extrabold text-ink">
                    {sector.name}
                  </span>
                )}
              </li>
            )}
            {project && (
              <li className="flex min-w-0 items-center gap-0.5">
                <ChevronLeft className="size-4 shrink-0 text-ink-muted" aria-hidden="true" />
                <span aria-current="page" className="truncate px-2 text-sm font-extrabold text-ink">
                  {project.name}
                </span>
              </li>
            )}
          </ol>
        </nav>

        <div className="flex w-full flex-wrap items-center gap-2 sm:w-auto" role="group" aria-label="الفترة والمكتب">
          <div className="min-w-0 flex-1 sm:w-48 sm:flex-none">
            <SelectField
              label="الفترة"
              value={filters.period ?? ''}
              options={periodOptions}
              allLabel="كل الفترات"
              onChange={(value) => onPeriod(value || null)}
            />
          </div>
          <div className="min-w-0 flex-1 sm:w-48 sm:flex-none">
            <SelectField
              label="المكتب"
              value={filters.office ?? ''}
              options={officeOptions}
              allLabel="كل المكاتب"
              onChange={(value) => onOffice(value || null)}
            />
          </div>
        </div>
      </div>

      {(chips.length > 0 || busy) && (
        <div className="flex flex-wrap items-center gap-2" aria-live="polite">
          {chips.map((chip) => (
            <span
              key={chip.key}
              className="inline-flex max-w-full items-center gap-1 rounded-full border border-brand/35 bg-brand-wash py-0.5 ps-3 pe-0.5 text-sm font-semibold text-ink"
            >
              <span className="truncate">{chip.label}</span>
              <button
                type="button"
                onClick={chip.onRemove}
                aria-label={`إزالة: ${chip.label}`}
                className="grid size-7 shrink-0 cursor-pointer place-items-center rounded-full text-ink-soft hover:bg-brand-soft hover:text-brand-ink"
              >
                <X className="size-3.5" aria-hidden="true" />
              </button>
            </span>
          ))}
          {hasActiveFilters && (
            <button
              type="button"
              onClick={onReset}
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
