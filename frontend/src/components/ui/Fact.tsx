import type { LucideIcon } from 'lucide-react'

interface FactProps {
  icon: LucideIcon
  label: string
  value: string
  caption?: string
}

/** One labelled figure with a short caption: the small stat tile used inside analysis cards. */
export function Fact({ icon: Icon, label, value, caption }: FactProps) {
  return (
    <article className="min-w-0 rounded-xl border border-line bg-paper-deep/40 p-3">
      <div className="flex items-center justify-between gap-2">
        <h3 className="text-sm font-bold text-ink-soft">{label}</h3>
        <Icon className="size-4 shrink-0 text-ink-muted" aria-hidden="true" />
      </div>
      <p className="num mt-1.5 text-xl font-extrabold leading-tight text-ink">{value}</p>
      {caption && <p className="mt-0.5 text-sm text-ink-muted">{caption}</p>}
    </article>
  )
}
