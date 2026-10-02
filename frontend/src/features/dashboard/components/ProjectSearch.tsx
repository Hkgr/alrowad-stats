import { Search, X } from 'lucide-react'
import { useEffect, useId, useMemo, useRef, useState, type KeyboardEvent } from 'react'
import { normalizeArabic } from '../../../lib/format'
import type { FilterOptions } from '../../../types/api'

type ProjectOption = FilterOptions['projects'][number]

interface Props {
  projects: ProjectOption[]
  onSelect: (project: ProjectOption) => void
  className?: string
}

const MAX_RESULTS = 8

/**
 * Project finder (ARIA combobox). Matches ignore diacritics, hamza forms and ta marbuta.
 * "/" focuses it from anywhere; arrows move, Enter opens, Escape closes.
 */
export function ProjectSearch({ projects, onSelect, className = '' }: Props) {
  const [query, setQuery] = useState('')
  const [open, setOpen] = useState(false)
  const [active, setActive] = useState(0)
  const inputRef = useRef<HTMLInputElement>(null)
  const listId = useId()

  const results = useMemo(() => {
    const needle = normalizeArabic(query)
    const pool = needle
      ? projects.filter((p) => normalizeArabic(`${p.name} ${p.sector?.name ?? ''}`).includes(needle))
      : projects.filter((p) => p.has_data)
    return pool.slice(0, MAX_RESULTS)
  }, [projects, query])

  useEffect(() => {
    const onKey = (event: globalThis.KeyboardEvent) => {
      const target = event.target as HTMLElement | null
      const typing = target && (['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName) || target.isContentEditable)
      if (event.key === '/' && !typing && !event.ctrlKey && !event.metaKey) {
        event.preventDefault()
        inputRef.current?.focus()
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  const choose = (project: ProjectOption) => {
    onSelect(project)
    setQuery('')
    setOpen(false)
    inputRef.current?.blur()
  }

  const onKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setOpen(true)
      setActive((i) => Math.min(i + 1, results.length - 1))
    } else if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActive((i) => Math.max(i - 1, 0))
    } else if (event.key === 'Enter' && open && results[active]) {
      event.preventDefault()
      choose(results[active])
    } else if (event.key === 'Escape') {
      setOpen(false)
    }
  }

  const showList = open && (results.length > 0 || query !== '')

  return (
    <div className={`relative ${className}`}>
      <label htmlFor={`${listId}-input`} className="sr-only">
        ابحث عن مشروع
      </label>
      <div className="flex h-11 items-center rounded-full border border-line-strong bg-white ps-4 pe-1.5 shadow-sm transition-colors focus-within:border-brand hover:border-ink-muted">
        <input
          id={`${listId}-input`}
          ref={inputRef}
          type="text"
          role="combobox"
          aria-expanded={showList}
          aria-controls={listId}
          aria-autocomplete="list"
          aria-activedescendant={showList && results[active] ? `${listId}-${active}` : undefined}
          value={query}
          placeholder="ابحث عن مشروع…"
          autoComplete="off"
          onChange={(event) => {
            setQuery(event.target.value)
            setActive(0)
            setOpen(true)
          }}
          onFocus={() => setOpen(true)}
          onBlur={() => window.setTimeout(() => setOpen(false), 120)}
          onKeyDown={onKeyDown}
          className="h-full min-w-0 flex-1 bg-transparent text-sm font-semibold text-ink outline-none placeholder:font-normal placeholder:text-ink-muted"
        />
        {query && (
          <button
            type="button"
            onClick={() => {
              setQuery('')
              inputRef.current?.focus()
            }}
            aria-label="مسح البحث"
            className="grid size-8 cursor-pointer place-items-center rounded-full text-ink-muted hover:bg-paper-deep"
          >
            <X className="size-4" aria-hidden="true" />
          </button>
        )}
        <span aria-hidden="true" className="grid size-8 place-items-center rounded-full bg-brand text-white">
          <Search className="size-4" />
        </span>
      </div>

      {showList && (
        <ul
          id={listId}
          role="listbox"
          aria-label="نتائج البحث"
          className="absolute inset-x-0 top-full z-40 mt-2 max-h-96 overflow-auto rounded-2xl border border-line bg-white p-1.5 shadow-lift"
        >
          {!query && <li className="px-3 pb-1 pt-2 text-xs font-bold text-ink-muted">مشاريع لديها بيانات</li>}
          {results.length === 0 && <li className="px-3 py-4 text-sm text-ink-muted">لا يوجد مشروع بهذا الاسم.</li>}
          {results.map((project, index) => (
            <li
              key={project.slug}
              id={`${listId}-${index}`}
              role="option"
              aria-selected={index === active}
              onMouseDown={(event) => event.preventDefault()}
              onClick={() => choose(project)}
              onMouseEnter={() => setActive(index)}
              className={`flex cursor-pointer items-center justify-between gap-3 rounded-xl px-3 py-2.5 ${
                index === active ? 'bg-brand-wash' : ''
              }`}
            >
              <span className="min-w-0">
                <span className="block truncate text-sm font-bold text-ink">{project.name}</span>
                <span className="block truncate text-xs text-ink-muted">{project.sector?.name ?? 'غير مصنف'}</span>
              </span>
              {!project.has_data && (
                <span className="shrink-0 rounded-full bg-paper-deep px-2 py-0.5 text-[11px] font-semibold text-ink-muted">
                  لا بيانات
                </span>
              )}
            </li>
          ))}
        </ul>
      )}
    </div>
  )
}
