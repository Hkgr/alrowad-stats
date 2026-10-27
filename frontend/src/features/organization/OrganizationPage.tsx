import { ChevronLeft, Printer } from 'lucide-react'
import { useEffect } from 'react'
import { EmojiBackdrop } from '../../components/background/EmojiBackdrop'
import { Card } from '../../components/ui/Card'
import { ErrorState, NoDataState } from '../../components/ui/StateViews'
import { useBackdropMotion } from '../../hooks/useBackdropMotion'
import { useFullscreen } from '../../hooks/useFullscreen'
import { useReducedMotion } from '../../hooks/useReducedMotion'
import { introProps, useIntro } from '../../hooks/useIntro'
import { ORGANIZATION_THEME, themeFor, themeStyle } from '../../lib/themes'
import { SiteHeader } from '../../layout/SiteHeader'
import { RankChart, YearChart } from './components/OrgCharts'
import { OrgFigures, OrgFiguresSkeleton } from './components/OrgFigures'
import { OrgRail } from './components/OrgRail'
import { CentersTable, ProjectsTable, RegionsTable } from './components/OrgTables'
import { ProgramCards } from './components/ProgramCards'
import { useOrganization } from './queries'
import { organizationQuery, useOrganizationFilters } from './useOrganizationFilters'

export function OrganizationPage() {
  const nav = useOrganizationFilters()
  const query = useOrganization(nav.filters)
  const data = query.data
  const fullscreen = useFullscreen()
  const reducedMotion = useReducedMotion()
  const backdrop = useBackdropMotion()
  const intro = useIntro(Boolean(data))
  const theme = data?.scope.program?.code ? themeFor(data.scope.program.code) : ORGANIZATION_THEME
  const placeFilter = Boolean(nav.filters.region || nav.filters.center)
  const programFilter = Boolean(nav.filters.program || nav.filters.project)

  useEffect(() => {
    document.title = 'بيانات المنظمة — إحصاءات الرواد'
  }, [])

  const trail = [
    { key: 'org', label: 'المنظمة', go: nav.hasActiveFilters ? nav.reset : undefined },
    data?.scope.program ? { key: 'program', label: data.scope.program.name, go: () => nav.setProgram(data.scope.program!.slug) } : null,
    data?.scope.project ? { key: 'project', label: data.scope.project.name } : null,
    data?.scope.region ? { key: 'region', label: data.scope.region.name, go: () => nav.setRegion(data.scope.region!.slug) } : null,
    data?.scope.center ? { key: 'center', label: data.scope.center.name } : null,
  ].filter((item) => item !== null)

  return (
    <div className="relative min-h-screen" style={{ ...themeStyle(theme), background: `linear-gradient(180deg, ${theme.light} 0, var(--color-paper) 34rem)` }}>
      <EmojiBackdrop symbols={theme.backdrop} animate={backdrop.enabled && !reducedMotion} />
      <div className="relative z-10">
        <a href="#content" className="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-ink focus:px-4 focus:py-2 focus:font-bold focus:text-white">
          تخطي إلى المحتوى
        </a>
        <SiteHeader {...introProps(intro, 0)} context="بيانات المنظمة" periodLabel={data?.summary.span ?? null} fullscreen={fullscreen} backdrop={backdrop} />

        <main id="content" tabIndex={-1} className="grid w-full items-start pb-16 outline-none lg:grid-cols-[20.5rem_minmax(0,1fr)] lg:pb-0">
          <div className="filter-rail sticky top-0 z-20 max-h-[calc(100dvh-0.75rem)] self-start overflow-y-auto bg-paper/95 px-4 pt-4 backdrop-blur-md sm:px-6 lg:top-[6.25rem] lg:h-[calc(100dvh-6.25rem)] lg:max-h-[calc(100dvh-6.25rem)] lg:border-e lg:border-line lg:bg-white lg:px-8 lg:py-5 lg:backdrop-blur-none">
            <div {...introProps(intro, 1)}>
              <OrgRail
                filters={nav.filters}
                data={data}
                busy={query.isFetching && !query.isPending}
                hasActiveFilters={nav.hasActiveFilters}
                onYear={nav.setYear}
                onRange={nav.update}
                onProgram={nav.setProgram}
                onProject={(slug, programSlug) => nav.setProject(slug, programSlug)}
                onRegion={nav.setRegion}
                onCenter={(slug, regionSlug) => nav.setCenter(slug, regionSlug)}
                onReset={nav.reset}
              />
            </div>
          </div>

          <div className="min-w-0 space-y-5 px-4 pt-5 sm:px-6 lg:px-8 lg:pt-6">
            <div className={`flex flex-wrap items-center justify-between gap-x-4 gap-y-2 ${introProps(intro, 1).className}`} style={introProps(intro, 1).style}>
            <nav aria-label="مسار التصفح">
              <ol className="flex flex-wrap items-center gap-0.5">
                {trail.map((item, index) => (
                  <li key={item.key} className="flex min-w-0 items-center gap-0.5">
                    {index > 0 && <ChevronLeft className="size-4 shrink-0 text-ink-muted" aria-hidden="true" />}
                    {item.go && index < trail.length - 1 ? (
                      <button type="button" onClick={item.go} className="inline-flex min-h-9 cursor-pointer items-center rounded-lg px-2 text-sm font-semibold text-ink-soft hover:bg-white hover:text-ink">
                        {item.label}
                      </button>
                    ) : (
                      <span className="px-2 text-sm font-extrabold text-ink">{item.label}</span>
                    )}
                  </li>
                ))}
              </ol>
            </nav>
            <a
              className="inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg border border-line-strong bg-white px-3 text-sm font-semibold text-ink hover:border-brand hover:text-brand-ink"
              href={`/organization/report${organizationQuery(nav.filters) ? `?${organizationQuery(nav.filters)}` : ''}`}
              target="_blank"
              rel="noopener"
            >
              <Printer className="size-4" aria-hidden="true" />
              تقرير PDF
            </a>
            </div>

            {query.error ? (
              <ErrorState message={query.error instanceof Error ? query.error.message : 'تعذّر تحميل البيانات'} onRetry={() => void query.refetch()} onReset={nav.reset} />
            ) : query.isPending ? (
              <OrgFiguresSkeleton />
            ) : data && data.by_year.length === 0 ? (
              <NoDataState onReset={nav.hasActiveFilters ? nav.reset : undefined} />
            ) : data ? (
              <>
                <OrgFigures data={data} />
                {data.programs.length > 0 && <ProgramCards programs={data.programs} onSelect={nav.setProgram} />}
                <div className="grid gap-5 xl:grid-cols-2">
                  <Card title="المستفيدون حسب السنة" hint="اضغط سنة لتثبيتها">
                    <YearChart years={data.by_year} color={theme.primary} onSelect={(year) => nav.setYear(nav.filters.year === year ? null : year)} />
                  </Card>
                  {data.regions.length > 0 && (
                    <Card title="المناطق" hint="اضغط منطقة لفتحها">
                      <RankChart
                        items={data.regions.map((row) => ({ key: row.slug, name: row.name, value: row.beneficiaries, selected: row.selected }))}
                        color={theme.primary}
                        ariaLabel="المستفيدون حسب المنطقة"
                        onSelect={(slug) => nav.setRegion(nav.filters.region === slug ? null : slug)}
                      />
                    </Card>
                  )}
                </div>
                <Card title="المشاريع" hint={placeFilter ? 'المستفيدون في المنطقة أو المركز المختار' : 'الاحتياجات والكادر لقطة 2024 على مستوى المشروع'}>
                  <ProjectsTable rows={data.projects} showSnapshot={!placeFilter} onSelect={(slug, programSlug) => nav.setProject(slug, programSlug)} />
                </Card>
                <div className="grid gap-5 xl:grid-cols-2">
                  <Card title="المناطق" hint={programFilter ? 'المستفيدون ضمن البرنامج أو المشروع' : 'المكاتب والاحتياجات والكادر لقطة 2024'}>
                    <RegionsTable rows={data.regions} showSnapshot={!programFilter} onSelect={nav.setRegion} />
                  </Card>
                  <Card title="المراكز" hint="سجل المستفيدين محفوظ على مستوى المركز">
                    <CentersTable rows={data.centers} onSelect={(slug, regionSlug) => nav.setCenter(slug, regionSlug)} />
                  </Card>
                </div>
              </>
            ) : null}
          </div>
        </main>
      </div>
    </div>
  )
}
