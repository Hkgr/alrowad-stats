import { Accessibility, Building2, CalendarDays, Gauge } from 'lucide-react'
import { Card } from '../../../components/ui/Card'
import { Fact } from '../../../components/ui/Fact'
import { formatNumber, formatPercent } from '../../../lib/format'
import type { Insights } from '../../../types/api'

/**
 * Age split and highlights of the filtered scope. The age bar is proportional to the full total:
 * the part whose age the source does not report is its own grey segment, never shared out.
 */
export function InsightsPanel({ insights }: { insights: Insights }) {
  const { ages, disabled, peak_month: peak, monthly_average: average, top_office: top, months_with_data: months } = insights
  const hasFacts = disabled || peak || average != null || top
  if (!ages && !hasFacts) return null

  const segments = ages
    ? [
        { key: 'under', label: 'أقل من 18 سنة', count: ages.under_18, share: ages.under_18_share, color: 'var(--t-primary)' },
        { key: 'adult', label: '18 سنة فأكثر', count: ages.adult, share: ages.adult_share, color: 'var(--t-dark)' },
        { key: 'none', label: 'العمر غير مذكور', count: ages.unreported, share: ages.unreported_share, color: '#a3a8b0' },
      ].filter((s) => s.count > 0)
    : []

  return (
    <Card id="insights" title="مؤشرات تحليلية" hint="محسوبة من الأرقام المعروضة نفسها، وما لا يذكره المصدر يبقى منفصلًا">
      <div className="grid gap-5 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        {ages && (
          <div>
            <h3 className="text-sm font-bold text-ink-soft">الفئات العمرية</h3>
            <div
              className="mt-2 flex h-5 w-full overflow-hidden rounded-full bg-paper-deep"
              role="img"
              aria-label={`الفئات العمرية: ${segments.map((s) => `${s.label} ${formatNumber(s.count)} (${formatPercent(s.share)})`).join('، ')}`}
            >
              {segments.map((s) => (
                <span key={s.key} style={{ width: `${s.share ?? 0}%`, background: s.color }} className="h-full border-e border-white last:border-e-0" />
              ))}
            </div>
            <ul className="mt-3 flex flex-wrap gap-x-5 gap-y-1.5 text-sm">
              {segments.map((s) => (
                <li key={s.key} className="flex items-center gap-2">
                  <span aria-hidden="true" className="size-3 rounded-sm" style={{ background: s.color }} />
                  <span className="text-ink">{s.label}</span>
                  <span className="num font-bold text-ink">{formatNumber(s.count)}</span>
                  <span className="num text-ink-muted">{formatPercent(s.share)}</span>
                </li>
              ))}
            </ul>
            <p className="mt-2 text-sm text-ink-muted">
              العمر مذكور لـ {formatPercent(ages.coverage)} من الإجمالي فقط؛ النسب من الإجمالي الكامل وليست من المذكور وحده.
            </p>
          </div>
        )}

        {hasFacts && (
          <div className="grid gap-3 sm:grid-cols-2">
            {peak && <Fact icon={CalendarDays} label="أعلى شهر" value={formatNumber(peak.total)} caption={peak.label} />}
            {average != null && (
              <Fact icon={Gauge} label="متوسط الشهر" value={formatNumber(average)} caption={`على ${formatNumber(months)} أشهر فيها أرقام`} />
            )}
            {top && <Fact icon={Building2} label="أكبر مكتب" value={formatPercent(top.share)} caption={`${top.name} · ${formatNumber(top.total)}`} />}
            {disabled && (
              <Fact
                icon={Accessibility}
                label="ذوو الاحتياجات الخاصة"
                value={formatNumber(disabled.count)}
                caption={`${formatPercent(disabled.share)} من الإجمالي (المسجّل فقط)`}
              />
            )}
          </div>
        )}
      </div>
    </Card>
  )
}
