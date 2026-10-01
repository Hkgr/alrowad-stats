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
