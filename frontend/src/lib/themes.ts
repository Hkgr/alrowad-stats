import type { CSSProperties } from 'react'
import type { Dashboard } from '../types/api'

/**
 * One theme per track, keyed by the track code from the institution's project list. Colours come
 * from the institution's project pages; projects, main and sub activities inherit their track's
 * theme. The institution identity (logo orange) is used for the overview and unclassified data.
 *
 * `ink` is the text colour to put on `primary` (yellow takes dark text). Track identity is never
 * colour alone: the track name and emoji are always shown next to it.
 */
export interface Theme {
  key: string
  primary: string
  dark: string
  light: string
  ink: string
  emoji: string
  backdrop: string[]
  /** Outline for chart marks when `primary` is below 3:1 on white (the yellow track). */
  outline?: string
}

export const THEMES: Record<string, Theme> = {
  EDU: { key: 'EDU', primary: '#0D5EAF', dark: '#073B72', light: '#F7FBFF', ink: '#FFFFFF', emoji: '🎓', backdrop: ['📚', '✏️', '💡', '🎓'] },
  CUL: { key: 'CUL', primary: '#F2B705', dark: '#9A6900', light: '#FFFCF0', ink: '#1D2025', emoji: '🎨', backdrop: ['🎭', '⚽', '🎵', '🎨'], outline: '#9A6900' },
  DEV: { key: 'DEV', primary: '#1A6B3A', dark: '#0F4324', light: '#F3FBF6', ink: '#FFFFFF', emoji: '🏡', backdrop: ['🌱', '🛠️', '🏗️', '🏡'] },
  HLT: { key: 'HLT', primary: '#B52532', dark: '#7A1721', light: '#FFF6F7', ink: '#FFFFFF', emoji: '🩺', backdrop: ['❤️', '🏥', '💊', '🩺'] },
  CHR: { key: 'CHR', primary: '#C95200', dark: '#7A3100', light: '#FFF6F0', ink: '#FFFFFF', emoji: '🤝', backdrop: ['🫶', '🎁', '🕊️', '🤝'] },
  PRT: { key: 'PRT', primary: '#6D28A8', dark: '#43156E', light: '#F8F4FC', ink: '#FFFFFF', emoji: '🛡️', backdrop: ['🛡️', '🤝', '🏠', '💜'] },
  EAR: { key: 'EAR', primary: '#C46B1A', dark: '#7A3E0C', light: '#FFF8F1', ink: '#FFFFFF', emoji: '🛠️', backdrop: ['🛠️', '🧱', '🌱', '🏡'] },
  FOD: { key: 'FOD', primary: '#8A5A00', dark: '#5C3C00', light: '#FFF9EE', ink: '#FFFFFF', emoji: '🌾', backdrop: ['🌾', '🍞', '🧺', '🌱'] },
  WSH: { key: 'WSH', primary: '#0E7490', dark: '#0A4E61', light: '#F3FBFD', ink: '#FFFFFF', emoji: '💧', backdrop: ['💧', '🚿', '🧼', '🌊'] },
}

/** Organization overview uses the same orange identity as the institution. */
export const ORGANIZATION_THEME: Theme = {
  key: 'ORGANIZATION', primary: '#EF5A27', dark: '#B23D0F', light: '#FFF7F2', ink: '#FFFFFF', emoji: '🏛️',
  backdrop: ['🏛️', '🤝', '📍', '📊', '🌱'],
}

/** Institution-wide identity: overview and records without a track. */
export const INSTITUTION_THEME: Theme = {
  key: 'INSTITUTION', primary: '#EF5A27', dark: '#B23D0F', light: '#FFF7F2', ink: '#FFFFFF', emoji: '🤝',
  backdrop: ['🤝', '🫶', '🕊️', '📚', '🌱', '🏡', '🩺', '🎨'],
}

export const UNCLASSIFIED_THEME: Theme = {
  key: 'UNCLASSIFIED', primary: '#656A73', dark: '#3D4250', light: '#F5F4F1', ink: '#FFFFFF', emoji: '🗂️',
  backdrop: ['🤝', '🫶', '🕊️', '📚'],
}

export function themeFor(code: string | null | undefined, unclassified = false): Theme {
  if (unclassified) return UNCLASSIFIED_THEME
  return (code && THEMES[code]) || INSTITUTION_THEME
}

/** Theme of a dashboard scope: the active track's, the unclassified one, or the institution's. */
export function scopeTheme(data: Pick<Dashboard, 'active_sector'> | undefined): Theme {
  const sector = data?.active_sector
  if (!sector) return INSTITUTION_THEME
  return themeFor(sector.code, sector.slug === 'unclassified')
}

/** CSS custom properties consumed by Tailwind arbitrary values (e.g. `bg-[var(--t-light)]`). */
export function themeStyle(theme: Theme): CSSProperties {
  return {
    '--t-primary': theme.primary,
    '--t-dark': theme.dark,
    '--t-light': theme.light,
    '--t-ink': theme.ink,
  } as CSSProperties
}
