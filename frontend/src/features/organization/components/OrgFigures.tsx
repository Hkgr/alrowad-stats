import { Accessibility, Briefcase, Building2, FolderKanban, Landmark, Layers, Users, type LucideIcon } from 'lucide-react'
import { Skeleton } from '../../../components/ui/Skeleton'
import { useAnimatedNumber } from '../../../hooks/useAnimatedNumber'
import { formatNumber, NO_DATA } from '../../../lib/format'
import type { OrgDashboard } from '../types'

function Figure({
  label,
  value,
  caption,
  accent,
  icon: Icon,
  featured,
}: {
  label: string
  value: number | null
  caption?: string
  accent: string
  icon: LucideIcon
  featured?: boolean
}) {
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
      <p
        className={`num mt-2 font-extrabold leading-none tracking-tight text-ink ${featured ? 'text-[2.6rem] sm:text-5xl' : 'text-[2rem] sm:text-[2.4rem]'}`}
        aria-label={`${label}: ${exact}`}
        title={exact}
        data-value={value ?? ''}
      >
        {value == null ? NO_DATA : formatNumber(shown)}
      </p>
      <p className="mt-2 min-h-5 text-sm text-ink-muted">{caption}</p>
    </article>
  )
}

const SNAPSHOT_NOTE: Record<NonNullable<OrgDashboard['snapshot']>['basis'], string> = {
  overview: 'لقطة 2024: الاحتياجات والكادر من المشاريع، والمكاتب من المناطق. لا تتأثر بفلتر السنة.',
  program: 'لقطة 2024 لهذا البرنامج أو المشروع. ليست موزعة على المناطق، ولا تتأثر بفلتر السنة.',
  region: 'لقطة 2024 لهذه المنطقة. لا تتأثر بفلتر السنة.',
}

export function OrgFigures({ data }: { data: OrgDashboard }) {
  const { summary, snapshot } = data

  return (
    <div className="space-y-3">
      <div className="grid grid-cols-2 gap-3 xl:grid-cols-[1.4fr_1fr_1fr_1fr]">
        <div className="col-span-2 xl:col-span-1">
          <Figure featured label="المستفيدون" value={summary.beneficiaries} caption={summary.span ?? 'لا سنوات في هذا النطاق'} accent="var(--t-primary)" icon={Users} />
        </div>
        <Figure label="البرامج" value={summary.programs} caption="لديها مستفيدون" accent="var(--t-dark)" icon={Layers} />
        <Figure label="المشاريع" value={summary.projects} caption="لديها مستفيدون" accent="#2193c7" icon={FolderKanban} />
        <div className="col-span-2 xl:col-span-1">
          <Figure label="المراكز" value={summary.centers} caption={`${formatNumber(summary.regions)} مناطق`} accent="var(--t-dark)" icon={Building2} />
        </div>
      </div>

      {snapshot && (
        <div>
          <div className={`grid grid-cols-2 gap-3 ${snapshot.offices == null ? 'sm:grid-cols-2' : 'sm:grid-cols-3'}`}>
            <Figure label="ذوو الاحتياجات الخاصة" value={snapshot.disabled} caption="لقطة 2024" accent="#8f4bc9" icon={Accessibility} />
            <Figure label="الكادر" value={snapshot.staff} caption="لقطة 2024" accent="var(--t-dark)" icon={Briefcase} />
            {snapshot.offices != null && <Figure label="المكاتب" value={snapshot.offices} caption="لقطة 2024" accent="var(--t-primary)" icon={Landmark} />}
          </div>
          <p className="mt-2 text-sm text-ink-muted">{SNAPSHOT_NOTE[snapshot.basis]}</p>
        </div>
      )}
    </div>
  )
}

export function OrgFiguresSkeleton() {
  return (
    <div className="grid grid-cols-2 gap-3 xl:grid-cols-4">
      {Array.from({ length: 4 }, (_, index) => (
        <Skeleton key={index} className="h-32" />
      ))}
    </div>
  )
}
