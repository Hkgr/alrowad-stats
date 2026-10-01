import { ExternalLink, FileSpreadsheet, Info } from 'lucide-react'
import type { ReactNode } from 'react'
import { formatNumber } from '../../../lib/format'
import type { Dashboard } from '../../../types/api'

function Row({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="grid gap-1 border-t border-line py-3 first:border-t-0 sm:grid-cols-[11rem_1fr] sm:gap-4">
      <dt className="text-sm font-bold text-ink-muted">{label}</dt>
      <dd className="min-w-0 text-sm leading-7 text-ink">{children}</dd>
    </div>
  )
}

interface Props {
  dashboard: Dashboard
  /** Number of periods that exist for the project; the timeline needs at least two. */
  availablePeriods: number
}

/** Explains exactly what the numbers on screen cover and where they come from. */
export function ScopePanel({ dashboard, availablePeriods }: Props) {
  const { scope, measure, institution, summary } = dashboard
  const isSample = scope.sources.some((source) => source.coverage === 'sample')

  return (
    <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem]">
      <dl>
        <Row label="المؤسسة">{institution.name}</Row>
        <Row label="المشاريع المشمولة">
          {scope.projects.length === 0 ? (
            'لا شيء ضمن هذا التحديد'
          ) : (
            <ul className="space-y-1">
              {scope.projects.map((project) => (
                <li key={project.slug}>
                  <span className="font-bold">{project.name}</span>
                  <span className="text-ink-muted">
                    {' — '}المسار: {project.sector ?? 'غير محدد'}
                    {project.source_category ? ` · تصنيف الملف (level 1): ${project.source_category}` : ''}
                  </span>
                </li>
              ))}
            </ul>
          )}
        </Row>
        <Row label="الفترات المشمولة">
          {scope.periods.length === 0 ? 'لا شيء ضمن هذا التحديد' : scope.periods.map((period) => period.label).join('، ')}
        </Row>
        <Row label="المكاتب المشمولة">
          <span className="num font-bold">{formatNumber(summary.offices_count)}</span>
          {summary.offices_count != null && ' مكاتب لديها سجلات في هذا النطاق'}
        </Row>
        <Row label="ما الذي يعنيه الرقم؟">
          <span className="font-bold">{measure.name}</span> — وحدة القياس: {measure.unit_label}. مستوى السجل: {measure.record_level_label}.
          طريقة التجميع: {measure.aggregation_label}. {measure.description}
        </Row>
        <Row label="الخط الزمني والنمو">
          {availablePeriods < 2
            ? 'غير معروضين: لا توجد حاليًا فترتان قابلتان للمقارنة. لا تُستنتج بيانات لبقية الشهور أو لسنة 2025.'
            : 'متاحة فترات متعددة، وسيُضاف عرض زمني في مرحلة لاحقة.'}
        </Row>
      </dl>

      <aside className="rounded-xl bg-navy-50 p-4 ring-1 ring-line" aria-label="مصدر البيانات">
        <h3 className="flex items-center gap-2 text-sm font-extrabold text-ink">
          <FileSpreadsheet className="size-4 text-navy-600" aria-hidden="true" />
          المصدر
        </h3>
        {scope.sources.length === 0 && <p className="mt-2 text-sm text-ink-muted">لا مصدر مسجل لهذا التحديد.</p>}
        <ul className="mt-2 space-y-4">
          {scope.sources.map((source) => (
            <li key={source.label} className="text-sm leading-7">
              <p className="font-bold text-ink">{source.label}</p>
              {source.file_name && <p className="text-ink-muted">الملف: <bdi>{source.file_name}</bdi></p>}
              {source.reference_url && (
                <a
                  href={source.reference_url}
                  target="_blank"
                  rel="noreferrer noopener"
                  className="inline-flex items-center gap-1 font-semibold text-navy-700 underline underline-offset-4 hover:text-brand-strong"
                >
                  فتح ملف المشروع
                  <ExternalLink className="size-3.5" aria-hidden="true" />
                  <span className="sr-only">(يفتح في نافذة جديدة)</span>
                </a>
              )}
            </li>
          ))}
        </ul>
        {isSample && (
          <p className="mt-4 flex gap-2 rounded-lg bg-brand-soft p-3 text-sm leading-6 text-navy-900">
            <Info className="mt-1 size-4 shrink-0 text-brand-strong" aria-hidden="true" />
            <span>
              هذه عينة أولى من ملف المشروع (مقطع واحد)، وليست استيرادًا كاملًا لبيانات المؤسسة لعامي 2025 و2026.
            </span>
          </p>
        )}
      </aside>
    </div>
  )
}
