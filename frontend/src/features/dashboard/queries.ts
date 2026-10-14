import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { fetchAnalytics, fetchDashboard, fetchDataQuality, fetchFilterOptions, fetchInstitutions } from '../../services/api/dashboard'
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
    queryKey: ['filters', filters.institution, filters.sector, filters.project, filters.main_activity, filters.sub_activity, filters.period, filters.year, filters.from, filters.to, filters.gender, filters.age, filters.disabled],
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

export function useAnalytics(filters: FilterState) {
  return useQuery({
    queryKey: ['analytics', filters],
    queryFn: ({ signal }) => fetchAnalytics(filters, signal),
    enabled: Boolean(filters.institution),
    placeholderData: keepPreviousData,
  })
}

/** Loaded only when the panel is opened (`enabled`): it is a maintenance view, not part of the first paint. */
export function useDataQuality(institution: string | null, enabled: boolean) {
  return useQuery({
    queryKey: ['data-quality', institution],
    queryFn: ({ signal }) => fetchDataQuality(institution as string, signal),
    enabled: enabled && Boolean(institution),
    staleTime: 60_000,
  })
}
