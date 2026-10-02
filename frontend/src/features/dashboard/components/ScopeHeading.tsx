import { ArrowRight } from 'lucide-react'
import { countLabel, PROJECTS } from '../../../lib/arabic'
import { formatNumber } from '../../../lib/format'
import type { Dashboard } from '../../../types/api'

interface Props {
  dashboard: Dashboard
  sectorColor: string | null
  onBack: (() => void) | null
  backLabel: string | null
}

/**
 * The page heading for the current level, plus one short scope line so a total is never read
 * as more than it is (e.g. "1 من 56 مشروعًا لديه بيانات").
 */
export function ScopeHeading({ dashboard, sectorColor, onBack, backLabel }: Props) {
  const { level, summary, filters, active_sector: sector, periods } = dashboard
  const title = level === 'project' ? (filters.project?.name ?? '') : level === 'sector' ? (sector?.name ?? '') : 'كل المسارات'
  const periodText = filters.period?.label ?? (periods.length > 0 ? periods.map((p) => p.label).join('، ') : null)

  return (
    <div className="flex flex-wrap items-end justify-between gap-3">
      <div className="min-w-0">
        {onBack && backLabel && (
          <button
            type="button"
            onClick={onBack}
            className="mb-1 inline-flex min-h-8 cursor-pointer items-center gap-1.5 rounded-lg text-sm font-bold text-brand-ink hover:underline"
          >
            <ArrowRight className="size-4" aria-hidden="true" />
            {backLabel}
          </button>
        )}
        <h1 className="flex items-center gap-2.5 text-2xl font-extrabold leading-tight text-ink sm:text-[1.75rem]">
          {sectorColor && <span aria-hidden="true" className="size-3 shrink-0 rounded-full" style={{ background: sectorColor }} />}
          {title}
        </h1>
        <p className="mt-1 flex flex-wrap gap-x-2 text-sm text-ink-muted">
          {level === 'project' ? (
            <>
              {sector && <span>{sector.name}</span>}
              {!sector && <span>غير مصنف</span>}
            </>
          ) : (
            <span>
              لديها بيانات:{' '}
              <span className="num font-bold text-ink">{formatNumber(summary.projects_with_data)}</span> من{' '}
              {countLabel(summary.classified_projects, PROJECTS)}
            </span>
          )}
          {periodText && (
            <>
              <span aria-hidden="true">·</span>
              <span className="font-semibold text-ink-soft">{periodText}</span>
            </>
          )}
        </p>
      </div>
    </div>
  )
}
