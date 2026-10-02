import { useRef, type ReactNode } from 'react'

export interface TabItem<T extends string> {
  value: T
  label: string
  count?: number
}

interface TabsProps<T extends string> {
  label: string
  items: TabItem<T>[]
  value: T
  onChange: (value: T) => void
  idPrefix: string
}

/** ARIA tabs: arrow keys move between tabs (RTL aware), Home/End jump to the ends. */
export function Tabs<T extends string>({ label, items, value, onChange, idPrefix }: TabsProps<T>) {
  const refs = useRef<(HTMLButtonElement | null)[]>([])

  const focusTab = (index: number) => {
    const next = (index + items.length) % items.length
    refs.current[next]?.focus()
    onChange(items[next].value)
  }

  return (
    <div role="tablist" aria-label={label} className="inline-flex gap-1 rounded-xl bg-paper-deep p-1">
      {items.map((item, index) => {
        const selected = item.value === value
        return (
          <button
            key={item.value}
            ref={(el) => {
              refs.current[index] = el
            }}
            id={`${idPrefix}-tab-${item.value}`}
            role="tab"
            type="button"
            aria-selected={selected}
            aria-controls={`${idPrefix}-panel`}
            tabIndex={selected ? 0 : -1}
            onClick={() => onChange(item.value)}
            onKeyDown={(event) => {
              // In RTL the "next" tab is to the left.
              if (event.key === 'ArrowLeft') focusTab(index + 1)
              else if (event.key === 'ArrowRight') focusTab(index - 1)
              else if (event.key === 'Home') focusTab(0)
              else if (event.key === 'End') focusTab(items.length - 1)
              else return
              event.preventDefault()
            }}
            className={`inline-flex min-h-9 cursor-pointer items-center gap-2 rounded-lg px-4 text-sm font-bold transition-colors ${
              selected ? 'bg-white text-ink shadow-sm' : 'text-ink-muted hover:text-ink'
            }`}
          >
            {item.label}
            {item.count != null && (
              <span className={`num rounded-full px-1.5 text-xs ${selected ? 'bg-brand-soft text-brand-ink' : 'bg-white/70'}`}>
                {item.count}
              </span>
            )}
          </button>
        )
      })}
    </div>
  )
}

export function TabPanel({ idPrefix, value, children }: { idPrefix: string; value: string; children: ReactNode }) {
  return (
    <div role="tabpanel" id={`${idPrefix}-panel`} aria-labelledby={`${idPrefix}-tab-${value}`} tabIndex={0} className="outline-none">
      {children}
    </div>
  )
}
