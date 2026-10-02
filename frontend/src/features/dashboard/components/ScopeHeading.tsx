import { ArrowRight } from 'lucide-react'
import { countLabel, PROJECTS } from '../../../lib/arabic'
import { formatNumber } from '../../../lib/format'
import type { Theme } from '../../../lib/themes'
import type { Dashboard } from '../../../types/api'

interface Props {
  dashboard: Dashboard
  theme: Theme
  onBack: (() => void) | null
  backLabel: string | null
}

const LEVEL_LABEL: Record<Dashboard['level'], string | null> = {
  overview: null,
  sector: 'المسار',
  project: 'المشروع',
  main_activity: 'النشاط الرئيسي',
  sub_activity: 'النشاط الفرعي',
}

/**
 * The heading of the current level and one short coverage line, so a total is never read as more
 * than it is (e.g. "تتوفر بيانات لـ 21 من 56 مشروعًا").
 */
export function ScopeHeading({ dashboard, theme, onBack, backLabel }: Props) {
  const { level, summary, filters, active_sector: sector, periods } = dashboard
  const title =
    level === 'sub_activity' ? filters.sub_activity?.name
      : level === 'main_activity' ? filters.main_activity?.name
        : level === 'project' ? filters.project?.name
          : level === 'sector' ? sector?.name
            : 'كل المسارات'
  const withData = periods.filter((p) => p.has_data)
  const periodText = filters.period?.label
    ?? (withData.length > 1 ? `${withData[0].label} – ${withData[withData.length - 1].label}` : withData[0]?.label ?? null)
  const context = [
    level !== 'sector' && level !== 'overview' ? sector?.name : null,
    level === 'main_activity' || level === 'sub_activity' ? filters.project?.name : null,
    level === 'sub_activity' ? filters.main_activity?.name : null,
  ].filter(Boolean)

  return (
    <div className="min-w-0">
      {onBack && backLabel && (
        <button
          type="button"
          onClick={onBack}
          className="mb-1 inline-flex min-h-8 cursor-pointer items-center gap-1.5 rounded-lg text-sm font-bold hover:underline"
          style={{ color: theme.dark }}
        >
          <ArrowRight className="size-4" aria-hidden="true" />
          {backLabel}
        </button>
      )}
      {LEVEL_LABEL[level] && (
        <p className="text-xs font-bold tracking-wide" style={{ color: theme.dark }}>
          {LEVEL_LABEL[level]}
        </p>
      )}
      <h1 className="flex items-center gap-2.5 text-2xl font-extrabold leading-tight text-ink sm:text-[1.75rem]">
        <span aria-hidden="true" className="text-2xl">
          {theme.emoji}
        </span>
        {title}
      </h1>
      <p className="mt-1 flex flex-wrap gap-x-2 text-sm text-ink-muted">
        {context.length > 0 && <span>{context.join(' · ')}</span>}
        {(level === 'overview' || level === 'sector') && summary.classified_projects > 0 && (
          <span>
            تتوفر بيانات لـ <span className="num font-bold text-ink">{formatNumber(summary.projects_with_data)}</span> من{' '}
            {countLabel(summary.classified_projects, PROJECTS)}
          </span>
        )}
        {(level === 'overview' || level === 'sector') && summary.classified_projects === 0 && summary.projects_with_data > 0 && (
          <span>
            بيانات {countLabel(summary.projects_with_data, PROJECTS)}
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
  )
}
