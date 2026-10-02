import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import type { BreakdownView, FilterKey, FilterState } from '../../types/api'

const KEYS: FilterKey[] = ['institution', 'sector', 'project', 'period', 'office']
const VIEWS: BreakdownView[] = ['projects', 'offices', 'sectors']

type Changes = Partial<FilterState> & { view?: BreakdownView | null }

/**
 * The single exploration state (level, filters, tab). It lives in the URL query string, so a
 * view can be shared, and every navigation is a history entry, so the browser Back button
 * walks back through project → sector → overview.
 */
export function useDashboardFilters() {
  const [params, setParams] = useSearchParams()

  const filters = useMemo<FilterState>(
    () => ({
      institution: params.get('institution'),
      sector: params.get('sector'),
      project: params.get('project'),
      period: params.get('period'),
      office: params.get('office'),
    }),
    [params],
  )

  const rawView = params.get('view') as BreakdownView | null
  const view: BreakdownView | null = rawView && VIEWS.includes(rawView) ? rawView : null

  const update = useCallback(
    (changes: Changes, options: { replace?: boolean } = {}) => {
      setParams(
        (current) => {
          const next = new URLSearchParams(current)
          for (const key of [...KEYS, 'view'] as const) {
            if (!(key in changes)) continue
            const value = changes[key]
            if (value) next.set(key, value)
            else next.delete(key)
          }
          return next
        },
        { replace: options.replace ?? false },
      )
    },
    [setParams],
  )

  const goOverview = useCallback(() => update({ sector: null, project: null }), [update])
  const goSector = useCallback((slug: string | null) => update({ sector: slug, project: null }), [update])
  const goProject = useCallback(
    (slug: string, sectorSlug: string | null) => update({ project: slug, sector: sectorSlug }),
    [update],
  )
  const setPeriod = useCallback((key: string | null) => update({ period: key }), [update])
  const setView = useCallback((next: BreakdownView) => update({ view: next }, { replace: true }), [update])

  /** Clicking the already-selected office clears it, like deselecting in Power BI. */
  const toggleOffice = useCallback(
    (slug: string | null) => update({ office: slug && filters.office !== slug ? slug : null }),
    [filters.office, update],
  )

  /** Back to the institution overview with no filters (the institution itself is kept). */
  const reset = useCallback(
    () => update({ sector: null, project: null, period: null, office: null, view: null }),
    [update],
  )

  const hasActiveFilters = Boolean(filters.sector || filters.project || filters.period || filters.office)

  return { filters, view, update, goOverview, goSector, goProject, setPeriod, setView, toggleOffice, reset, hasActiveFilters }
}
