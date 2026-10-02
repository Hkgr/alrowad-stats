import { lazy, Suspense, useCallback, useEffect, useMemo, useRef } from 'react'
import { EmojiBackdrop } from '../../components/background/EmojiBackdrop'
import { Card } from '../../components/ui/Card'
import { Skeleton } from '../../components/ui/Skeleton'
import { ErrorState, NoDataState } from '../../components/ui/StateViews'
import { TabPanel, Tabs } from '../../components/ui/Tabs'
import { useBackdropMotion } from '../../hooks/useBackdropMotion'
import { useFullscreen } from '../../hooks/useFullscreen'
import { introProps, useIntro } from '../../hooks/useIntro'
import { useReducedMotion } from '../../hooks/useReducedMotion'
import { formatNumber } from '../../lib/format'
import { INSTITUTION_THEME, themeFor, themeStyle, type Theme } from '../../lib/themes'
import { SiteHeader } from '../../layout/SiteHeader'
import type { BreakdownView, Dashboard } from '../../types/api'
import { ActivitiesPanel } from './components/ActivitiesPanel'
import { ExplorerBar } from './components/ExplorerBar'
import { FiguresBand, FiguresBandSkeleton } from './components/FiguresBand'
import { OfficesTable } from './components/OfficesTable'
import { OtherMeasures } from './components/OtherMeasures'
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
const PeriodsChart = lazy(() => import('./components/PeriodsChart').then((m) => ({ default: m.PeriodsChart })))

const chartFallback = (height = 'h-80') => <Skeleton className={`w-full ${height}`} />

/** Theme of the current scope: the active track's, the unclassified one, or the institution's. */
function scopeTheme(data: Dashboard | undefined): Theme {
  const sector = data?.active_sector
  if (!sector) return INSTITUTION_THEME
  return themeFor(sector.code, sector.slug === 'unclassified')
}

export function DashboardPage() {
  const nav = useDashboardFilters()
  const { filters: urlFilters, view: urlView, hasActiveFilters, update } = nav
  const institutions = useInstitutions()
  const fullscreen = useFullscreen()
  const reducedMotion = useReducedMotion()
  const backdrop = useBackdropMotion()

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
  const theme = scopeTheme(data)

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
    () => update({ institution: null, sector: null, project: null, main_activity: null, sub_activity: null, period: null, office: null, view: null }),
    [update],
  )

  const failure = institutions.error ?? dashboard.error
  const loadingFirst = institutions.isPending || (Boolean(filters.institution) && dashboard.isPending)

  return (
    <div
      className="relative min-h-screen transition-[background] duration-500"
      style={{ ...themeStyle(theme), background: `linear-gradient(180deg, ${theme.light} 0, var(--color-paper) 34rem)` }}
    >
      <EmojiBackdrop symbols={theme.backdrop} animate={backdrop.enabled && !reducedMotion} />

      <div className="relative z-10">
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
          backdrop={backdrop}
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
              onProject={nav.goProject}
              onMainActivity={nav.goMainActivity}
              onSubActivity={nav.goSubActivity}
              onPeriod={nav.setPeriod}
              onOffice={nav.toggleOffice}
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
              {(data.level === 'overview' || data.level === 'sector') && (
                <div {...introProps(intro, 1)}>
                  <SectorCards sectors={data.sectors} unclassified={data.unclassified} onSelect={nav.goSector} />
                </div>
              )}

              {/* A short fade marks a change of level; the first-load entrance is not replayed. */}
              <div key={data.level} className={intro ? '' : 'animate-fade'}>
                <LevelContent data={data} intro={intro} view={urlView ?? 'projects'} nav={nav} theme={theme} />
              </div>
            </div>
          )}
        </main>
      </div>
    </div>
  )
}

interface LevelProps {
  data: Dashboard
  intro: boolean
  view: BreakdownView
  nav: ReturnType<typeof useDashboardFilters>
  theme: Theme
}

/** Heading, figures, activities, charts and breakdown for the current level. */
function LevelContent({ data, intro, view, nav, theme }: LevelProps) {
  const { level, activities } = data
  const sector = data.active_sector
  const inProject = level === 'project' || level === 'main_activity' || level === 'sub_activity'
  const sectorSlug = sector?.slug ?? null

  const back = {
    overview: null,
    sector: { label: 'رجوع إلى النظرة العامة', go: nav.goOverview },
    project: sector ? { label: `رجوع إلى ${sector.name}`, go: () => nav.goSector(sector.slug) } : { label: 'رجوع إلى النظرة العامة', go: nav.goOverview },
    main_activity: { label: `رجوع إلى ${data.filters.project?.name ?? 'المشروع'}`, go: () => nav.goMainActivity(null) },
    sub_activity: { label: `رجوع إلى ${data.filters.main_activity?.name ?? 'النشاط الرئيسي'}`, go: () => nav.goSubActivity(null) },
  }[level]

  const sectorsWithData = data.sectors.filter((s) => s.has_data)
  const periodsWithData = data.periods.filter((p) => p.has_data)
  const panel: BreakdownView = inProject ? 'offices' : view
  const charts = introProps(intro, 3)

  const breakdownBody =
    panel === 'projects' ? (
      <ProjectsPanel
        projects={data.projects}
        showSector={level === 'overview'}
        onOpen={(project) => nav.goProject(project.slug, project.sector?.slug ?? sectorSlug)}
      />
    ) : data.has_data ? (
      <div className="space-y-5">
        <Suspense fallback={chartFallback()}>
          <StackedGenderChart rows={data.comparison} onSelectOffice={nav.toggleOffice} />
        </Suspense>
        <OfficesTable rows={data.offices} summary={data.summary} selectedOffice={data.filters.office?.slug ?? null} onSelectOffice={nav.toggleOffice} />
      </div>
    ) : (
      <NoDataState compact />
    )

  return (
    <div className="space-y-5">
      <div {...introProps(intro, 2)}>
        <ScopeHeading dashboard={data} theme={theme} onBack={back?.go ?? null} backLabel={back?.label ?? null} />
      </div>

      <div {...introProps(intro, 2)} >
        <FiguresBand summary={data.summary} />
      </div>

      <OtherMeasures measures={data.other_measures} />

      {level === 'project' && (
        <Card id="main-activities" title="الأنشطة الرئيسية" hint={activities.main.length ? 'اضغط على نشاط لعرض أنشطته الفرعية' : undefined}>
          <ActivitiesPanel kind="main" rows={activities.main} without={activities.without_main} theme={theme} onOpen={nav.goMainActivity} />
        </Card>
      )}

      {level === 'project' && activities.main.length === 0 && activities.categories.length >= 2 && (
        <Card id="categories" title="حسب تصنيف ملف المشروع" hint="التصنيف كما ورد في ملف المشروع (Level 1)، وليس أنشطة رئيسية">
          <ul className="grid gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
            {activities.categories.map((c) => (
              <li key={c.name} className="flex items-baseline justify-between gap-3 border-b border-line py-1.5 text-sm">
                <span className="text-ink">{c.name}</span>
                <span className="num font-bold text-ink">{formatNumber(c.total)}</span>
              </li>
            ))}
          </ul>
        </Card>
      )}

      {level === 'main_activity' && (
        <Card id="sub-activities" title="الأنشطة الفرعية" hint={activities.sub.length ? 'اضغط على نشاط فرعي لعرض إحصاءاته' : undefined}>
          <ActivitiesPanel kind="sub" rows={activities.sub} without={activities.without_sub} theme={theme} onOpen={nav.goSubActivity} />
        </Card>
      )}

      {data.has_data ? (
        <div className={`grid gap-5 xl:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)] ${charts.className}`} style={charts.style}>
          <Card id="offices-rank" title="الاستفادات حسب المكتب" hint="اضغط على مكتب لتصفيته">
            <Suspense fallback={chartFallback()}>
              <OfficeRankingChart rows={data.comparison} onSelectOffice={nav.toggleOffice} color={theme.primary} outline={theme.outline} />
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

      {periodsWithData.length >= 2 && (
        <Card id="periods" title="الاستفادات حسب الشهر" hint="اضغط على شهر لتصفيته">
          <Suspense fallback={chartFallback('h-72')}>
            <PeriodsChart
              periods={periodsWithData}
              color={theme.primary}
              outline={theme.outline}
              onSelect={(key) => nav.setPeriod(data.filters.period?.key === key ? null : key)}
            />
          </Suspense>
        </Card>
      )}

      {level === 'overview' && sectorsWithData.length >= 2 && (
        <Card id="sectors-compare" title="الاستفادات حسب المسار">
          <Suspense fallback={chartFallback('h-60')}>
            <ComparisonBarChart
              ariaLabel="مقارنة الاستفادات المسجلة بين المسارات"
              items={sectorsWithData.map((s) => ({
                key: s.slug,
                name: `${themeFor(s.code).emoji} ${s.name}`,
                value: s.total ?? 0,
                male: s.male ?? 0,
                female: s.female ?? 0,
                color: themeFor(s.code).primary,
              }))}
              onSelect={nav.goSector}
            />
          </Suspense>
        </Card>
      )}

      <Card
        id="breakdown"
        title={inProject ? 'التفاصيل حسب المكتب' : undefined}
        actions={
          inProject ? undefined : (
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
        {inProject ? (
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
