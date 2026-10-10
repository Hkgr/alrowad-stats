import type { FilterState } from '../types/api'

/** Current filters as a query string (empty values left out), e.g. for links to the print report. */
export function filtersQuery(filters: FilterState): string {
  const query = new URLSearchParams()
  for (const [key, value] of Object.entries(filters)) {
    if (value) query.set(key, value)
  }
  return query.toString()
}
