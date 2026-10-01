import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { fetchDashboard, fetchFilterOptions, fetchInstitutions } from '../../services/api/dashboard'
import type { FilterState } from '../../types/api'

export function useInstitutions() {
  return useQuery({
    queryKey: ['institutions'],
    queryFn: ({ signal }) => fetchInstitutions(signal),
    staleTime: 5 * 60_000,
  })
}

/** `filters.institution` must be resolved before calling; queries stay idle until then. */
export function useFilterOptions(filters: FilterState) {
  return useQuery({
    queryKey: ['filters', filters.institution, filters.project, filters.period],
    queryFn: ({ signal }) => fetchFilterOptions(filters, signal),
    enabled: Boolean(filters.institution),
    placeholderData: keepPreviousData,
  })
}

export function useDashboard(filters: FilterState) {
  return useQuery({
    queryKey: ['dashboard', filters],
    queryFn: ({ signal }) => fetchDashboard(filters, signal),
    enabled: Boolean(filters.institution),
    placeholderData: keepPreviousData,
  })
}
