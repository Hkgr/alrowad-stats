import type { Analytics, Dashboard, DataQuality, FilterOptions, FilterState, Institution } from '../../types/api'
import { apiGet } from './client'

export const fetchInstitutions = (signal?: AbortSignal) =>
  apiGet<Institution[]>('/institutions', {}, signal)

export const fetchFilterOptions = (filters: FilterState, signal?: AbortSignal) =>
  apiGet<FilterOptions>(
    '/filters',
    {
      institution: filters.institution,
      sector: filters.sector,
      project: filters.project,
      main_activity: filters.main_activity,
      sub_activity: filters.sub_activity,
      period: filters.period,
      year: filters.year,
      gender: filters.gender,
      age: filters.age,
      disabled: filters.disabled,
    },
    signal,
  )

export const fetchDashboard = (filters: FilterState, signal?: AbortSignal) =>
  apiGet<Dashboard>('/dashboard', { ...filters }, signal)

/** Deeper readings (matrix, pyramid, courses, specialties, monthly stack) of the same filtered scope. */
export const fetchAnalytics = (filters: FilterState, signal?: AbortSignal) =>
  apiGet<Analytics>('/analytics', { ...filters }, signal)

export const fetchDataQuality = (institution: string, signal?: AbortSignal) =>
  apiGet<DataQuality>('/data-quality', { institution }, signal)
