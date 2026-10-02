import { formatNumber } from './format'

interface Forms {
  /** 1 → "مشروع واحد" */
  one: string
  /** 2 → "مشروعان" */
  two: string
  /** 3–10 → "مشاريع" (after the number) */
  few: string
  /** 11+ → "مشروعًا" (after the number) */
  many: string
}

/** Counts with correct Arabic number agreement, e.g. 1 مشروع، 2 مشروعان، 8 مشاريع، 15 مشروعًا. */
export function countLabel(n: number, forms: Forms): string {
  if (n === 1) return forms.one
  if (n === 2) return forms.two
  const mod = n % 100
  if (mod >= 3 && mod <= 10) return `${formatNumber(n)} ${forms.few}`
  return `${formatNumber(n)} ${forms.many}`
}

export const PROJECTS: Forms = { one: 'مشروع واحد', two: 'مشروعان', few: 'مشاريع', many: 'مشروعًا' }
export const OFFICES: Forms = { one: 'مكتب واحد', two: 'مكتبان', few: 'مكاتب', many: 'مكتبًا' }
