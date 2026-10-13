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

export interface ActivityRef {
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
  /** Years that have records. */
  years: number[]
  sectors: (SectorRef & { classified_projects: number })[]
  projects: (ProjectRef & { sector: { slug: string; name: string } | null; has_data: boolean })[]
  main_activities: (ActivityRef & { category: string | null; has_data: boolean })[]
  sub_activities: (ActivityRef & { has_data: boolean })[]
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
  items_label: string | null
  description: string | null
}

/** Figures of one slice. Every value is null (and has_data false) when the slice has no records. */
export interface SliceFigures {
  has_data: boolean
  total: number | null
  male: number | null
  female: number | null
  /** Part of the total whose gender the source does not report (not zero, not guessed). */
  gender_unreported: number | null
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
  year: number
  month: number
  selected: boolean
}

export interface MainActivityRow extends ActivityRef, SliceFigures {
  category: string | null
  sub_activities_count: number
  selected: boolean
}

export interface SubActivityRow extends ActivityRef, SliceFigures {
  selected: boolean
}

export interface OtherMeasure {
  code: string
  name: string
  unit_label: string
  total: number
  items_label: string | null
  items: number | null
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
  gender_unreported: number | null
  disabled: number | null
  offices_count: number | null
  records: number | null
  projects_with_data: number
  classified_projects: number
}

export type Gender = 'male' | 'female'
export type AgeGroup = 'under_18' | 'adult'

export type Level = 'overview' | 'sector' | 'project' | 'main_activity' | 'sub_activity'

export interface Dashboard {
  institution: Institution
  level: Level
  classification_year: number | null
  filters: {
    sector: SectorRef | null
    project: ProjectRef | null
    main_activity: ActivityRef | null
    sub_activity: ActivityRef | null
    period: { key: string; label: string } | null
    office: { slug: string; name: string } | null
    /** First / last month of a range (either may be null). Never set together with `period`. */
    from: { key: string; label: string } | null
    to: { key: string; label: string } | null
    year: number | null
    gender: Gender | null
    age: AgeGroup | null
    disabled: boolean
  }
  /** True when the figures are a gender / age / special-needs slice of the registered benefits. */
  demographic: boolean
  active_sector: SectorRef | null
  measure: Measure
  has_data: boolean
  summary: Summary
  sectors: SectorRow[]
  unclassified: (SectorRef & SliceFigures & { selected: boolean }) | null
  projects: ProjectRow[]
  activities: {
    main_available: boolean | null
    sub_available: boolean | null
    main: MainActivityRow[]
    without_main: SliceFigures | null
    sub: SubActivityRow[]
    without_sub: SliceFigures | null
    /** Level 1 of the project sheet: the source's own grouping, not an activity level. */
    categories: (SliceFigures & { name: string })[]
  }
  periods: PeriodRow[]
  offices: OfficeRow[]
  comparison: ComparisonRow[]
  other_measures: OtherMeasure[]
  insights: Insights
}

/** Readings derived from the filtered numbers. Shares are of the full total; unreported parts stay separate. */
export interface Insights {
  ages: {
    under_18: number
    adult: number
    unreported: number
    under_18_share: number | null
    adult_share: number | null
    unreported_share: number | null
    /** Share of the total whose age the source reports. */
    coverage: number | null
  } | null
  disabled: { count: number; share: number | null } | null
  months_with_data: number
  monthly_average: number | null
  peak_month: { key: string; label: string; total: number } | null
  top_office: { slug: string; name: string; total: number; share: number | null } | null
}

/** The exploration state shared by the URL, the controls and every query. */
export interface FilterState {
  institution: string | null
  sector: string | null
  project: string | null
  main_activity: string | null
  sub_activity: string | null
  period: string | null
  office: string | null
  year: string | null
  /** First / last month of a range, as YYYY-MM. */
  from: string | null
  to: string | null
  gender: string | null
  age: string | null
  /** '1' when only people with special needs are counted. */
  disabled: string | null
}

export type FilterKey = keyof FilterState

/** Breakdown tab shown under the charts. */
export type BreakdownView = 'projects' | 'offices' | 'sectors'

/** /analytics: deeper readings of the filtered scope. Every part is null when the scope has no such data. */
export interface Analytics {
  /** Overview and track levels only. Cells without a record are absent (not zero). */
  heatmap: {
    offices: { slug: string; name: string; total: number }[]
    rows: { slug: string; name: string; total: number; cells: Record<string, { value: number; share: number | null }> }[]
  } | null
  /** Overview (by track) and track level (by project). A month without a record of a series is null. */
  monthly_stack: {
    group: 'sector' | 'project'
    periods: { key: string; label: string }[]
    series: { key: string; name: string; code: string | null; total: number; values: (number | null)[] }[]
  } | null
  pyramid: {
    under_18: { male: number; female: number }
    adult: { male: number; female: number }
    genders: ('male' | 'female')[]
    total: number
    unreported: number
    coverage: number | null
  } | null
  courses: {
    courses: number
    records: number
    total: number
    average_per_course: number | null
    /** Share of the scope's registered benefits that carry a course number. */
    coverage: number | null
    sections: { total: number; records: number; average_per_record: number; benefits_per_section: number } | null
    by_project: { slug: string; name: string; courses: number; total: number; average_per_course: number }[]
    list: { number: string; offices: number; months: number; total: number }[]
  } | null
  knowledge: {
    records: number
    total: number
    specialties: { name: string; total: number }[]
    specialties_count: number
    other_specialties: { count: number; total: number } | null
    specialty_unreported: number
    universities: { name: string; total: number }[]
    university_unreported: number
  } | null
}

export interface IssueExample {
  file: string | null
  sheet: string | null
  row: number | null
  message: string
}

/** /data-quality: what the last import could not use as written, and where the data has holes. */
export interface DataQuality {
  run: { id: number; finished_at: string | null } | null
  issues: {
    severities: { severity: 'excluded' | 'conflict' | 'resolved' | 'info'; label: string; count: number }[]
    groups: { severity: 'excluded' | 'conflict' | 'resolved' | 'info'; code: string; label: string; count: number; examples: IssueExample[] }[]
  }
  gaps: { slug: string; name: string; first: string; last: string; months_with_data: number; missing: string[] }[]
  coverage: {
    classification_year: number | null
    classified_projects: number
    projects_with_data: number
    projects_without_data: number
    projects_without_data_names: string[]
  }
}
