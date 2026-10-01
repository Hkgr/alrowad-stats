// Mirrors the contracts documented in /api/README.md (API v1).

export interface Institution {
  slug: string
  name: string
}

export interface ProjectOption {
  slug: string
  name: string
}

export interface PeriodOption {
  key: string // "2026-05"
  label: string // "أيار 2026"
  year: number
  month: number
}

export interface OfficeOption {
  slug: string
  name: string
}

export interface FilterOptions {
  institution: Institution
  projects: ProjectOption[]
  periods: PeriodOption[]
  offices: OfficeOption[]
}

export interface Measure {
  code: string
  name: string
  unit: string
  unit_label: string
  record_level: string
  record_level_label: string
  aggregation: string
  aggregation_label: string
  description: string | null
}

export interface OfficeRow {
  slug: string
  name: string
  male: number
  female: number
  total: number
  share: number | null
}

export interface ComparisonRow {
  slug: string
  name: string
  male: number
  female: number
  total: number
  selected: boolean
}

/** Every figure is null when no records match the filters (absence, not zero). */
export interface Summary {
  total: number | null
  male: number | null
  female: number | null
  male_share: number | null
  female_share: number | null
  offices_count: number | null
}

export interface ScopeProject {
  slug: string
  name: string
  sector: string | null
  source_category: string | null
}

export interface ScopeSource {
  label: string
  file_name: string | null
  reference_url: string | null
  coverage: 'sample' | 'full'
  notes: string | null
}

export interface Dashboard {
  institution: Institution
  filters: {
    project: { slug: string; name: string } | null
    period: { key: string; label: string } | null
    office: { slug: string; name: string } | null
  }
  measure: Measure
  has_data: boolean
  summary: Summary
  offices: OfficeRow[]
  comparison: ComparisonRow[]
  scope: {
    projects: ScopeProject[]
    periods: { key: string; label: string }[]
    sources: ScopeSource[]
  }
}

/** The filter state shared by the URL, the filter bar and every query. */
export interface FilterState {
  institution: string | null
  project: string | null
  period: string | null
  office: string | null
}

export type FilterKey = keyof FilterState
