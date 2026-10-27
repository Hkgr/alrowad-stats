export interface OrgFilters {
  program: string | null
  project: string | null
  region: string | null
  center: string | null
  year: string | null
  from: string | null
  to: string | null
}

export interface OrgNamed {
  slug: string
  name: string
  code?: string
}

export interface OrgYearRow {
  year: number
  beneficiaries: number
  selected: boolean
}

export interface OrgProgramRow {
  slug: string
  code: string
  name: string
  beneficiaries: number
  projects: number
  share: number | null
  selected: boolean
}

export interface OrgProjectRow {
  slug: string
  name: string
  program_slug: string
  program_name: string
  beneficiaries: number
  disabled: number
  staff: number
  selected: boolean
}

export interface OrgRegionRow {
  slug: string
  name: string
  beneficiaries: number
  centers: number
  disabled: number
  staff: number
  offices: number
  share: number | null
  selected: boolean
}

export interface OrgCenterRow {
  slug: string
  name: string
  region_slug: string | null
  region_name: string | null
  beneficiaries: number
  selected: boolean
}

export interface OrgDashboard {
  scope: {
    program: OrgNamed | null
    project: OrgNamed | null
    region: OrgNamed | null
    center: OrgNamed | null
    year: number | null
    from: number | null
    to: number | null
  }
  summary: {
    beneficiaries: number
    programs: number
    projects: number
    regions: number
    centers: number
    span: string | null
  }
  snapshot: {
    basis: 'overview' | 'program' | 'region'
    disabled: number
    staff: number
    offices: number | null
  } | null
  by_year: OrgYearRow[]
  programs: OrgProgramRow[]
  projects: OrgProjectRow[]
  regions: OrgRegionRow[]
  centers: OrgCenterRow[]
  options: {
    programs: { slug: string; name: string; code: string }[]
    projects: { slug: string; name: string; program_slug: string }[]
    regions: { slug: string; name: string }[]
    centers: { slug: string; name: string; region_slug: string | null }[]
    years: number[]
  }
}
