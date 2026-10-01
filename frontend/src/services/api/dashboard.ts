import type { Dashboard, FilterOptions, FilterState, Institution } from '../../types/api'
import { apiGet } from './client'

export const fetchInstitutions = (signal?: AbortSignal) =>
  apiGet<Institution[]>('/institutions', {}, signal)

export const fetchFilterOptions = (filters: FilterState, signal?: AbortSignal) =>
  apiGet<FilterOptions>(
    '/filters',
    { institution: filters.institution, project: filters.project, period: filters.period },
    signal,
  )

export const fetchDashboard = (filters: FilterState, signal?: AbortSignal) =>
  apiGet<Dashboard>('/dashboard', { ...filters }, signal)
