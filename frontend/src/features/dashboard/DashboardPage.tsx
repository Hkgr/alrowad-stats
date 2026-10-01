import { lazy, Suspense, useCallback, useEffect, useMemo } from 'react'
import { Card } from '../../components/ui/Card'
import { Skeleton } from '../../components/ui/Skeleton'
import { EmptyState, ErrorState } from '../../components/ui/StateViews'
import { useFullscreen } from '../../hooks/useFullscreen'
import { AppShell } from '../../layout/AppShell'
import { Header } from '../../layout/Header'
import { FilterBar } from './components/FilterBar'
import { KpiCards, KpiCardsSkeleton } from './components/KpiCards'
import { OfficesTable } from './components/OfficesTable'
import { ScopePanel } from './components/ScopePanel'
import { useDashboard, useFilterOptions, useInstitutions } from './queries'
import { useDashboardFilters } from './useDashboardFilters'

// ECharts is heavy: load it in its own chunk, after the first paint of cards and filters.
const OfficeRankingChart = lazy(() =>
  import('./components/OfficeRankingChart').then((m) => ({ default: m.OfficeRankingChart })),
)
const GenderDonutChart = lazy(() =>
  import('./components/GenderDonutChart').then((m) => ({ default: m.GenderDonutChart })),
)
const StackedGenderChart = lazy(() =>
  import('./components/StackedGenderChart').then((m) => ({ default: m.StackedGenderChart })),
)

function ChartSkeleton({ className = 'h-80' }: { className?: string }) {
  return <Skeleton className={`w-full ${className}`} />
}

export function DashboardPage() {
  const { filters: urlFilters, setFilter, toggleOffice, reset, update, hasActiveFilters } = useDashboardFilters()
  const institutions = useInstitutions()
  const fullscreen = useFullscreen()

  // The institution always comes from the URL or the API list; nothing is hard-coded.
  const defaultInstitution = institutions.data?.[0]?.slug ?? null
  const filters = useMemo(
    () => ({ ...urlFilters, institution: urlFilters.institution ?? defaultInstitution }),
    [urlFilters, defaultInstitution],
  )

  useEffect(() => {
    if (!urlFilters.institution && defaultInstitution) update({ institution: defaultInstitution }, { replace: true })
  }, [urlFilters.institution, defaultInstitution, update])

  const filterOptions = useFilterOptions(filters)
  const dashboard = useDashboard(filters)

  const resetAll = useCallback(() => update({ institution: null, project: null, period: null, office: null }), [update])

  // "F" toggles presentation mode unless the user is typing.
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement | null
      const typing = target && (['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName) || target.isContentEditable)
      if (!typing && !event.ctrlKey && !event.metaKey && !event.altKey && event.key.toLowerCase() === 'f') fullscreen.toggle()
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [fullscreen])

  const data = dashboard.data
  const refreshing = dashboard.isFetching && !dashboard.isPending
  const institutionName = data?.institution.name ?? institutions.data?.find((i) => i.slug === filters.institution)?.name ?? null

  const scopeLabel = data?.has_data
    ? [
        data.scope.projects.map((p) => p.name).join('، '),
        data.scope.periods.map((p) => p.label).join('، '),
      ]
        .filter(Boolean)
        .join(' · ')
    : null

  const loadingFirst = institutions.isPending || (Boolean(filters.institution) && dashboard.isPending)
  const failure = institutions.error ?? dashboard.error

  return (
    <AppShell presenting={fullscreen.active}>
      {({ openMenu }) => (
        <>
          <Header
            institutionName={institutionName}
            scopeLabel={scopeLabel}
            fullscreen={fullscreen}
            onOpenMenu={openMenu}
            onReset={reset}
            canReset={hasActiveFilters}
          />

          <div className="mt-5 space-y-5" aria-busy={refreshing || loadingFirst}>
            <FilterBar
              institutions={institutions.data ?? []}
              options={filterOptions.data}
              dashboard={data}
              filters={filters}
              onChange={setFilter}
              onReset={reset}
              hasActiveFilters={hasActiveFilters}
              busy={refreshing}
            />

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
              <>
                <KpiCardsSkeleton />
                <div className="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
                  <Card>
                    <ChartSkeleton className="h-[22rem]" />
                  </Card>
                  <Card>
                    <ChartSkeleton className="h-[22rem]" />
                  </Card>
                </div>
              </>
            ) : (
              <div className={`space-y-5 transition-opacity duration-300 ${refreshing ? 'opacity-60' : 'opacity-100'}`}>
                <KpiCards summary={data.summary} unitLabel={data.measure.unit_label} />

                {!data.has_data ? (
                  <Card>
                    <EmptyState onReset={reset} />
                  </Card>
                ) : (
                  <>
                    <div className="grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
                      <Card
                        id="ranking"
                        title="ترتيب المكاتب"
                        description="إجمالي الاستفادات المسجلة لكل مكتب. اضغط على عمود لتصفية المكتب."
                      >
                        <Suspense fallback={<ChartSkeleton className="h-[22rem]" />}>
                          <OfficeRankingChart rows={data.comparison} onSelectOffice={toggleOffice} />
                        </Suspense>
                      </Card>
                      <Card id="gender" title="توزيع الذكور والإناث" description="حسب الفلاتر المحددة حاليًا.">
                        <Suspense fallback={<ChartSkeleton className="h-[22rem]" />}>
                          <GenderDonutChart summary={data.summary} />
                        </Suspense>
                      </Card>
                    </div>

                    <Card
                      id="stacked"
                      title="مقارنة الجنسين بين المكاتب"
                      description="الذكور والإناث في كل مكتب ضمن عمود واحد مكدس."
                    >
                      <Suspense fallback={<ChartSkeleton className="h-[26rem]" />}>
                        <StackedGenderChart rows={data.comparison} onSelectOffice={toggleOffice} />
                      </Suspense>
                    </Card>

                    <Card id="table" title="التفاصيل حسب المكتب" description="قابل للبحث والفرز. اضغط على اسم مكتب لتصفية العرض.">
                      <OfficesTable
                        rows={data.offices}
                        summary={data.summary}
                        selectedOffice={filters.office}
                        onSelectOffice={toggleOffice}
                      />
                    </Card>
                  </>
                )}

                <Card id="scope" title="نطاق البيانات ومصدرها" description="ما الذي تغطيه الأرقام المعروضة، ومن أين جاءت.">
                  <ScopePanel dashboard={data} availablePeriods={filterOptions.data?.periods.length ?? data.scope.periods.length} />
                </Card>
              </div>
            )}
          </div>

          <footer className="mt-8 text-xs text-ink-muted">
            <span>إحصاءات الرواد · المرحلة الأولى: عرض وتحليل</span>
          </footer>
        </>
      )}
    </AppShell>
  )
}
