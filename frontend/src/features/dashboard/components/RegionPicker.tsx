import { ChevronDown } from 'lucide-react'
import { useEffect, useId, useRef, useState } from 'react'
import { formatNumber } from '../../../lib/format'
import { officesCountLabel, regionSlugs, selectionChips } from '../../../lib/regions'
import type { RegionOption } from '../../../types/api'

interface Props {
  regions: RegionOption[]
  /** Chosen office slugs. */
  selected: string[]
  /** Names of chosen offices the options may not list (kept readable). */
  names?: Record<string, string>
  onChange: (slugs: string[]) => void
  /** `bar` puts the label above a full-width control, for a narrow rail. */
  variant?: 'inline' | 'bar'
}

/** A checkbox that can show "some of the group is chosen". */
function Check({ checked, partial, onChange, label }: { checked: boolean; partial?: boolean; onChange: () => void; label: string }) {
  const ref = useRef<HTMLInputElement>(null)
  useEffect(() => {
    if (ref.current) ref.current.indeterminate = Boolean(partial) && !checked
  }, [partial, checked])

  return <input ref={ref} type="checkbox" checked={checked} onChange={onChange} aria-label={label} className="size-4 shrink-0 cursor-pointer accent-[var(--color-brand)]" />
}

/**
 * Multi-select of regions and their offices. A region row selects (or clears) all its offices; the
 * offices below it can be picked one by one. Next to each region: how many offices it has — or the
 * note that it has none (its single choice then stands for the region itself).
 */
export function RegionPicker({ regions, selected, names, onChange, variant = 'inline' }: Props) {
  const [open, setOpen] = useState(false)
  const [box, setBox] = useState<{ top: number; right: number; width: number; maxHeight: number } | null>(null)
  const root = useRef<HTMLDivElement>(null)
  const trigger = useRef<HTMLButtonElement>(null)
  const panelId = useId()
  const buttonId = useId()
  const bar = variant === 'bar'
  const chosen = new Set(selected)
  const chips = selectionChips(regions, selected, names)
  const active = selected.length > 0

  useEffect(() => {
    if (!open) return
    const close = (event: Event) => {
      if (event instanceof KeyboardEvent) {
        if (event.key === 'Escape') {
          setOpen(false)
          trigger.current?.focus()
        }
      } else if (!root.current?.contains(event.target as Node)) {
        setOpen(false)
      }
    }
    document.addEventListener('mousedown', close)
    document.addEventListener('keydown', close)
    return () => {
      document.removeEventListener('mousedown', close)
      document.removeEventListener('keydown', close)
    }
  }, [open])

  // Fixed, so the menu is not clipped by the scrolling filter rail.
  useEffect(() => {
    if (!open) return
    const place = () => {
      const rect = trigger.current?.getBoundingClientRect()
      if (!rect) return
      const below = window.innerHeight - rect.bottom - 12
      const above = rect.top - 12
      const openUp = below < 220 && above > below
      const maxHeight = Math.max(160, Math.min(384, openUp ? above : below))
      const right = Math.max(8, window.innerWidth - rect.right)
      const width = Math.min(360, Math.max(rect.width, 260), window.innerWidth - right - 8)
      const top = openUp ? Math.max(8, rect.top - maxHeight - 4) : rect.bottom + 4
      setBox((prev) =>
        prev && Math.abs(prev.top - top) < 1 && Math.abs(prev.right - right) < 1 && Math.abs(prev.width - width) < 1 && prev.maxHeight === maxHeight
          ? prev
          : { top, right, width, maxHeight },
      )
    }
    place()
    window.addEventListener('resize', place)
    window.addEventListener('scroll', place, true)
    return () => {
      window.removeEventListener('resize', place)
      window.removeEventListener('scroll', place, true)
    }
  }, [open])

  const toggleOffice = (slug: string) => onChange(chosen.has(slug) ? selected.filter((s) => s !== slug) : [...selected, slug])

  const toggleRegion = (region: RegionOption) => {
    const slugs = regionSlugs(region)
    const allChosen = slugs.every((s) => chosen.has(s))
    onChange(allChosen ? selected.filter((s) => !slugs.includes(s)) : [...selected, ...slugs.filter((s) => !chosen.has(s))])
  }

  const summary =
    chips.length === 0 ? 'كل المناطق' : chips.length <= 2 ? chips.map((c) => c.label).join('، ') : `${formatNumber(selected.length)} مختارة`

  return (
    <div ref={root} className="relative min-w-0">
      {bar && (
        <label htmlFor={buttonId} className="mb-1 block text-xs font-bold text-ink-soft">
          المنطقة
        </label>
      )}
      <button
        ref={trigger}
        id={buttonId}
        type="button"
        aria-haspopup="dialog"
        aria-expanded={open}
        aria-controls={open ? panelId : undefined}
        onClick={() => setOpen((v) => !v)}
        className={`relative flex h-10 w-full min-w-0 cursor-pointer items-center rounded-xl border bg-white text-start transition-colors ${
          active ? 'border-brand/60 bg-brand-wash' : 'border-line-strong hover:border-ink-muted'
        }`}
      >
        {!bar && <span className="shrink-0 ps-3 text-xs font-bold text-ink-muted">المنطقة</span>}
        <span className={`min-w-0 flex-1 truncate pe-8 text-sm font-semibold text-ink ${bar ? 'ps-3' : 'ps-2'}`}>{summary}</span>
        <ChevronDown aria-hidden="true" className={`pointer-events-none absolute end-2.5 size-4 text-ink-muted transition-transform ${open ? 'rotate-180' : ''}`} />
      </button>

      {open && box && (
        <div
          id={panelId}
          role="group"
          aria-label="اختيار المناطق والمكاتب"
          className="z-40 overflow-y-auto rounded-xl border border-line-strong bg-white p-1.5 shadow-lift"
          style={{ position: 'fixed', top: box.top, right: box.right, width: box.width, maxHeight: box.maxHeight }}
        >
          <div className="flex items-center justify-between gap-2 px-2 py-1.5 text-xs text-ink-muted">
            <span>{active ? `${formatNumber(selected.length)} مختارة` : 'لا تصفية: كل المناطق'}</span>
            {active && (
              <button type="button" onClick={() => onChange([])} className="cursor-pointer rounded-md px-2 py-0.5 text-xs font-bold text-brand-ink hover:bg-brand-soft">
                مسح الاختيار
              </button>
            )}
          </div>

          {regions.map((region) => {
            const slugs = regionSlugs(region)
            const count = slugs.filter((s) => chosen.has(s)).length
            const standIn = !region.has_offices
            const row = 'flex cursor-pointer items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-paper-deep'

            return (
              <div key={region.slug} className="border-t border-line first:border-t-0">
                <label className={`${row} ${region.has_data ? '' : 'text-ink-muted'}`}>
                  <Check checked={slugs.length > 0 && count === slugs.length} partial={count > 0} onChange={() => toggleRegion(region)} label={standIn ? region.name : `كل مكاتب منطقة ${region.name}`} />
                  <span className="min-w-0 flex-1 truncate text-sm font-extrabold text-ink">{region.name}</span>
                  <span className="shrink-0 text-xs font-semibold text-ink-muted">{officesCountLabel(region)}</span>
                </label>

                {!standIn &&
                  region.offices.map((office) => (
                    <label key={office.slug} className={`${row} ps-9`}>
                      <Check checked={chosen.has(office.slug)} onChange={() => toggleOffice(office.slug)} label={office.name} />
                      <span className={`min-w-0 flex-1 truncate text-sm ${office.has_data ? 'text-ink' : 'text-ink-muted'}`}>{office.name}</span>
                      {!office.has_data && <span className="shrink-0 text-xs text-ink-muted">لا بيانات</span>}
                    </label>
                  ))}
              </div>
            )
          })}
        </div>
      )}
    </div>
  )
}
