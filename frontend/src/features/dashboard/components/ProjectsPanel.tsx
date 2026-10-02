import { ChevronDown, ChevronLeft, Search } from 'lucide-react'
import { lazy, Suspense, useMemo, useState } from 'react'
import { Skeleton } from '../../../components/ui/Skeleton'
import { countLabel, PROJECTS } from '../../../lib/arabic'
import { formatNumber, normalizeArabic } from '../../../lib/format'
import type { ProjectRow } from '../../../types/api'

// Loaded on demand so ECharts stays out of the main bundle.
const ComparisonBarChart = lazy(() => import('./ComparisonBarChart').then((m) => ({ default: m.ComparisonBarChart })))

interface Props {
  projects: ProjectRow[]
  /** Show each project's track (overview); hidden inside a single track. */
  showSector: boolean
  onOpen: (project: ProjectRow) => void
}

/**
 * Projects of the current scope. Every classified project is listed, with or without figures;
 * the comparison chart only appears when at least two projects have data.
 */
const INITIAL_COUNT = 12

export function ProjectsPanel({ projects, showSector, onOpen }: Props) {
  const [query, setQuery] = useState('')
  const [expanded, setExpanded] = useState(false)
  const withData = projects.filter((p) => p.has_data)

  const visible = useMemo(() => {
    const needle = normalizeArabic(query)
    return needle ? projects.filter((p) => normalizeArabic(`${p.name} ${p.sector?.name ?? ''}`).includes(needle)) : projects
  }, [projects, query])

  // Projects with data come first (API order), so the short list always shows them.
  const shown = expanded || query ? visible : visible.slice(0, INITIAL_COUNT)

  return (
    <div className="space-y-4">
      {withData.length >= 2 && (
        <Suspense fallback={<Skeleton className="h-48 w-full" />}>
          <ComparisonBarChart
            ariaLabel={`مقارنة الاستفادات المسجلة بين ${countLabel(withData.length, PROJECTS)}`}
            items={withData.map((p) => ({
              key: p.slug,
              name: p.name,
              value: p.total ?? 0,
              male: p.male ?? 0,
              female: p.female ?? 0,
            }))}
            onSelect={(slug) => {
              const project = projects.find((p) => p.slug === slug)
              if (project) onOpen(project)
            }}
          />
        </Suspense>
      )}

      <div className="flex flex-wrap items-center justify-between gap-3">
        <div className="relative w-full sm:max-w-xs">
          <label htmlFor="projects-filter" className="sr-only">
            تصفية قائمة المشاريع
          </label>
          <Search aria-hidden="true" className="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-muted" />
          <input
            id="projects-filter"
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="تصفية المشاريع…"
            autoComplete="off"
            className="h-10 w-full rounded-xl border border-line-strong bg-white ps-10 pe-3 text-sm font-semibold text-ink placeholder:font-normal placeholder:text-ink-muted hover:border-ink-muted"
          />
        </div>
        <p className="text-sm text-ink-muted" aria-live="polite">
          لديها بيانات: <span className="num font-bold text-ink">{formatNumber(withData.length)}</span> من{' '}
          <span className="num font-bold text-ink">{formatNumber(projects.length)}</span>
        </p>
      </div>

      {visible.length === 0 ? (
        <p className="rounded-xl bg-paper px-4 py-8 text-center text-sm text-ink-muted">لا يوجد مشروع مطابق.</p>
      ) : (
        <ul className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
          {shown.map((project) => (
            <li key={project.slug}>
              <button
                type="button"
                onClick={() => onOpen(project)}
                className={`group flex h-full w-full cursor-pointer items-center gap-3 rounded-xl border px-3.5 py-3 text-start transition-colors hover:border-brand/50 hover:bg-brand-wash ${
                  project.has_data ? 'border-line bg-white' : 'border-dashed border-line-strong bg-paper/60'
                }`}
              >
                <span className="min-w-0 flex-1">
                  <span className="block text-sm font-bold leading-snug text-ink">{project.name}</span>
                  {showSector && <span className="mt-0.5 block truncate text-xs text-ink-muted">{project.sector?.name ?? 'غير مصنف'}</span>}
                </span>
                <span className="shrink-0 text-end">
                  {project.has_data ? (
                    <>
                      <span className="num block text-lg font-extrabold leading-none text-ink">{formatNumber(project.total)}</span>
                      <span className="block text-[11px] text-ink-muted">استفادة مسجلة</span>
                    </>
                  ) : (
                    <span className="block text-xs font-semibold text-ink-muted">لا توجد بيانات</span>
                  )}
                </span>
                <ChevronLeft className="size-4 shrink-0 text-ink-muted transition-transform group-hover:-translate-x-0.5" aria-hidden="true" />
              </button>
            </li>
          ))}
        </ul>
      )}

      {shown.length < visible.length && (
        <div className="flex justify-center">
          <button
            type="button"
            onClick={() => setExpanded(true)}
            className="inline-flex min-h-10 cursor-pointer items-center gap-1.5 rounded-xl border border-line-strong bg-white px-4 text-sm font-bold text-ink hover:border-brand hover:text-brand-ink"
          >
            {`عرض كل المشاريع (${formatNumber(visible.length)})`}
            <ChevronDown className="size-4" aria-hidden="true" />
          </button>
        </div>
      )}
    </div>
  )
}
