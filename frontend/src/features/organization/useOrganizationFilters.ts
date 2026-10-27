import { useCallback, useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import type { OrgFilters } from './types'

const KEYS = ['program', 'project', 'region', 'center', 'year', 'from', 'to'] as const

/** Current organization filters as a query string, for the print report link. */
export function organizationQuery(filters: OrgFilters): string {
  const query = new URLSearchParams()
  for (const key of KEYS) {
    const value = filters[key]
    if (value) query.set(key, value)
  }
  return query.toString()
}

type Changes = Partial<OrgFilters>

/** Organization exploration state, stored in the URL so a view can be shared and the Back button walks it. */
export function useOrganizationFilters() {
  const [params, setParams] = useSearchParams()

  const filters = useMemo<OrgFilters>(
    () => ({
      program: params.get('program'),
      project: params.get('project'),
      region: params.get('region'),
      center: params.get('center'),
      year: params.get('year'),
      from: params.get('from'),
      to: params.get('to'),
    }),
    [params],
  )

  const update = useCallback(
    (changes: Changes) => {
      setParams((current) => {
        const next = new URLSearchParams(current)
        for (const key of KEYS) {
          if (!(key in changes)) continue
          const value = changes[key]
          if (value) next.set(key, value)
          else next.delete(key)
        }
        return next
      })
    },
    [setParams],
  )

  const setYear = useCallback((year: string | null) => update(year ? { year, from: null, to: null } : { year: null }), [update])
  const setProgram = useCallback((slug: string | null) => update({ program: slug, project: null }), [update])
  const setProject = useCallback(
    (slug: string | null, programSlug?: string | null) => update({ project: slug, ...(programSlug ? { program: programSlug } : {}) }),
    [update],
  )
  const setRegion = useCallback((slug: string | null) => update({ region: slug, center: null }), [update])
  const setCenter = useCallback(
    (slug: string | null, regionSlug?: string | null) => update({ center: slug, ...(regionSlug ? { region: regionSlug } : {}) }),
    [update],
  )
  const reset = useCallback(() => update({ program: null, project: null, region: null, center: null, year: null, from: null, to: null }), [update])

  const hasActiveFilters = KEYS.some((key) => Boolean(filters[key]))

  return { filters, update, setYear, setProgram, setProject, setRegion, setCenter, reset, hasActiveFilters }
}
