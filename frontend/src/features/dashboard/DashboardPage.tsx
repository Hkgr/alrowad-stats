import { lazy, Suspense, useCallback, useEffect, useMemo, useRef } from 'react'
import { sectorColor } from '../../components/charts/chartTheme'
import { Card } from '../../components/ui/Card'
import { Skeleton } from '../../components/ui/Skeleton'
import { ErrorState, NoDataState } from '../../components/ui/StateViews'
import { TabPanel, Tabs } from '../../components/ui/Tabs'
import { useFullscreen } from '../../hooks/useFullscreen'
import { introProps, useIntro } from '../../hooks/useIntro'
import { useReducedMotion } from '../../hooks/useReducedMotion'
import { SiteHeader } from '../../layout/SiteHeader'
import type { BreakdownView, Dashboard } from '../../types/api'
import { ExplorerBar } from './components/ExplorerBar'
import { FiguresBand, FiguresBandSkeleton } from './components/FiguresBand'
import { OfficesTable } from './components/OfficesTable'
import { ProjectSearch } from './components/ProjectSearch'
import { ProjectsPanel } from './components/ProjectsPanel'
import { ScopeHeading } from './components/ScopeHeading'
import { SectorCards } from './components/SectorCards'
import { useDashboard, useFilterOptions, useInstitutions } from './queries'
import { useDashboardFilters } from './useDashboardFilters'

// ECharts is heavy: it loads in its own chunk after the figures are on screen.
const OfficeRankingChart = lazy(() => import('./components/OfficeRankingChart').then((m) => ({ default: m.OfficeRankingChart })))
const GenderDonutChart = lazy(() => import('./components/GenderDonutChart').then((m) => ({ default: m.GenderDonutChart })))
const StackedGenderChart = lazy(() => import('./components/StackedGenderChart').then((m) => ({ default: m.StackedGenderChart })))
const ComparisonBarChart = lazy(() => import('./components/ComparisonBarChart').then((m) => ({ default: m.ComparisonBarChart })))
const PeriodsPanel = lazy(() => import('./components/PeriodsPanel').then((m) => ({ default: m.PeriodsPanel })))

const chartFallback = (height = 'h-80') => <Skeleton className={`w-full ${height}`} />

export function DashboardPage() {
  const nav = useDashboardFilters()
  const { filters: urlFilters, view: urlView, hasActiveFilters, update } = nav
  const institutions = useInstitutions()
  const fullscreen = useFullscreen()
  const reducedMotion = useReducedMotion()

  // The institution always comes from the URL or the API list; nothing is hard-coded.
  const defaultInstitution = institutions.data?.[0]?.slug ?? null
  const filters = useMemo(
    () => ({ ...urlFilters, institution: urlFilters.institution ?? defaultInstitution }),
    [urlFilters, defaultInstitution],
  )

  useEffect(() => {
    if (!urlFilters.institution && defaultInstitution) update({ institution: defaultInstitution }, { replace: true })
  }, [urlFilters.institution, defaultInstitution, update])

  const options = useFilterOptions(filters)
  const dashboard = useDashboard(filters)
  const data = dashboard.data
  const intro = useIntro(Boolean(data))
  const refreshing = dashboard.isFetching && !dashboard.isPending

  // "F" toggles presentation mode unless the user is typing.
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement | null
      const typing = target && (['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName) || target.isContentEditable)
      if (!typing && !event.ctrlKey && !event.metaKey && !event.altKey && event.key.toLowerCase() === 'f') void fullscreen.toggle()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [fullscreen])

  // Moving between levels keeps the scroll position, unless the new content starts above the viewport.
  const contentRef = useRef<HTMLDivElement>(null)
  const level = data?.level
  const previousLevel = useRef(level)
  useEffect(() => {
    if (previousLevel.current && level && previousLevel.current !== level) {
      const top = contentRef.current?.getBoundingClientRect().top ?? 0
      if (top < 80) contentRef.current?.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'start' })
    }
    previousLevel.current = level
  }, [level, reducedMotion])

  const resetAll = useCallback(
    () => update({ institution: null, sector: null, project: null, period: null, office: null, view: null }),
    [update],
  )

  const failure = institutions.error ?? dashboard.error
  const loadingFirst = institutions.isPending || (Boolean(filters.institution) && dashboard.isPending)

  return (
    <div className="min-h-screen">
      <a
        href="#content"
        className="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-ink focus:px-4 focus:py-2 focus:font-bold focus:text-white"
      >
        تخطي إلى المحتوى
      </a>

      <SiteHeader
        {...introProps(intro, 0)}
        periodLabel={data ? (data.filters.period?.label ?? 'كل الفترات') : null}
        fullscreen={fullscreen}
        search={
          <ProjectSearch
            projects={options.data?.projects ?? []}
            onSelect={(project) => nav.goProject(project.slug, project.sector?.slug ?? null)}
          />
        }
      />

      <main id="content" tabIndex={-1} className="mx-auto w-full max-w-[1440px] space-y-5 px-4 pb-16 pt-5 outline-none sm:px-6 lg:px-8">
        <div {...introProps(intro, 1)}>
          <ExplorerBar
            filters={filters}
            dashboard={data}
            options={options.data}
            onOverview={nav.goOverview}
            onSector={nav.goSector}
            onPeriod={nav.setPeriod}
            onOffice={nav.toggleOffice}
            onClearProject={() => update({ project: null })}
            onReset={nav.reset}
            hasActiveFilters={hasActiveFilters}
            busy={refreshing}
          />
        </div>

        {failure ? (
          <Card>
            <ErrorState
              message={failure.message}
              onRetry={() => {
                void institutions.refetch()
                void dashboard.refetch()
              }}
              onReset={resetAll}
            />
          </Card>
        ) : loadingFirst || !data ? (
          <div className="space-y-5" aria-busy="true">
            <div className="grid grid-cols-2 gap-3 lg:grid-cols-5">
              {Array.from({ length: 5 }, (_, i) => (
                <Skeleton key={i} className="h-36" />
              ))}
            </div>
            <FiguresBandSkeleton />
            <Skeleton className="h-80" />
          </div>
        ) : (
          <div
            ref={contentRef}
            className={`scroll-mt-28 space-y-5 transition-opacity duration-200 ${refreshing ? 'opacity-60' : ''}`}
            aria-busy={refreshing}
          >
            {data.level !== 'project' && (
              <div {...introProps(intro, 1)}>
                <SectorCards sectors={data.sectors} onSelect={nav.goSector} />
              </div>
            )}

            {/* A short fade marks a change of level; the first-load entrance is not replayed. */}
            <div key={data.level} className={intro ? '' : 'animate-fade'}>
              <LevelContent data={data} intro={intro} view={urlView ?? 'projects'} nav={nav} />
            </div>
          </div>
        )}
      </main>
    </div>
  )
}

interface LevelProps {
  data: Dashboard
  intro: boolean
  view: BreakdownView
  nav: ReturnType<typeof useDashboardFilters>
}

/** Heading, figures, charts and breakdown for the current level (overview, sector or project). */
function LevelContent({ data, intro, view, nav }: LevelProps) {
  const isProject = data.level === 'project'
  const sector = data.active_sector
  const sectorIndex = (slug: string | undefined) => data.sectors.findIndex((s) => s.slug === slug)
  const activeColor = sector ? sectorColor(sectorIndex(sector.slug)) : null

  const back =
    data.level === 'project'
      ? sector
        ? { label: `رجوع إلى ${sector.name}`, go: () => nav.goSector(sector.slug) }
        : { label: 'رجوع إلى النظرة العامة', go: nav.goOverview }
      : data.level === 'sector'
        ? { label: 'رجوع إلى النظرة العامة', go: nav.goOverview }
        : null

  const sectorsWithData = data.sectors.filter((s) => s.has_data)
  const openProject = (project: { slug: string; sector: { slug: string } | null }) =>
    nav.goProject(project.slug, project.sector?.slug ?? null)
  const panel: BreakdownView = isProject ? 'offices' : view
  const charts = introProps(intro, 3)

  // A plain element (not a nested component) so charts are not re-created on every render.
  const breakdownBody =
    panel === 'projects' ? (
      <ProjectsPanel projects={data.projects} showSector={data.level === 'overview'} onOpen={openProject} />
    ) : data.has_data ? (
      <div className="space-y-5">
        <Suspense fallback={chartFallback()}>
          <StackedGenderChart rows={data.comparison} onSelectOffice={nav.toggleOffice} />
        </Suspense>
        <OfficesTable
          rows={data.offices}
          summary={data.summary}
          selectedOffice={data.filters.office?.slug ?? null}
          onSelectOffice={nav.toggleOffice}
        />
      </div>
    ) : (
      <NoDataState compact />
    )

  return (
    <div className="space-y-5">
      <div {...introProps(intro, 2)}>
        <ScopeHeading dashboard={data} sectorColor={activeColor} onBack={back?.go ?? null} backLabel={back?.label ?? null} />
      </div>

      <div {...introProps(intro, 2)}>
        <FiguresBand summary={data.summary} />
      </div>

      {data.has_data ? (
        <div className={`grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] ${charts.className}`} style={charts.style}>
          <Card id="offices-rank" title="الاستفادات حسب المكتب" hint="اضغط على مكتب لتصفيته">
            <Suspense fallback={chartFallback()}>
              <OfficeRankingChart rows={data.comparison} onSelectOffice={nav.toggleOffice} />
            </Suspense>
          </Card>
          <Card id="gender" title="الذكور والإناث">
            <Suspense fallback={chartFallback()}>
              <GenderDonutChart summary={data.summary} />
            </Suspense>
          </Card>
        </div>
      ) : (
        <Card className={charts.className} style={charts.style}>
          <NoDataState compact onReset={nav.hasActiveFilters ? nav.reset : undefined} />
        </Card>
      )}

      {data.level === 'overview' && sectorsWithData.length >= 2 && (
        <Card id="sectors-compare" title="الاستفادات حسب المسار">
          <Suspense fallback={chartFallback('h-60')}>
            <ComparisonBarChart
              ariaLabel="مقارنة الاستفادات المسجلة بين المسارات"
              items={sectorsWithData.map((s) => ({
                key: s.slug,
                name: s.name,
                value: s.total ?? 0,
                male: s.male ?? 0,
                female: s.female ?? 0,
                color: sectorColor(sectorIndex(s.slug)),
              }))}
              onSelect={nav.goSector}
            />
          </Suspense>
        </Card>
      )}

      {isProject && data.periods.length > 0 && (
        <Card id="periods" title="الفترات المتاحة">
          <Suspense fallback={chartFallback('h-12')}>
            <PeriodsPanel
              periods={data.periods}
              selected={data.filters.period?.key ?? null}
              onSelect={(key) => nav.setPeriod(data.filters.period?.key === key ? null : key)}
            />
          </Suspense>
        </Card>
      )}

      <Card
        id="breakdown"
        title={isProject ? 'التفاصيل حسب المكتب' : undefined}
        actions={
          isProject ? undefined : (
            <Tabs<BreakdownView>
              label="عرض التفاصيل"
              idPrefix="breakdown"
              value={view}
              onChange={nav.setView}
              items={[
                { value: 'projects', label: 'المشاريع', count: data.projects.length },
                { value: 'offices', label: 'المكاتب', count: data.offices.length },
              ]}
            />
          )
        }
      >
        {isProject ? (
          breakdownBody
        ) : (
          <TabPanel idPrefix="breakdown" value={panel}>
            {breakdownBody}
          </TabPanel>
        )}
      </Card>
    </div>
  )
}
