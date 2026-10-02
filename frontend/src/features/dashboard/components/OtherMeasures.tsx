import { Home } from 'lucide-react'
import { formatNumber } from '../../../lib/format'
import type { OtherMeasure } from '../../../types/api'

/** Figures in another unit (e.g. families served). Shown apart, never added to registered benefits. */
export function OtherMeasures({ measures }: { measures: OtherMeasure[] }) {
  if (measures.length === 0) return null

  return (
    <div className="flex flex-wrap gap-3" aria-label="مؤشرات بوحدات أخرى">
      {measures.map((m) => (
        <article key={m.code} className="flex min-w-0 items-center gap-3 rounded-2xl border border-line bg-white px-4 py-3 shadow-card">
          <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-paper-deep text-ink-soft" aria-hidden="true">
            <Home className="size-5" />
          </span>
          <span className="min-w-0">
            <span className="block text-sm font-bold text-ink-soft">{m.name}</span>
            <span className="flex flex-wrap items-baseline gap-x-3">
              <span>
                <span className="num text-2xl font-extrabold text-ink" title={String(m.total)}>{formatNumber(m.total)}</span>{' '}
                <span className="text-xs text-ink-muted">{m.unit_label}</span>
              </span>
              {m.items !== null && m.items_label && (
                <span className="text-sm text-ink-muted">
                  {m.items_label}: <span className="num font-bold text-ink">{formatNumber(m.items)}</span>
                </span>
              )}
            </span>
          </span>
        </article>
      ))}
    </div>
  )
}
