import { lazy, Suspense } from 'react'
import { Card } from '../../../components/ui/Card'
import { Skeleton } from '../../../components/ui/Skeleton'
import { ErrorState } from '../../../components/ui/StateViews'
import type { Dashboard, FilterState } from '../../../types/api'
import { useAnalytics } from '../queries'
import { CoursesPanel } from './CoursesPanel'
import { KnowledgePanel } from './KnowledgePanel'
import { ProjectOfficeMatrix } from './ProjectOfficeMatrix'

const MonthlyStackChart = lazy(() => import('./MonthlyStackChart').then((m) => ({ default: m.MonthlyStackChart })))
const PyramidChart = lazy(() => import('./PyramidChart').then((m) => ({ default: m.PyramidChart })))

interface Props {
  filters: FilterState
  dashboard: Dashboard
  onOpenProject: (slug: string) => void
  onSelectOffice: (slug: string) => void
  onSelectPeriod: (key: string | null) => void
}

/**
 * Deeper readings of the current scope, loaded after the main figures: monthly stack, project ×
 * office matrix, age × gender, courses and specialties. A part with nothing to show is left out.
 */
export function AnalyticsSection({ filters, dashboard, onOpenProject, onSelectOffice, onSelectPeriod }: Props) {
  const query = useAnalytics(filters)
  const a = query.data

  if (query.error && !a) {
    return (
      <Card title="تحليلات إضافية">
        <ErrorState message={query.error.message} onRetry={() => void query.refetch()} />
      </Card>
    )
  }
  if (!a) {
    return (
      <div aria-busy="true" className="space-y-5">
        <Skeleton className="h-72" />
        <Skeleton className="h-56" />
      </div>
    )
  }

  const fallback = (height: string) => <Skeleton className={`w-full ${height}`} />
  const selectedPeriod = dashboard.filters.period?.key ?? null
  const stack = a.monthly_stack && a.monthly_stack.periods.length >= 2 ? a.monthly_stack : null

  return (
    <div className="space-y-5">
      {stack && (
        <Card
          id="monthly-stack"
          title={stack.group === 'sector' ? 'الاستفادات حسب الشهر والمسار' : 'الاستفادات حسب الشهر والمشروع'}
          hint="اضغط على شهر لتصفيته"
        >
          <Suspense fallback={fallback('h-80')}>
            <MonthlyStackChart stack={stack} selectedPeriod={selectedPeriod} onSelectPeriod={(key) => onSelectPeriod(selectedPeriod === key ? null : key)} />
          </Suspense>
        </Card>
      )}

      {a.heatmap && a.heatmap.rows.length >= 2 && (
        <Card id="project-office-matrix" title="المشاريع والمكاتب / المناطق" hint="أين يتركز نشاط كل مشروع وأين لا توجد سجلات">
          <ProjectOfficeMatrix matrix={a.heatmap} selectedOffices={dashboard.filters.offices.map((o) => o.slug)} onOpenProject={onOpenProject} onSelectOffice={onSelectOffice} />
        </Card>
      )}

      {a.pyramid && (
        <Card id="age-gender" title="الفئات العمرية والجنس" hint="من السجلات التي ذُكر عمرها في المصدر">
          <Suspense fallback={fallback('h-40')}>
            <PyramidChart pyramid={a.pyramid} />
          </Suspense>
        </Card>
      )}

      {a.courses && (
        <Card id="courses" title="تحليل الدورات" hint="الدورة = مشروع + رقم الدورة، والأرقام استفادات مسجلة">
          <CoursesPanel courses={a.courses} onOpenProject={onOpenProject} />
        </Card>
      )}

      {a.knowledge && (
        <Card id="knowledge" title="الاختصاص والجامعة" hint="من السجلات التي ذكرت الاختصاص والجامعة (رواد المعرفة)">
          <KnowledgePanel knowledge={a.knowledge} />
        </Card>
      )}
    </div>
  )
}
