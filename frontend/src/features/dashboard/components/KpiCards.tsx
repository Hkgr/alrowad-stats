import { Building2, Mars, Users, Venus } from 'lucide-react'
import type { ComponentType } from 'react'
import { Skeleton } from '../../../components/ui/Skeleton'
import { useAnimatedNumber } from '../../../hooks/useAnimatedNumber'
import { formatNumber, formatPercent, NO_DATA } from '../../../lib/format'
import type { Summary } from '../../../types/api'

interface KpiProps {
  label: string
  value: number | null
  caption: string
  accent: string
  icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>
  delay: number
  featured?: boolean
}

function Kpi({ label, value, caption, accent, icon: Icon, delay, featured }: KpiProps) {
  const shown = useAnimatedNumber(value)

  return (
    <article
      className={`relative animate-rise overflow-hidden rounded-2xl border p-5 shadow-card ${
        featured ? 'border-navy-800 bg-gradient-to-br from-navy-900 to-navy-700 text-white' : 'border-line bg-surface'
      }`}
      style={{ animationDelay: `${delay}ms` }}
    >
      <span aria-hidden="true" className="absolute inset-x-0 top-0 h-1" style={{ background: accent }} />
      <div className="flex items-center justify-between gap-3">
        <h3 className={`text-sm font-bold ${featured ? 'text-navy-200' : 'text-ink-muted'}`}>{label}</h3>
        <span
          className={`grid size-10 place-items-center rounded-xl ${featured ? 'bg-white/12' : 'bg-navy-50'}`}
          style={{ color: featured ? '#fff' : accent }}
        >
          <Icon className="size-5" aria-hidden={true} />
        </span>
      </div>
      <p
        className={`num mt-3 text-4xl font-extrabold leading-none tracking-tight sm:text-[2.75rem] ${
          featured ? 'text-white' : 'text-ink'
        }`}
        aria-label={`${label}: ${formatNumber(value)}`}
      >
        {value == null ? NO_DATA : formatNumber(shown)}
      </p>
      <p className={`mt-3 text-sm ${featured ? 'text-navy-200' : 'text-ink-muted'}`}>{caption}</p>
    </article>
  )
}

export function KpiCards({ summary, unitLabel }: { summary: Summary; unitLabel: string }) {
  const none = summary.total == null
  return (
    <section aria-label="المؤشرات الرئيسية" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <Kpi
        featured
        label="الاستفادات المسجلة"
        value={summary.total}
        caption={none ? 'لا بيانات لهذا التحديد' : `مجموع ${unitLabel}، وليس مستفيدين فريدين`}
        accent="#f58220"
        icon={Users}
        delay={0}
      />
      <Kpi
        label="الذكور"
        value={summary.male}
        caption={none ? 'لا بيانات لهذا التحديد' : `${formatPercent(summary.male_share)} من الإجمالي`}
        accent="#2f6fd0"
        icon={Mars}
        delay={60}
      />
      <Kpi
        label="الإناث"
        value={summary.female}
        caption={none ? 'لا بيانات لهذا التحديد' : `${formatPercent(summary.female_share)} من الإجمالي`}
        accent="#e26a00"
        icon={Venus}
        delay={120}
      />
      <Kpi
        label="المكاتب المشمولة"
        value={summary.offices_count}
        caption={none ? 'لا بيانات لهذا التحديد' : 'مكاتب لديها سجلات في هذا النطاق'}
        accent="#14274e"
        icon={Building2}
        delay={180}
      />
    </section>
  )
}

export function KpiCardsSkeleton() {
  return (
    <div aria-hidden="true" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      {Array.from({ length: 4 }, (_, i) => (
        <div key={i} className="rounded-2xl border border-line bg-surface p-5 shadow-card">
          <Skeleton className="h-4 w-28" />
          <Skeleton className="mt-4 h-11 w-36" />
          <Skeleton className="mt-4 h-4 w-44" />
        </div>
      ))}
    </div>
  )
}
