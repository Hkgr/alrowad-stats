import { Building2, Mars, Users, Venus, type LucideIcon } from 'lucide-react'
import { Skeleton } from '../../../components/ui/Skeleton'
import { useAnimatedNumber } from '../../../hooks/useAnimatedNumber'
import { formatNumber, formatPercent, NO_DATA } from '../../../lib/format'
import type { Summary } from '../../../types/api'

interface FigureProps {
  label: string
  value: number | null
  caption?: string
  accent: string
  icon: LucideIcon
  featured?: boolean
}

function Figure({ label, value, caption, accent, icon: Icon, featured }: FigureProps) {
  const shown = useAnimatedNumber(value)
  const exact = formatNumber(value)

  return (
    <article
      className={`relative min-w-0 overflow-hidden rounded-2xl border p-4 sm:p-5 ${
        featured ? 'border-[color-mix(in_srgb,var(--t-primary)_35%,transparent)] bg-gradient-to-bl from-[var(--t-light)] to-white' : 'border-line bg-white'
      }`}
    >
      <span aria-hidden="true" className="absolute inset-y-4 start-0 w-1 rounded-e-full" style={{ background: accent }} />
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-sm font-bold text-ink-soft">{label}</h3>
        <Icon className="size-5 shrink-0" style={{ color: accent }} aria-hidden="true" />
      </div>
      {/* The tween only changes the display; the exact API value is in the label and the title. */}
      <p
        className={`num mt-2 font-extrabold leading-none tracking-tight text-ink ${featured ? 'text-[2.6rem] sm:text-5xl' : 'text-[2rem] sm:text-[2.4rem]'}`}
        aria-label={`${label}: ${exact}`}
        title={exact}
        data-value={value ?? ''}
      >
        {value == null ? NO_DATA : formatNumber(shown)}
      </p>
      <p className="mt-2 min-h-5 text-sm text-ink-muted">{value == null ? 'لا توجد بيانات' : caption}</p>
    </article>
  )
}

export function FiguresBand({ summary }: { summary: Summary }) {
  return (
    <div className="grid grid-cols-2 gap-3 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
      <div className="col-span-2 lg:col-span-1">
        <Figure
          featured
          label="الاستفادات المسجلة"
          value={summary.total}
          caption={summary.gender_unreported ? `منها ${formatNumber(summary.gender_unreported)} لم يُذكر جنسها في المصدر` : undefined}
          accent="var(--t-primary)"
          icon={Users}
        />
      </div>
      <Figure
        label="الذكور"
        value={summary.male}
        caption={`${formatPercent(summary.male_share)} من الإجمالي`}
        accent="#2193c7"
        icon={Mars}
      />
      <Figure
        label="الإناث"
        value={summary.female}
        caption={`${formatPercent(summary.female_share)} من الإجمالي`}
        accent="#8f4bc9"
        icon={Venus}
      />
      <div className="col-span-2 lg:col-span-1">
        <Figure label="المكاتب" value={summary.offices_count} caption="مكاتب لديها سجلات" accent="var(--t-dark)" icon={Building2} />
      </div>
    </div>
  )
}

export function FiguresBandSkeleton() {
  return (
    <div aria-hidden="true" className="grid grid-cols-2 gap-3 lg:grid-cols-[1.4fr_1fr_1fr_1fr]">
      {Array.from({ length: 4 }, (_, i) => (
        <div key={i} className={`rounded-2xl border border-line bg-white p-5 ${i === 0 ? 'col-span-2 lg:col-span-1' : ''}`}>
          <Skeleton className="h-4 w-24" />
          <Skeleton className="mt-3 h-10 w-32" />
          <Skeleton className="mt-3 h-4 w-28" />
        </div>
      ))}
    </div>
  )
}
