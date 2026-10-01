import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import type { FilterKey, FilterState } from '../../types/api'

const KEYS: FilterKey[] = ['institution', 'project', 'period', 'office']

/** Dependent filters that are dropped when their parent changes. */
const DEPENDENTS: Partial<Record<FilterKey, FilterKey[]>> = {
  institution: ['project', 'period', 'office'],
  project: ['period', 'office'],
}

/**
 * The single filter state for cards, charts and table. It lives in the URL query string,
 * so any view can be shared or bookmarked.
 */
export function useDashboardFilters() {
  const [params, setParams] = useSearchParams()

  const filters = useMemo<FilterState>(
    () => ({
      institution: params.get('institution'),
      project: params.get('project'),
      period: params.get('period'),
      office: params.get('office'),
    }),
    [params],
  )

  const update = useCallback(
    (changes: Partial<FilterState>, options: { replace?: boolean } = {}) => {
      setParams(
        (current) => {
          const next = new URLSearchParams(current)
          for (const key of KEYS) {
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

  const setFilter = useCallback(
    (key: FilterKey, value: string | null) => {
      const changes: Partial<FilterState> = { [key]: value }
      for (const dependent of DEPENDENTS[key] ?? []) changes[dependent] = null
      update(changes)
    },
    [update],
  )

  /** Clicking the already-selected office clears it, like deselecting in Power BI. */
  const toggleOffice = useCallback(
    (slug: string) => update({ office: filters.office === slug ? null : slug }),
    [filters.office, update],
  )

  /** Clears everything except the institution. */
  const reset = useCallback(() => update({ project: null, period: null, office: null }), [update])

  const hasActiveFilters = Boolean(filters.project || filters.period || filters.office)

  return { filters, setFilter, toggleOffice, reset, update, hasActiveFilters }
}
