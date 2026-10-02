import { BookOpen, Building2, HandHeart, HeartPulse, Layers, Palette, type LucideIcon } from 'lucide-react'
import { useEffect, useRef } from 'react'
import { sectorColor } from '../../../components/charts/chartTheme'
import { useReducedMotion } from '../../../hooks/useReducedMotion'
import { formatNumber } from '../../../lib/format'
import type { SectorRow } from '../../../types/api'

// Presentation only: an icon per track code from the reference list, with a neutral fallback.
const ICONS: Record<string, LucideIcon> = {
  EDU: BookOpen,
  CUL: Palette,
  DEV: Building2,
  HLT: HeartPulse,
  CHR: HandHeart,
}

interface Props {
  sectors: SectorRow[]
  onSelect: (slug: string | null) => void
}

/** The five tracks as the main entry point. Selecting one narrows everything below it. */
export function SectorCards({ sectors, onSelect }: Props) {
  const anySelected = sectors.some((s) => s.selected)
  const selectedSlug = sectors.find((s) => s.selected)?.slug
  const listRef = useRef<HTMLUListElement>(null)
  const reduced = useReducedMotion()

  // On narrow screens the cards scroll sideways: bring the selected one into view (horizontally only).
  useEffect(() => {
    const list = listRef.current
    const item = selectedSlug ? list?.querySelector<HTMLElement>(`[data-slug="${selectedSlug}"]`) : null
    if (!list || !item || list.scrollWidth <= list.clientWidth) return
    const box = list.getBoundingClientRect()
    const rect = item.getBoundingClientRect()
    if (rect.left < box.left || rect.right > box.right) {
      list.scrollBy({ left: rect.left - box.left - (box.width - rect.width) / 2, behavior: reduced ? 'auto' : 'smooth' })
    }
  }, [selectedSlug, reduced])

  return (
    <section aria-label="المسارات">
      <ul ref={listRef} className="scrollbar-none -mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 lg:grid-cols-5">
        {sectors.map((sector, index) => {
          const Icon = (sector.code && ICONS[sector.code]) || Layers
          const color = sectorColor(index)
          return (
            <li key={sector.slug} data-slug={sector.slug} className="w-[15.5rem] shrink-0 snap-start sm:w-auto">
              <button
                type="button"
                onClick={() => onSelect(sector.selected ? null : sector.slug)}
                aria-pressed={sector.selected}
                className={`group relative flex h-full w-full cursor-pointer flex-col overflow-hidden rounded-2xl border bg-white p-4 text-start shadow-card transition-[transform,box-shadow,border-color,opacity] duration-200 hover:-translate-y-0.5 hover:shadow-lift ${
                  sector.selected
                    ? 'border-brand ring-2 ring-brand/25'
                    : anySelected
                      ? 'border-line opacity-75 hover:opacity-100'
                      : 'border-line'
                }`}
              >
                <span aria-hidden="true" className="absolute inset-x-0 top-0 h-1" style={{ background: color }} />
                <span className="flex items-center gap-2.5">
                  <span
                    className="grid size-9 shrink-0 place-items-center rounded-xl"
                    style={{ background: `${color}1a`, color }}
                    aria-hidden="true"
                  >
                    <Icon className="size-[1.15rem]" />
                  </span>
                  <span className="min-w-0 text-sm font-bold leading-snug text-ink">{sector.name}</span>
                </span>

                <span className="mt-3 flex items-end justify-between gap-2">
                  {sector.has_data ? (
                    <span>
                      <span className="num text-2xl font-extrabold leading-none text-ink">{formatNumber(sector.total)}</span>
                      <span className="mt-1 block text-xs text-ink-muted">استفادة مسجلة</span>
                    </span>
                  ) : (
                    <span className="text-sm font-semibold text-ink-muted">لا توجد بيانات</span>
                  )}
                </span>

                <span className="mt-3 flex flex-wrap gap-x-3 gap-y-1 border-t border-line pt-2.5 text-xs text-ink-soft">
                  <span>
                    مشاريع مصنفة: <span className="num font-bold text-ink">{formatNumber(sector.classified_projects)}</span>
                  </span>
                  <span>
                    لديها بيانات: <span className="num font-bold text-ink">{formatNumber(sector.projects_with_data ?? 0)}</span>
                  </span>
                </span>
              </button>
            </li>
          )
        })}
      </ul>
    </section>
  )
}
