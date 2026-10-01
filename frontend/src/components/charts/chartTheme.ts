// One visual language for every chart: fonts, ink, grid, tooltip and the validated palette.
export const chartColors = {
  male: '#2f6fd0',
  female: '#e26a00',
  total: '#14274e',
  ink: '#11213f',
  inkSoft: '#3d4d6b',
  inkMuted: '#5b6b8a',
  grid: '#e3e9f3',
  axis: '#c9d5ea',
  surface: '#ffffff',
  // Label ink chosen per fill for >= 4.5:1 contrast.
  onMale: '#ffffff',
  onFemale: '#14274e',
} as const

export const chartFont = {
  family: "'Cairo Variable', 'Cairo', system-ui, sans-serif",
  size: 13,
} as const

export const DIMMED_OPACITY = 0.28

export const axisLabelStyle = {
  color: chartColors.inkMuted,
  fontFamily: chartFont.family,
  fontSize: 12.5,
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

/** Shared tooltip markup: title, then one row per figure (swatch · label · value). */
export function tooltipHtml(title: string, rows: TooltipRow[]): string {
  const body = rows
    .map(
      (row) => `
      <div style="display:flex;align-items:center;gap:10px;justify-content:space-between;margin-top:6px;${row.strong ? 'padding-top:6px;border-top:1px solid #e3e9f3;font-weight:700;' : ''}">
        <span style="display:flex;align-items:center;gap:7px;color:${chartColors.inkSoft}">
          ${row.color ? `<i style="width:10px;height:10px;border-radius:3px;background:${row.color};display:inline-block"></i>` : ''}
          ${escapeHtml(row.label)}
        </span>
        <span style="direction:ltr;font-variant-numeric:tabular-nums;color:${chartColors.ink}">${escapeHtml(row.value)}</span>
      </div>`,
    )
    .join('')
  return `<div dir="rtl" style="min-width:170px"><div style="font-weight:700;color:${chartColors.ink}">${escapeHtml(title)}</div>${body}</div>`
}

export const tooltipBase = {
  confine: true,
  backgroundColor: '#ffffff',
  borderColor: chartColors.axis,
  borderWidth: 1,
  padding: [10, 14],
  extraCssText: 'box-shadow:0 12px 32px -12px rgba(11,27,63,.35);border-radius:12px;direction:rtl;',
  textStyle: { fontFamily: chartFont.family, fontSize: 13, color: chartColors.ink },
}
