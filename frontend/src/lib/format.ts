// Latin digits with thousands separators everywhere: full numbers, never abbreviated.
const integer = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 })
const percent = new Intl.NumberFormat('en-US', { maximumFractionDigits: 1, minimumFractionDigits: 1 })

export const NO_DATA = '—'

/** Absence (null) is shown as a dash, never as 0. */
export function formatNumber(value: number | null | undefined): string {
  return value == null ? NO_DATA : integer.format(value)
}

export function formatPercent(value: number | null | undefined): string {
  return value == null ? NO_DATA : `${percent.format(value)}%`
}

/** "من X إلى Y" for a month range (either end may be open), or null when there is no range. */
export function rangeLabel(from: string | null | undefined, to: string | null | undefined): string | null {
  if (from && to) return `من ${from} إلى ${to}`
  if (from) return `من ${from}`
  if (to) return `حتى ${to}`
  return null
}

/** Normalises Arabic text so search ignores diacritics, hamza forms and ta marbuta. */
export function normalizeArabic(text: string): string {
  return text
    .normalize('NFKC')
    .replace(/[ً-ٰٟـ]/g, '')
    .replace(/[أإآٱ]/g, 'ا')
    .replace(/ى/g, 'ي')
    .replace(/ة/g, 'ه')
    .toLowerCase()
    .trim()
}
