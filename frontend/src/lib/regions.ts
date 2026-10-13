import type { RegionOption } from '../types/api'
import { OFFICES, countLabel } from './arabic'

/** The `office` filter is a comma-separated list of office slugs ("afrin,marea"). */
export function parseOffices(value: string | null | undefined): string[] {
  return value ? [...new Set(value.split(',').map((s) => s.trim()).filter(Boolean))] : []
}

export const serializeOffices = (slugs: string[]): string | null => (slugs.length ? slugs.join(',') : null)

/** "9 مكاتب" / "مكتبان" / "مكتب واحد", or the note for a region that has none. */
export function officesCountLabel(region: RegionOption): string {
  return region.has_offices ? countLabel(region.offices_count, OFFICES) : 'لا يوجد مكاتب'
}

/** Slugs of the choices a region offers (its offices, or the one entry that stands for it). */
export const regionSlugs = (region: RegionOption): string[] => region.offices.map((o) => o.slug)

export interface SelectionChip {
  key: string
  label: string
  /** Office slugs this chip stands for (removed together). */
  slugs: string[]
}

/**
 * What is chosen, as short labels: a region whose offices are all chosen reads "منطقة حلب" (one chip);
 * anything else is shown office by office. A selected slug the options do not list keeps its own name.
 */
export function selectionChips(regions: RegionOption[], selected: string[], names: Record<string, string> = {}): SelectionChip[] {
  const chosen = new Set(selected)
  const used = new Set<string>()
  const chips: SelectionChip[] = []

  for (const region of regions) {
    const slugs = regionSlugs(region)
    const picked = slugs.filter((s) => chosen.has(s))
    if (picked.length === 0) continue
    picked.forEach((s) => used.add(s))

    if (region.has_offices && slugs.length > 1 && picked.length === slugs.length) {
      chips.push({ key: `region:${region.slug}`, label: `منطقة ${region.name}`, slugs })
      continue
    }
    for (const office of region.offices) {
      if (chosen.has(office.slug)) chips.push({ key: office.slug, label: office.name, slugs: [office.slug] })
    }
  }

  for (const slug of selected) {
    if (!used.has(slug)) chips.push({ key: slug, label: names[slug] ?? slug, slugs: [slug] })
  }
  return chips
}
