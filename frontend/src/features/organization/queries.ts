import { useQuery } from '@tanstack/react-query'
import { apiGet } from '../../services/api/client'
import type { OrgDashboard, OrgFilters } from './types'

export const fetchOrganization = (filters: OrgFilters, signal?: AbortSignal) =>
  apiGet<OrgDashboard>(
    '/organization',
    {
      program: filters.program,
      project: filters.project,
      region: filters.region,
      center: filters.center,
      year: filters.year,
      from: filters.from,
      to: filters.to,
    },
    signal,
  )

export function useOrganization(filters: OrgFilters) {
  return useQuery({
    queryKey: ['organization', filters],
    queryFn: ({ signal }) => fetchOrganization(filters, signal),
  })
}
