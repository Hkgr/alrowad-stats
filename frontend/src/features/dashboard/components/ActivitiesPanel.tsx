import { ChevronDown, ChevronLeft, CircleDashed } from 'lucide-react'
import { useState } from 'react'
import { formatNumber } from '../../../lib/format'
import type { Theme } from '../../../lib/themes'
import type { MainActivityRow, SliceFigures, SubActivityRow } from '../../../types/api'

type Row = (MainActivityRow | SubActivityRow) & { category?: string | null; sub_activities_count?: number }

interface Props {
  kind: 'main' | 'sub'
  rows: Row[]
  /** Records of the scope that have no activity at this level in the source. */
  without: SliceFigures | null
  theme: Theme
  onOpen: (slug: string) => void
}

const INITIAL = 12

/**
 * «الأنشطة الرئيسية» (Level 2) of a project, or «الأنشطة الفرعية» (Level 3) of a main activity.
 * Each row shows its own registered benefits; the parent total is the sum of these rows.
 */
export function ActivitiesPanel({ kind, rows, without, theme, onOpen }: Props) {
  const [expanded, setExpanded] = useState(false)
  if (rows.length === 0) {
    return (
      <p className="flex items-center gap-2 rounded-xl bg-paper px-4 py-5 text-sm font-semibold text-ink-muted">
        <CircleDashed className="size-4 shrink-0" aria-hidden="true" />
        {kind === 'main' ? 'لا تتوفر أنشطة رئيسية في المصدر' : 'لا تتوفر أنشطة فرعية في المصدر'}
      </p>
    )
  }

  const max = Math.max(1, ...rows.map((r) => r.total ?? 0))
  // Rows arrive largest first, so the short list always shows the biggest activities.
  const shown = expanded ? rows : rows.slice(0, INITIAL)

  return (
    <div className="space-y-2">
      <ul className="grid gap-2 md:grid-cols-2">
        {shown.map((row) => (
          <li key={row.slug}>
            <button
              type="button"
              onClick={() => onOpen(row.slug)}
              aria-pressed={row.selected}
              className="group flex h-full w-full cursor-pointer items-center gap-3 rounded-xl border bg-white px-3.5 py-3 text-start transition-colors hover:bg-[var(--t-light)]"
              style={{ borderColor: row.selected ? theme.primary : 'var(--color-line)' }}
            >
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-bold leading-snug text-ink">{row.name}</span>
                <span className="mt-0.5 block truncate text-xs text-ink-muted">
                  {[row.category, kind === 'main' && row.sub_activities_count ? `${formatNumber(row.sub_activities_count)} نشاط فرعي` : null]
                    .filter(Boolean)
                    .join(' · ') || ' '}
                </span>
                {row.has_data && (
                  <span aria-hidden="true" className="mt-2 block h-1.5 w-full overflow-hidden rounded-full bg-paper-deep">
                    <span className="block h-full rounded-full" style={{ width: `${((row.total ?? 0) / max) * 100}%`, background: theme.primary }} />
                  </span>
                )}
              </span>
              <span className="shrink-0 text-end">
                {row.has_data ? (
                  <>
                    <span className="num block text-lg font-extrabold leading-none text-ink">{formatNumber(row.total)}</span>
                    <span className="block text-[11px] text-ink-muted">استفادة مسجلة</span>
                  </>
                ) : (
                  <span className="block text-xs font-semibold text-ink-muted">لا توجد بيانات</span>
                )}
              </span>
              <ChevronLeft className="size-4 shrink-0 text-ink-muted transition-transform group-hover:-translate-x-0.5" aria-hidden="true" />
            </button>
          </li>
        ))}
      </ul>
      {shown.length < rows.length && (
        <div className="flex justify-center">
          <button
            type="button"
            onClick={() => setExpanded(true)}
            className="inline-flex min-h-10 cursor-pointer items-center gap-1.5 rounded-xl border border-line-strong bg-white px-4 text-sm font-bold text-ink hover:border-ink-muted"
          >
            {`عرض كل ${kind === 'main' ? 'الأنشطة الرئيسية' : 'الأنشطة الفرعية'} (${formatNumber(rows.length)})`}
            <ChevronDown className="size-4" aria-hidden="true" />
          </button>
        </div>
      )}
      {without?.has_data && (
        <p className="text-sm text-ink-muted">
          {kind === 'main' ? 'استفادات بلا نشاط رئيسي في المصدر' : 'استفادات بلا نشاط فرعي في المصدر'}:{' '}
          <span className="num font-bold text-ink">{formatNumber(without.total)}</span>
        </p>
      )}
    </div>
  )
}
