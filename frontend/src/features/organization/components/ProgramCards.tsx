import { formatNumber, formatPercent } from '../../../lib/format'
import { themeFor, type Theme } from '../../../lib/themes'
import type { OrgProgramRow } from '../types'

export function ProgramCards({ programs, onSelect }: { programs: OrgProgramRow[]; onSelect: (slug: string | null) => void }) {
  const anySelected = programs.some((program) => program.selected)

  return (
    <section aria-label="البرامج">
      <ul className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        {programs.map((program) => {
          const theme: Theme = themeFor(program.code)
          return (
            <li key={program.slug}>
              <button
                type="button"
                onClick={() => onSelect(program.selected ? null : program.slug)}
                aria-pressed={program.selected}
                className={`relative flex h-full w-full cursor-pointer flex-col overflow-hidden rounded-2xl border p-4 text-start shadow-card transition-[transform,box-shadow,opacity] duration-200 hover:-translate-y-0.5 hover:shadow-lift ${
                  anySelected && !program.selected ? 'opacity-75 hover:opacity-100' : ''
                }`}
                style={{
                  background: program.selected ? theme.light : '#ffffff',
                  borderColor: program.selected ? theme.primary : 'var(--color-line)',
                  boxShadow: program.selected ? `0 0 0 3px ${theme.primary}33` : undefined,
                }}
              >
                <span aria-hidden="true" className="absolute inset-x-0 top-0 h-1.5" style={{ background: theme.primary }} />
                <span className="flex items-center gap-2.5">
                  <span className="grid size-10 shrink-0 place-items-center rounded-xl text-xl" style={{ background: theme.light }} aria-hidden="true">
                    {theme.emoji}
                  </span>
                  <span className="min-w-0 text-sm font-bold leading-snug" style={{ color: theme.dark }}>
                    {program.name}
                  </span>
                </span>
                <span className="num mt-3 text-2xl font-extrabold text-ink">{formatNumber(program.beneficiaries)}</span>
                <span className="mt-1 text-sm text-ink-muted">
                  {program.projects === 1 ? 'مشروع واحد' : `${formatNumber(program.projects)} مشاريع`}
                  {program.share != null && ` · ${formatPercent(program.share)}`}
                </span>
              </button>
            </li>
          )
        })}
      </ul>
    </section>
  )
}
