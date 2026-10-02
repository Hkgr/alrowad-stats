// Mirrors the contracts documented in /api/README.md (API v1).

export interface Institution {
  slug: string
  name: string
}

export interface SectorRef {
  slug: string
  code: string | null
  name: string
}

export interface ProjectRef {
  slug: string
  name: string
}

export interface PeriodOption {
  key: string // "2026-05"
  label: string // "أيار 2026"
  year: number
  month: number
}

export interface FilterOptions {
  institution: Institution
  classification_year: number | null
  sectors: (SectorRef & { classified_projects: number })[]
  projects: (ProjectRef & { sector: { slug: string; name: string } | null; has_data: boolean })[]
  periods: PeriodOption[]
  offices: { slug: string; name: string }[]
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

/** Figures of one slice. Every value is null (and has_data false) when the slice has no records. */
export interface SliceFigures {
  has_data: boolean
  total: number | null
  male: number | null
  female: number | null
  projects_with_data: number | null
  offices_count: number | null
}

export interface SectorRow extends SectorRef, SliceFigures {
  selected: boolean
  classified_projects: number
}

export interface ProjectRow extends ProjectRef, SliceFigures {
  sector: { slug: string; name: string } | null
  selected: boolean
}

export interface PeriodRow extends SliceFigures {
  key: string
  label: string
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
  projects_with_data: number
  classified_projects: number
}

export type Level = 'overview' | 'sector' | 'project'

export interface Dashboard {
  institution: Institution
  level: Level
  classification_year: number | null
  filters: {
    sector: SectorRef | null
    project: ProjectRef | null
    period: { key: string; label: string } | null
    office: { slug: string; name: string } | null
  }
  active_sector: SectorRef | null
  measure: Measure
  has_data: boolean
  summary: Summary
  sectors: SectorRow[]
  unclassified: SliceFigures | null
  projects: ProjectRow[]
  periods: PeriodRow[]
  offices: OfficeRow[]
  comparison: ComparisonRow[]
}

/** The exploration state shared by the URL, the controls and every query. */
export interface FilterState {
  institution: string | null
  sector: string | null
  project: string | null
  period: string | null
  office: string | null
}

export type FilterKey = keyof FilterState

/** Breakdown tab shown under the charts. */
export type BreakdownView = 'projects' | 'offices' | 'sectors'
