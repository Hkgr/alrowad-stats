import { ArrowRight, Printer } from 'lucide-react'
import { useEffect, useMemo, useState } from 'react'
import logo from '../../assets/brand/rowad-logo.png'
import { Skeleton } from '../../components/ui/Skeleton'
import { ErrorState, NoDataState } from '../../components/ui/StateViews'
import { formatNumber, formatPercent } from '../../lib/format'
import { filtersQuery } from '../../lib/query'
import { scopeTheme, themeStyle } from '../../lib/themes'
import type { Dashboard } from '../../types/api'
import { GenderDonutChart } from '../dashboard/components/GenderDonutChart'
import { OfficeRankingChart } from '../dashboard/components/OfficeRankingChart'
import { PeriodsChart } from '../dashboard/components/PeriodsChart'
import { useDashboard, useInstitutions } from '../dashboard/queries'
import { useDashboardFilters } from '../dashboard/useDashboardFilters'

const noop = () => undefined

/** Heading of the scope: the track, project or activity the report is about. */
function scopeTitle(data: Dashboard): string {
  const { filters, level, active_sector: sector } = data
  return (
    (level === 'sub_activity' ? filters.sub_activity?.name
      : level === 'main_activity' ? filters.main_activity?.name
        : level === 'project' ? filters.project?.name
          : level === 'sector' ? sector?.name
            : null) ?? 'كل المسارات'
  )
}

/** Filters in effect, as readable text ("السنة: 2026 · الجنس: إناث فقط"). */
function describeFilters(data: Dashboard): string[] {
  const f = data.filters
  const out: string[] = []
  if (data.level !== 'overview' && data.active_sector && data.level !== 'sector') out.push(`المسار: ${data.active_sector.name}`)
  if (f.project && data.level !== 'project') out.push(`المشروع: ${f.project.name}`)
  if (f.main_activity && data.level !== 'main_activity') out.push(`النشاط الرئيسي: ${f.main_activity.name}`)
  if (f.year) out.push(`السنة: ${f.year}`)
  if (f.office) out.push(`المكتب: ${f.office.name}`)
  if (f.gender) out.push(`الجنس: ${f.gender === 'male' ? 'ذكور فقط' : 'إناث فقط'}`)
  if (f.age) out.push(`العمر: ${f.age === 'under_18' ? 'أقل من 18 سنة' : '18 سنة فأكثر'}`)
  if (f.disabled) out.push('ذوو الاحتياجات الخاصة فقط')
  return out
}

function periodText(data: Dashboard): string {
  if (data.filters.period) return data.filters.period.label
  const withData = data.periods.filter((p) => p.has_data)
  if (withData.length === 0) return 'كل الفترات'
  return withData.length === 1 ? withData[0].label : `${withData[0].label} – ${withData[withData.length - 1].label}`
}

function Box({ label, value, caption }: { label: string; value: string; caption?: string }) {
  return (
    <div className="avoid-break rounded-xl border border-line px-4 py-3">
      <p className="text-sm font-bold text-ink-soft">{label}</p>
      <p className="num mt-1 text-3xl font-extrabold leading-none text-ink">{value}</p>
      {caption && <p className="mt-1.5 text-sm text-ink-muted">{caption}</p>}
    </div>
  )
}

/** Rows of the detail table: projects of a track, activities of a project, or offices. */
function detailRows(data: Dashboard): { title: string; rows: { name: string; total: number | null; male: number | null; female: number | null }[] } {
  const pick = (title: string, rows: { name: string; total: number | null; male: number | null; female: number | null; has_data?: boolean }[]) => ({
    title,
    rows: rows.filter((r) => r.total != null).slice(0, 10),
  })
  if (data.level === 'overview' || data.level === 'sector') return pick('أكبر المشاريع', data.projects)
  if (data.level === 'project' && data.activities.main.length > 0) return pick('الأنشطة الرئيسية', data.activities.main)
  if (data.level === 'main_activity' && data.activities.sub.length > 0) return pick('الأنشطة الفرعية', data.activities.sub)
  return pick('المكاتب', data.offices)
}

/**
 * Print-ready report of the current view: heading, figures, highlights, two charts and a short table
 * (A4, RTL). "Print" opens the browser dialog, where "Save as PDF" produces the file. The numbers
 * are the dashboard's own — same API, same filters — so the report can never disagree with the screen.
 */
export function ReportPage() {
  const { filters: urlFilters } = useDashboardFilters()
  const institutions = useInstitutions()
  const filters = useMemo(
    () => ({ ...urlFilters, institution: urlFilters.institution ?? institutions.data?.[0]?.slug ?? null }),
    [urlFilters, institutions.data],
  )
  const dashboard = useDashboard(filters)
  const data = dashboard.data
  const theme = scopeTheme(data)
  const failure = institutions.error ?? dashboard.error

  useEffect(() => {
    document.title = data ? `تقرير ${scopeTitle(data)} — ${periodText(data)}` : 'تقرير إحصاءات الرواد'
  }, [data])

  // Fixed when the page opens, so the date does not change between renders.
  const [today] = useState(() => new Date().toLocaleDateString('ar-SY-u-nu-latn', { year: 'numeric', month: 'long', day: 'numeric' }))
  const backHref = `/?${filtersQuery(filters)}`

  return (
    <div className="min-h-screen bg-paper-deep py-6 print:bg-white print:py-0" style={themeStyle(theme)}>
      <div className="no-print mx-auto mb-4 flex max-w-[210mm] flex-wrap items-center justify-between gap-3 px-4">
        <a href={backHref} className="inline-flex min-h-10 items-center gap-1.5 rounded-lg text-sm font-bold text-ink-soft hover:text-ink">
          <ArrowRight className="size-4" aria-hidden="true" />
          رجوع إلى اللوحة
        </a>
        <button
          type="button"
          onClick={() => window.print()}
          disabled={!data}
          className="inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-xl bg-ink px-4 text-sm font-bold text-white hover:bg-ink-soft disabled:cursor-not-allowed disabled:opacity-45"
        >
          <Printer className="size-4" aria-hidden="true" />
          طباعة / حفظ PDF
        </button>
      </div>

      <main className="report-sheet mx-auto max-w-[210mm] space-y-5 bg-white p-8 shadow-card print:max-w-none print:p-0 print:shadow-none">
        {failure ? (
          <ErrorState message={failure.message} onRetry={() => void dashboard.refetch()} />
        ) : !data ? (
          <div aria-busy="true" className="space-y-4">
            <Skeleton className="h-24" />
            <Skeleton className="h-32" />
            <Skeleton className="h-64" />
          </div>
        ) : (
          <ReportBody data={data} theme={theme} today={today} />
        )}
      </main>
    </div>
  )
}

function ReportBody({ data, theme, today }: { data: Dashboard; theme: ReturnType<typeof scopeTheme>; today: string }) {
  const { summary, insights } = data
  const filters = describeFilters(data)
  const withData = data.periods.filter((p) => p.has_data)
  const detail = detailRows(data)
  const ages = insights.ages

  return (
    <>
      <header className="avoid-break border-b-2 pb-4" style={{ borderColor: theme.primary }}>
        <div className="flex items-center justify-between gap-4">
          <div className="min-w-0">
            <p className="text-sm font-bold" style={{ color: theme.dark }}>
              تقرير إحصاءات الرواد — الاستفادات المسجلة
            </p>
            <h1 className="mt-1 text-3xl font-extrabold leading-tight text-ink">
              {theme.emoji} {scopeTitle(data)}
            </h1>
            <p className="mt-1 text-base font-semibold text-ink-soft">{periodText(data)}</p>
          </div>
          <img src={logo} alt="مؤسسة الرواد للتعاون والتنمية" width={1000} height={381} className="h-16 w-auto shrink-0" />
        </div>
        {filters.length > 0 && <p className="mt-2 text-sm text-ink-soft">{filters.join(' · ')}</p>}
      </header>

      {!data.has_data ? (
        <NoDataState compact />
      ) : (
        <>
          <section aria-label="الأرقام الرئيسية" className="grid grid-cols-2 gap-3">
            <Box
              label="الاستفادات المسجلة"
              value={formatNumber(summary.total)}
              caption={summary.gender_unreported ? `منها ${formatNumber(summary.gender_unreported)} لم يُذكر جنسها` : undefined}
            />
            <Box label="الذكور" value={formatNumber(summary.male)} caption={`${formatPercent(summary.male_share)} من الإجمالي`} />
            <Box label="الإناث" value={formatNumber(summary.female)} caption={`${formatPercent(summary.female_share)} من الإجمالي`} />
            <Box label="المكاتب" value={formatNumber(summary.offices_count)} caption={`${formatNumber(summary.projects_with_data)} مشروعًا لديها بيانات`} />
          </section>

          {data.other_measures.length > 0 && (
            <p className="text-sm text-ink-soft">
              {data.other_measures.map((m) => `${m.name}: ${formatNumber(m.total)} ${m.unit_label}`).join(' · ')} (وحدة مستقلة، لا تُجمع مع الاستفادات).
            </p>
          )}

          <section aria-label="أبرز الأرقام" className="avoid-break">
            <h2 className="mb-2 text-lg font-bold text-ink">أبرز الأرقام</h2>
            <ul className="grid gap-x-6 gap-y-1.5 text-sm leading-6 text-ink-soft sm:grid-cols-2">
              {insights.peak_month && (
                <li>
                  أعلى شهر: <strong className="text-ink">{insights.peak_month.label}</strong> (<span className="num">{formatNumber(insights.peak_month.total)}</span>)
                </li>
              )}
              {insights.monthly_average != null && (
                <li>
                  متوسط الشهر: <strong className="num text-ink">{formatNumber(insights.monthly_average)}</strong> على {formatNumber(insights.months_with_data)} أشهر فيها أرقام
                </li>
              )}
              {insights.top_office && (
                <li>
                  أكبر مكتب: <strong className="text-ink">{insights.top_office.name}</strong> ({formatPercent(insights.top_office.share)})
                </li>
              )}
              {insights.disabled && (
                <li>
                  ذوو الاحتياجات الخاصة المسجَّلون: <strong className="num text-ink">{formatNumber(insights.disabled.count)}</strong> ({formatPercent(insights.disabled.share)})
                </li>
              )}
              {ages && (
                <li className="sm:col-span-2">
                  الفئات العمرية: أقل من 18 سنة <strong className="text-ink">{formatPercent(ages.under_18_share)}</strong> · 18 فأكثر{' '}
                  <strong className="text-ink">{formatPercent(ages.adult_share)}</strong> · غير مذكور {formatPercent(ages.unreported_share)} (العمر مذكور لـ {formatPercent(ages.coverage)} من الإجمالي)
                </li>
              )}
            </ul>
          </section>

          {/* Full width each: the months chart needs more than half a page to show every month. */}
          <section aria-label="الرسوم" className="space-y-5">
            <div className="avoid-break min-w-0 rounded-xl border border-line p-4">
              <h2 className="mb-2 text-base font-bold text-ink">الاستفادات حسب المكتب</h2>
              <OfficeRankingChart rows={data.comparison} onSelectOffice={noop} color={theme.primary} outline={theme.outline} />
            </div>
            <div className="avoid-break min-w-0 rounded-xl border border-line p-4">
              {withData.length >= 2 ? (
                <>
                  <h2 className="mb-2 text-base font-bold text-ink">الاستفادات حسب الشهر</h2>
                  <PeriodsChart periods={withData} color={theme.primary} outline={theme.outline} onSelect={noop} />
                </>
              ) : (
                <>
                  <h2 className="mb-2 text-base font-bold text-ink">الذكور والإناث</h2>
                  <GenderDonutChart summary={summary} />
                </>
              )}
            </div>
          </section>

          {detail.rows.length > 0 && (
            <section aria-label={detail.title} className="avoid-break">
              <h2 className="mb-2 text-lg font-bold text-ink">{detail.title}</h2>
              <table className="w-full border-separate border-spacing-0 text-sm">
                <thead>
                  <tr className="text-xs font-bold text-ink-soft">
                    <th scope="col" className="border-b border-line-strong bg-paper-deep/60 px-3 py-2 text-start">الاسم</th>
                    <th scope="col" className="border-b border-line-strong bg-paper-deep/60 px-3 py-2 text-center">ذكور</th>
                    <th scope="col" className="border-b border-line-strong bg-paper-deep/60 px-3 py-2 text-center">إناث</th>
                    <th scope="col" className="border-b border-line-strong bg-paper-deep/60 px-3 py-2 text-center">الإجمالي</th>
                  </tr>
                </thead>
                <tbody>
                  {detail.rows.map((r) => (
                    <tr key={r.name}>
                      <th scope="row" className="border-b border-line px-3 py-1.5 text-start font-semibold text-ink">{r.name}</th>
                      <td className="border-b border-line px-3 py-1.5 text-center"><span className="num">{formatNumber(r.male)}</span></td>
                      <td className="border-b border-line px-3 py-1.5 text-center"><span className="num">{formatNumber(r.female)}</span></td>
                      <td className="border-b border-line px-3 py-1.5 text-center font-extrabold text-ink"><span className="num">{formatNumber(r.total)}</span></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </section>
          )}
        </>
      )}

      <footer className="avoid-break border-t border-line pt-3 text-xs leading-6 text-ink-muted">
        <p>
          الأرقام «استفادات مسجلة» وليست أشخاصًا فريدين: من يستفيد في أكثر من شهر يُسجَّل في كل شهر. ما لا يذكره المصدر (الجنس أو العمر) يُعرض «غير مذكور» ولا يُقدَّر.
          {data.demographic && ' فلتر الجنس أو العمر أو ذوي الاحتياجات مفعّل: لا تدخل إلا السجلات التي يذكر مصدرها هذه الفئة.'}
        </p>
        <p>
          تتوفر بيانات لـ {formatNumber(summary.projects_with_data)} من {formatNumber(summary.classified_projects)} مشروعًا مصنفًا · أُعدّ هذا التقرير بتاريخ {today}.
        </p>
      </footer>
    </>
  )
}
