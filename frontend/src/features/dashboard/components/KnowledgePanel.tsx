import { formatNumber, formatPercent } from '../../../lib/format'
import type { Analytics } from '../../../types/api'

type Knowledge = NonNullable<Analytics['knowledge']>

interface BarsProps {
  title: string
  rows: { name: string; total: number }[]
  total: number
  /** Registered benefits whose value the source does not state. */
  unreported: number
  extra?: { label: string; total: number } | null
}

function Bars({ title, rows, total, unreported, extra }: BarsProps) {
  const max = Math.max(1, ...rows.map((r) => r.total), extra?.total ?? 0)
  const all = [...rows, ...(extra ? [{ name: extra.label, total: extra.total }] : [])]

  return (
    <div className="min-w-0">
      <h3 className="mb-2 text-sm font-bold text-ink-soft">{title}</h3>
      <ul className="space-y-1.5">
        {all.map((row, index) => {
          const muted = extra != null && index === all.length - 1
          return (
            <li key={row.name} className="grid grid-cols-[minmax(0,9rem)_1fr_auto] items-center gap-2 text-sm sm:grid-cols-[minmax(0,12rem)_1fr_auto]">
              <span className={`truncate ${muted ? 'text-ink-muted' : 'font-semibold text-ink'}`} title={row.name}>
                {row.name}
              </span>
              <span className="h-3 overflow-hidden rounded-full bg-paper-deep" aria-hidden="true">
                <span
                  className="block h-full rounded-full"
                  style={{ width: `${(row.total / max) * 100}%`, background: muted ? '#a3a8b0' : 'var(--t-primary)', border: muted ? undefined : '1px solid var(--t-dark)' }}
                />
              </span>
              <span className="num text-ink">
                <span className="font-bold">{formatNumber(row.total)}</span>{' '}
                <span className="text-xs text-ink-muted">{formatPercent(total > 0 ? Math.round((row.total / total) * 1000) / 10 : null)}</span>
              </span>
            </li>
          )
        })}
      </ul>
      {unreported > 0 && <p className="mt-2 text-sm text-ink-muted">غير مذكور في المصدر: {formatNumber(unreported)}</p>}
    </div>
  )
}

/** Specialty and university of the records that state them (رواد المعرفة). */
export function KnowledgePanel({ knowledge }: { knowledge: Knowledge }) {
  const other = knowledge.other_specialties

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <Bars
        title={`الاختصاص (${formatNumber(knowledge.specialties_count)})`}
        rows={knowledge.specialties}
        total={knowledge.total}
        unreported={knowledge.specialty_unreported}
        extra={other ? { label: `${formatNumber(other.count)} اختصاصات أخرى`, total: other.total } : null}
      />
      <Bars title={`الجامعة (${formatNumber(knowledge.universities.length)})`} rows={knowledge.universities} total={knowledge.total} unreported={knowledge.university_unreported} />
    </div>
  )
}
