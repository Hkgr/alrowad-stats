// One visual language for every chart: fonts, ink, grid, tooltip and the validated palette.
export const chartColors = {
  male: '#1f6fd1',
  female: '#e2571f',
  // Neutral warm slate for totals, so orange stays reserved for the female series and the brand.
  total: '#4a505a',
  ink: '#1d2025',
  inkSoft: '#454a52',
  inkMuted: '#656a73',
  grid: '#efe8de',
  axis: '#dcd1c2',
  surface: '#ffffff',
  // Label ink chosen per fill for >= 4.5:1 contrast.
  onMale: '#ffffff',
  onFemale: '#0d0e10',
} as const

/**
 * Categorical slots for sectors, validated in this order (CVD separation, chroma, lightness).
 * A sector takes the slot of its position in the institution's list, never a cycled colour.
 */
export const sectorPalette = ['#2a78d6', '#e2571f', '#16a06f', '#c98500', '#d55181'] as const

export function sectorColor(index: number): string {
  return sectorPalette[index] ?? chartColors.inkMuted
}

export const chartFont = {
  family: "'Cairo Variable', 'Cairo', system-ui, sans-serif",
  size: 13,
} as const

export const DIMMED_OPACITY = 0.25

export const axisLabelStyle = {
  color: chartColors.inkMuted,
  fontFamily: chartFont.family,
  fontSize: 12.5,
} as const

export const categoryLabelStyle = {
  ...axisLabelStyle,
  color: chartColors.ink,
  fontWeight: 600,
  fontSize: 13.5,
} as const

export const gridLine = {
  show: true,
  lineStyle: { color: chartColors.grid, type: 'dashed' as const, width: 1 },
}

export const intl = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 })

const escapeHtml = (value: string) =>
  value.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c] ?? c)

export interface TooltipRow {
  color?: string
  label: string
  value: string
  strong?: boolean
}

/** Shared tooltip markup: title, then one short row per figure (swatch · label · value). */
export function tooltipHtml(title: string, rows: TooltipRow[]): string {
  const body = rows
    .map(
      (row) => `
      <div style="display:flex;align-items:center;gap:12px;justify-content:space-between;margin-top:5px;${row.strong ? 'padding-top:5px;border-top:1px solid #efe8de;font-weight:700;' : ''}">
        <span style="display:flex;align-items:center;gap:7px;color:${chartColors.inkSoft}">
          ${row.color ? `<i style="width:10px;height:10px;border-radius:3px;background:${row.color};display:inline-block"></i>` : ''}
          ${escapeHtml(row.label)}
        </span>
        <span style="direction:ltr;font-variant-numeric:tabular-nums;color:${chartColors.ink}">${escapeHtml(row.value)}</span>
      </div>`,
    )
    .join('')
  return `<div dir="rtl" style="min-width:150px"><div style="font-weight:700;color:${chartColors.ink}">${escapeHtml(title)}</div>${body}</div>`
}

export const tooltipBase = {
  confine: true,
  backgroundColor: '#ffffff',
  borderColor: chartColors.axis,
  borderWidth: 1,
  padding: [9, 13],
  extraCssText: 'box-shadow:0 14px 32px -14px rgba(29,32,37,.3);border-radius:12px;direction:rtl;',
  textStyle: { fontFamily: chartFont.family, fontSize: 13, color: chartColors.ink },
}
