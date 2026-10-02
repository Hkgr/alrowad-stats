import { useEffect, useRef } from 'react'
import { useReducedMotion } from '../../../hooks/useReducedMotion'
import { formatNumber } from '../../../lib/format'
import { themeFor, UNCLASSIFIED_THEME, type Theme } from '../../../lib/themes'
import type { Dashboard, SliceFigures } from '../../../types/api'

interface CardData extends SliceFigures {
  slug: string
  name: string
  selected: boolean
  classified_projects: number | null
  theme: Theme
  note?: string
}

interface Props {
  sectors: Dashboard['sectors']
  unclassified: Dashboard['unclassified']
  onSelect: (slug: string | null) => void
}

/** The tracks as the main entry point (plus records without a track for their year). */
export function SectorCards({ sectors, unclassified, onSelect }: Props) {
  const cards: CardData[] = sectors.map((s) => ({ ...s, theme: themeFor(s.code) }))
  if (unclassified) {
    cards.push({ ...unclassified, classified_projects: null, theme: UNCLASSIFIED_THEME, note: 'بلا مرجع تصنيف لسنتها' })
  }

  const anySelected = cards.some((c) => c.selected)
  const selectedSlug = cards.find((c) => c.selected)?.slug
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
      <ul
        ref={listRef}
        className={`scrollbar-none -mx-4 flex snap-x gap-3 overflow-x-auto px-4 pb-1 sm:mx-0 sm:grid sm:grid-cols-2 sm:overflow-visible sm:px-0 lg:grid-cols-3 ${
          cards.length > 5 ? 'xl:grid-cols-6' : 'xl:grid-cols-5'
        }`}
      >
        {cards.map((card) => {
          const { theme } = card
          return (
            <li key={card.slug} data-slug={card.slug} className="w-[15rem] shrink-0 snap-start sm:w-auto">
              <button
                type="button"
                onClick={() => onSelect(card.selected ? null : card.slug)}
                aria-pressed={card.selected}
                className={`group relative flex h-full w-full cursor-pointer flex-col overflow-hidden rounded-2xl border p-4 text-start shadow-card transition-[transform,box-shadow,border-color,opacity] duration-200 hover:-translate-y-0.5 hover:shadow-lift ${
                  anySelected && !card.selected ? 'opacity-75 hover:opacity-100' : ''
                }`}
                style={{
                  background: card.selected ? theme.light : '#ffffff',
                  borderColor: card.selected ? theme.primary : 'var(--color-line)',
                  boxShadow: card.selected ? `0 0 0 3px ${theme.primary}33` : undefined,
                }}
              >
                <span aria-hidden="true" className="absolute inset-x-0 top-0 h-1.5" style={{ background: theme.primary }} />
                <span className="flex items-center gap-2.5">
                  <span
                    className="grid size-10 shrink-0 place-items-center rounded-xl text-xl"
                    style={{ background: theme.light, boxShadow: `inset 0 0 0 1px ${theme.primary}33` }}
                    aria-hidden="true"
                  >
                    {theme.emoji}
                  </span>
                  <span className="min-w-0 text-sm font-bold leading-snug" style={{ color: theme.dark }}>
                    {card.name}
                  </span>
                </span>

                <span className="mt-3">
                  {card.has_data ? (
                    <>
                      <span className="num text-2xl font-extrabold leading-none text-ink">{formatNumber(card.total)}</span>
                      <span className="mt-1 block text-xs text-ink-muted">استفادة مسجلة</span>
                    </>
                  ) : (
                    <span className="text-sm font-semibold text-ink-muted">لا توجد بيانات</span>
                  )}
                </span>

                <span className="mt-auto flex flex-wrap gap-x-3 gap-y-1 border-t border-line pt-2.5 text-xs text-ink-soft [margin-top:0.75rem]">
                  {card.classified_projects !== null && (
                    <span>
                      مصنفة: <span className="num font-bold text-ink">{formatNumber(card.classified_projects)}</span>
                    </span>
                  )}
                  <span>
                    لديها بيانات: <span className="num font-bold text-ink">{formatNumber(card.projects_with_data ?? 0)}</span>
                  </span>
                  {card.note && <span className="w-full text-ink-muted">{card.note}</span>}
                </span>
              </button>
            </li>
          )
        })}
      </ul>
    </section>
  )
}
