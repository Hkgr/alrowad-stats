import { ChevronDown } from 'lucide-react'
import { useState } from 'react'
import { Card } from '../../../components/ui/Card'
import { Skeleton } from '../../../components/ui/Skeleton'
import { ErrorState } from '../../../components/ui/StateViews'
import { formatNumber } from '../../../lib/format'
import type { DataQuality } from '../../../types/api'
import { useDataQuality } from '../queries'

const SEVERITY_TONE: Record<string, string> = {
  excluded: 'border-danger/40 bg-danger-soft text-ink',
  conflict: 'border-line-strong bg-paper-deep text-ink',
  resolved: 'border-line bg-white text-ink-soft',
  info: 'border-line bg-white text-ink-soft',
}

function Body({ data }: { data: DataQuality }) {
  const { issues } = data

  return (
    <div className="space-y-6">
      <section aria-label="ملخص الاستيراد">
        <p className="mb-2 text-sm text-ink-muted">
          آخر استيراد فعلي{data.run?.finished_at ? ` (${new Date(data.run.finished_at).toLocaleDateString('ar-SY-u-nu-latn')})` : ''}: ما لم يدخل في الأرقام وما دخل مع تحفظ.
        </p>
        <ul className="grid grid-cols-2 gap-2 lg:grid-cols-4">
          {issues.severities.map((s) => (
            <li key={s.severity} className={`rounded-xl border px-3 py-2 ${SEVERITY_TONE[s.severity]}`}>
              <span className="num block text-2xl font-extrabold leading-tight">{formatNumber(s.count)}</span>
              <span className="text-sm font-semibold">{s.label}</span>
            </li>
          ))}
        </ul>
      </section>

      <section aria-label="تفصيل المشاكل" className="space-y-2">
        {issues.groups.map((g) => (
          <details key={`${g.severity}-${g.code}`} className="group rounded-xl border border-line bg-white" open={g.severity === 'excluded'}>
            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 px-3 py-2.5">
              <span className="min-w-0">
                <span className="block text-sm font-bold text-ink">{g.label}</span>
                <span className="text-xs text-ink-muted">{issues.severities.find((s) => s.severity === g.severity)?.label}</span>
              </span>
              <span className="flex shrink-0 items-center gap-2">
                <span className="num rounded-full bg-paper-deep px-2.5 py-0.5 text-sm font-bold text-ink">{formatNumber(g.count)}</span>
                <ChevronDown className="size-4 text-ink-muted transition-transform group-open:rotate-180" aria-hidden="true" />
              </span>
            </summary>
            <ul className="space-y-1.5 border-t border-line px-3 py-2.5 text-sm">
              {g.examples.map((e, i) => (
                <li key={i} className="leading-6">
                  <span className="text-ink-muted" dir="auto">
                    {[e.file, e.sheet?.trim(), e.row != null ? `صف ${e.row}` : null].filter(Boolean).join(' › ')}
                  </span>
                  <span className="block text-ink-soft">{e.message}</span>
                </li>
              ))}
              {g.count > g.examples.length && <li className="text-ink-muted">و{formatNumber(g.count - g.examples.length)} آخر مثلها.</li>}
            </ul>
          </details>
        ))}
        {issues.groups.length === 0 && <p className="text-sm text-ink-muted">لا توجد ملاحظات في آخر استيراد.</p>}
      </section>
    </div>
  )
}

/** Maintenance view for whoever enters the data. Fetched only once opened. */
export function DataQualityCard({ institution }: { institution: string | null }) {
  const [open, setOpen] = useState(false)
  const query = useDataQuality(institution, open)

  return (
    <Card
      id="data-quality"
      title="جودة البيانات"
      hint="الصفوف المعزولة والتعارضات من آخر استيراد"
      actions={
        <button
          type="button"
          onClick={() => setOpen((v) => !v)}
          aria-expanded={open}
          aria-controls="data-quality-body"
          className="inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg border border-line-strong bg-white px-3 text-sm font-bold text-ink hover:bg-paper-deep"
        >
          {open ? 'إخفاء' : 'عرض'}
          <ChevronDown className={`size-4 transition-transform ${open ? 'rotate-180' : ''}`} aria-hidden="true" />
        </button>
      }
    >
      <div id="data-quality-body" hidden={!open}>
        {query.isPending && open ? (
          <Skeleton className="h-40 w-full" />
        ) : query.error ? (
          <ErrorState message={query.error.message} onRetry={() => void query.refetch()} />
        ) : query.data ? (
          <Body data={query.data} />
        ) : null}
      </div>
    </Card>
  )
}
