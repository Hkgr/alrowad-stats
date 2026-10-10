import { formatNumber, formatPercent } from '../../../lib/format'
import type { Analytics } from '../../../types/api'

type Matrix = NonNullable<Analytics['heatmap']>

interface Props {
  matrix: Matrix
  selectedOffice: string | null
  onOpenProject: (slug: string) => void
  onSelectOffice: (slug: string) => void
}

/** Cell tint: the cell's share of its own project, relative to that project's strongest office. */
function tint(share: number | null, rowMax: number): string {
  if (share == null || rowMax <= 0) return 'transparent'
  // Capped so the dark number stays readable on every track colour (>= 4.5:1).
  const alpha = Math.round(8 + 47 * (share / rowMax))
  return `color-mix(in srgb, var(--t-primary) ${alpha}%, white)`
}

/**
 * Project × office table. The colour shows where each project concentrates (share of the project's
 * own total), the number is the exact count. A cell with no record is a dash — absence, not zero.
 */
export function ProjectOfficeMatrix({ matrix, selectedOffice, onOpenProject, onSelectOffice }: Props) {
  const { offices, rows } = matrix
  const th = 'whitespace-nowrap border-b border-line-strong bg-paper-deep/60 px-2 py-2 text-xs font-bold text-ink-soft'

  return (
    <div>
      <div className="overflow-x-auto rounded-xl border border-line">
        <table className="w-full border-separate border-spacing-0 text-sm">
          <caption className="sr-only">الاستفادات المسجلة لكل مشروع في كل مكتب</caption>
          <thead>
            <tr>
              <th scope="col" className={`${th} sticky start-0 z-10 min-w-44 text-start`}>
                المشروع
              </th>
              {offices.map((office) => (
                <th key={office.slug} scope="col" className={`${th} text-center`} aria-sort="none">
                  <button
                    type="button"
                    onClick={() => onSelectOffice(office.slug)}
                    aria-pressed={selectedOffice === office.slug}
                    title={`${office.name}: ${formatNumber(office.total)}`}
                    className={`cursor-pointer rounded-md px-1.5 py-0.5 font-bold hover:bg-white ${selectedOffice === office.slug ? 'bg-white text-ink ring-1 ring-ink-muted' : ''}`}
                  >
                    {office.name}
                  </button>
                </th>
              ))}
              <th scope="col" className={`${th} text-center`}>
                الإجمالي
              </th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => {
              const rowMax = Math.max(0, ...Object.values(row.cells).map((c) => c.share ?? 0))
              return (
                <tr key={row.slug}>
                  <th scope="row" className="sticky start-0 z-10 max-w-64 border-b border-line bg-white px-2 py-1.5 text-start font-semibold text-ink">
                    <button type="button" onClick={() => onOpenProject(row.slug)} className="block max-w-full cursor-pointer truncate text-start hover:underline" title={row.name}>
                      {row.name}
                    </button>
                  </th>
                  {offices.map((office) => {
                    const cell = row.cells[office.slug]
                    return (
                      <td
                        key={office.slug}
                        className="border-b border-line px-1.5 py-1.5 text-center"
                        style={{ background: cell ? tint(cell.share, rowMax) : undefined }}
                        title={cell ? `${row.name} · ${office.name}: ${formatNumber(cell.value)} (${formatPercent(cell.share)} من المشروع)` : `${row.name} · ${office.name}: لا سجلات`}
                      >
                        {cell ? <span className="num font-bold text-ink">{formatNumber(cell.value)}</span> : <span className="text-ink-muted" aria-label="لا سجلات">—</span>}
                      </td>
                    )
                  })}
                  <td className="border-b border-line bg-paper-deep/40 px-2 py-1.5 text-center font-extrabold text-ink">
                    <span className="num">{formatNumber(row.total)}</span>
                  </td>
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
      <p className="mt-2 text-sm text-ink-muted">اللون يبيّن حصة المكتب من إجمالي المشروع نفسه (الأغمق = أكبر حصة للمشروع)، والشرطة تعني أنه لا توجد سجلات.</p>
    </div>
  )
}
