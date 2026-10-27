import { ArrowRight, Printer } from 'lucide-react'
import { useEffect, useState } from 'react'
import logo from '../../assets/brand/rowad-logo.png'
import { Skeleton } from '../../components/ui/Skeleton'
import { ErrorState, NoDataState } from '../../components/ui/StateViews'
import { formatNumber, formatPercent } from '../../lib/format'
import { ORGANIZATION_THEME, themeFor, themeStyle, type Theme } from '../../lib/themes'
import type { OrgDashboard } from './types'
import { useOrganization } from './queries'
import { organizationQuery, useOrganizationFilters } from './useOrganizationFilters'

const head = 'border-b border-line-strong bg-paper-deep/60 px-3 py-2 text-start text-xs font-bold text-ink-soft'
const nameCell = 'border-b border-line px-3 py-1.5 text-start font-semibold text-ink'
const numCell = 'border-b border-line px-3 py-1.5 text-start whitespace-nowrap'

function scopeTitle(data: OrgDashboard): string {
  const scope = data.scope
  return scope.project?.name ?? scope.program?.name ?? scope.center?.name ?? scope.region?.name ?? 'كل البرامج'
}

function periodText(data: OrgDashboard): string {
  const { year, from, to } = data.scope
  if (year) return String(year)
  if (from && to) return `${from} – ${to}`
  if (from) return `من ${from}`
  if (to) return `حتى ${to}`
  return data.summary.span ?? '2017 – 2024'
}

function describeFilters(data: OrgDashboard): string[] {
  const scope = data.scope
  const title = scopeTitle(data)
  const out: string[] = []
  if (scope.program && scope.program.name !== title) out.push(`البرنامج: ${scope.program.name}`)
  if (scope.project && scope.project.name !== title) out.push(`المشروع: ${scope.project.name}`)
  if (scope.region && scope.region.name !== title) out.push(`المنطقة: ${scope.region.name}`)
  if (scope.center && scope.center.name !== title) out.push(`المركز: ${scope.center.name}`)
  return out
}

function Box({ label, value, caption }: { label: string; value: string; caption?: string }) {
  return (
    <div className="avoid-break rounded-xl border border-line px-4 py-3">
      <p className="text-sm font-bold text-ink-soft">{label}</p>
      <p className="mt-1 text-3xl font-extrabold leading-none text-ink">
        <span className="num">{value}</span>
      </p>
      {caption && <p className="mt-1.5 text-sm text-ink-muted">{caption}</p>}
    </div>
  )
}

function Num({ value, strong }: { value: number; strong?: boolean }) {
  return (
    <td className={`${numCell} ${strong ? 'font-extrabold text-ink' : 'text-ink-soft'}`}>
      <span className="num">{formatNumber(value)}</span>
    </td>
  )
}

/**
 * Print-ready report of the organization view that is open. The numbers come from the same
 * request as the dashboard, so the sheet matches the screen. "Print" saves it as PDF.
 */
export function OrganizationReportPage() {
  const { filters } = useOrganizationFilters()
  const query = useOrganization(filters)
  const data = query.data
  const theme = data?.scope.program?.code ? themeFor(data.scope.program.code) : ORGANIZATION_THEME
  const backHref = `/organization${organizationQuery(filters) ? `?${organizationQuery(filters)}` : ''}`

  useEffect(() => {
    document.title = data ? `تقرير المنظمة — ${scopeTitle(data)}` : 'تقرير بيانات المنظمة'
  }, [data])

  const [today] = useState(() => new Date().toLocaleDateString('ar-SY-u-nu-latn', { year: 'numeric', month: 'long', day: 'numeric' }))

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
        {query.error ? (
          <ErrorState message={query.error instanceof Error ? query.error.message : 'تعذّر تحميل البيانات'} onRetry={() => void query.refetch()} />
        ) : !data ? (
          <div aria-busy="true" className="space-y-4">
            <Skeleton className="h-24" />
            <Skeleton className="h-32" />
            <Skeleton className="h-64" />
          </div>
        ) : data.by_year.length === 0 ? (
          <NoDataState compact />
        ) : (
          <ReportBody data={data} theme={theme} today={today} />
        )}
      </main>
    </div>
  )
}

function ReportBody({ data, theme, today }: { data: OrgDashboard; theme: Theme; today: string }) {
  const { summary, snapshot } = data
  const filters = describeFilters(data)
  const placeFilter = Boolean(data.scope.region || data.scope.center)
  const programFilter = Boolean(data.scope.program || data.scope.project)
  const snapshotNote =
    snapshot?.basis === 'region'
      ? 'لقطة 2024 لهذه المنطقة، ولا تتأثر بفلتر السنة.'
      : snapshot?.basis === 'program'
        ? 'لقطة 2024 لهذا البرنامج أو المشروع، وليست موزعة على المناطق.'
        : 'لقطة 2024: الاحتياجات والكادر من المشاريع، والمكاتب من المناطق.'

  return (
    <>
      <header className="avoid-break border-b-2 pb-4" style={{ borderColor: theme.primary }}>
        <div className="flex items-center justify-between gap-4">
          <div className="min-w-0">
            <p className="text-sm font-bold" style={{ color: theme.dark }}>
              تقرير إحصاءات الرواد — بيانات المنظمة
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

      <section aria-label="الأرقام الرئيسية" className="grid grid-cols-2 gap-3">
        <Box label="المستفيدون" value={formatNumber(summary.beneficiaries)} caption={summary.span ?? undefined} />
        <Box label="البرامج" value={formatNumber(summary.programs)} caption="لديها مستفيدون" />
        <Box label="المشاريع" value={formatNumber(summary.projects)} caption="لديها مستفيدون" />
        <Box label="المراكز" value={formatNumber(summary.centers)} caption={`${formatNumber(summary.regions)} مناطق`} />
      </section>

      {snapshot && (
        <section aria-label="لقطة 2024" className="avoid-break">
          <h2 className="mb-2 text-lg font-bold text-ink">لقطة 2024</h2>
          <div className={`grid grid-cols-2 gap-3 ${snapshot.offices == null ? '' : 'sm:grid-cols-3'}`}>
            <Box label="ذوو الاحتياجات الخاصة" value={formatNumber(snapshot.disabled)} />
            <Box label="الكادر" value={formatNumber(snapshot.staff)} />
            {snapshot.offices != null && <Box label="المكاتب" value={formatNumber(snapshot.offices)} />}
          </div>
          <p className="mt-2 text-sm text-ink-muted">{snapshotNote}</p>
        </section>
      )}

      {data.programs.length > 0 && (
        <section aria-label="البرامج" className="avoid-break">
          <h2 className="mb-2 text-lg font-bold text-ink">البرامج</h2>
          <table className="w-full border-separate border-spacing-0 text-sm">
            <thead>
              <tr>
                <th className={head}>البرنامج</th>
                <th className={head}>المشاريع</th>
                <th className={head}>المستفيدون</th>
                <th className={head}>النسبة</th>
              </tr>
            </thead>
            <tbody>
              {data.programs.map((row) => (
                <tr key={row.slug}>
                  <th scope="row" className={nameCell}>{row.name}</th>
                  <Num value={row.projects} />
                  <Num value={row.beneficiaries} strong />
                  <td className={numCell}><span className="num">{formatPercent(row.share)}</span></td>
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      )}

      {data.by_year.length > 1 && (
        <section aria-label="السنوات" className="avoid-break">
          <h2 className="mb-2 text-lg font-bold text-ink">المستفيدون حسب السنة</h2>
          <table className="w-full border-separate border-spacing-0 text-sm">
            <thead>
              <tr>
                {data.by_year.map((row) => (
                  <th key={row.year} className={head}>{row.year}</th>
                ))}
              </tr>
            </thead>
            <tbody>
              <tr>
                {data.by_year.map((row) => (
                  <Num key={row.year} value={row.beneficiaries} strong />
                ))}
              </tr>
            </tbody>
          </table>
        </section>
      )}

      {data.regions.length > 0 && (
        <section aria-label="المناطق">
          <h2 className="mb-2 text-lg font-bold text-ink">المناطق</h2>
          <table className="w-full border-separate border-spacing-0 text-sm">
            <thead>
              <tr>
                <th className={head}>المنطقة</th>
                <th className={head}>المستفيدون</th>
                <th className={head}>المراكز</th>
                {!programFilter && (
                  <>
                    <th className={head}>المكاتب</th>
                    <th className={head}>احتياجات</th>
                    <th className={head}>الكادر</th>
                  </>
                )}
              </tr>
            </thead>
            <tbody>
              {data.regions.map((row) => (
                <tr key={row.slug}>
                  <th scope="row" className={nameCell}>{row.name}</th>
                  <Num value={row.beneficiaries} strong />
                  <Num value={row.centers} />
                  {!programFilter && (
                    <>
                      <Num value={row.offices} />
                      <Num value={row.disabled} />
                      <Num value={row.staff} />
                    </>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      )}

      {data.projects.length > 0 && (
        <section aria-label="المشاريع">
          <h2 className="mb-2 text-lg font-bold text-ink">المشاريع</h2>
          <table className="w-full border-separate border-spacing-0 text-sm">
            <thead>
              <tr>
                <th className={head}>المشروع</th>
                <th className={head}>البرنامج</th>
                <th className={head}>المستفيدون</th>
                {!placeFilter && (
                  <>
                    <th className={head}>احتياجات 2024</th>
                    <th className={head}>الكادر 2024</th>
                  </>
                )}
              </tr>
            </thead>
            <tbody>
              {data.projects.map((row) => (
                <tr key={row.slug}>
                  <th scope="row" className={nameCell}>{row.name}</th>
                  <td className="border-b border-line px-3 py-1.5 text-start text-ink-soft">{row.program_name}</td>
                  <Num value={row.beneficiaries} strong />
                  {!placeFilter && (
                    <>
                      <Num value={row.disabled} />
                      <Num value={row.staff} />
                    </>
                  )}
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      )}

      {data.centers.length > 0 && (
        <section aria-label="المراكز">
          <h2 className="mb-2 text-lg font-bold text-ink">المراكز</h2>
          <table className="w-full border-separate border-spacing-0 text-sm">
            <thead>
              <tr>
                <th className={head}>المركز</th>
                <th className={head}>المنطقة</th>
                <th className={head}>المستفيدون</th>
              </tr>
            </thead>
            <tbody>
              {data.centers.map((row) => (
                <tr key={row.slug}>
                  <th scope="row" className={nameCell}>{row.name}</th>
                  <td className="border-b border-line px-3 py-1.5 text-start text-ink-soft">{row.region_name}</td>
                  <Num value={row.beneficiaries} strong />
                </tr>
              ))}
            </tbody>
          </table>
        </section>
      )}

      <footer className="avoid-break border-t border-line pt-3 text-xs leading-6 text-ink-muted">
        <p>أرقام المستفيدين من ورقة المراكز، وهي سجل المنظمة من 2017 حتى 2024. ذوو الاحتياجات والكادر والمكاتب لقطة 2024 وليست تفصيلاً سنوياً.</p>
        <p>أُعدّ هذا التقرير بتاريخ {today}، ويعرض نفس النطاق الظاهر في اللوحة.</p>
      </footer>
    </>
  )
}
