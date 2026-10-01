import { ArrowDown, ArrowUp, ArrowUpDown, Search, SearchX } from 'lucide-react'
import { useEffect, useMemo, useRef, useState } from 'react'
import { formatNumber, formatPercent, normalizeArabic } from '../../../lib/format'
import type { OfficeRow, Summary } from '../../../types/api'

type SortKey = 'name' | 'male' | 'female' | 'total' | 'share'
type SortDir = 'asc' | 'desc'

interface Props {
  rows: OfficeRow[]
  summary: Summary
  selectedOffice: string | null
  onSelectOffice: (slug: string) => void
}

const COLUMNS: { key: SortKey; label: string; numeric: boolean }[] = [
  { key: 'name', label: 'المكتب', numeric: false },
  { key: 'male', label: 'ذكور', numeric: true },
  { key: 'female', label: 'إناث', numeric: true },
  { key: 'total', label: 'الإجمالي', numeric: true },
  { key: 'share', label: 'النسبة من الإجمالي', numeric: true },
]

function compare(a: OfficeRow, b: OfficeRow, key: SortKey): number {
  if (key === 'name') return a.name.localeCompare(b.name, 'ar')
  return (a[key] ?? 0) - (b[key] ?? 0)
}

export function OfficesTable({ rows, summary, selectedOffice, onSelectOffice }: Props) {
  const [query, setQuery] = useState('')
  const [sort, setSort] = useState<{ key: SortKey; dir: SortDir }>({ key: 'total', dir: 'desc' })
  const searchRef = useRef<HTMLInputElement>(null)

  // "/" jumps to the search box unless the user is already typing somewhere.
  useEffect(() => {
    const onKey = (event: KeyboardEvent) => {
      const target = event.target as HTMLElement | null
      const typing = target && (['INPUT', 'SELECT', 'TEXTAREA'].includes(target.tagName) || target.isContentEditable)
      if (event.key === '/' && !typing && !event.ctrlKey && !event.metaKey) {
        event.preventDefault()
        searchRef.current?.focus()
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [])

  const visible = useMemo(() => {
    const needle = normalizeArabic(query)
    const filtered = needle ? rows.filter((row) => normalizeArabic(row.name).includes(needle)) : rows
    const direction = sort.dir === 'asc' ? 1 : -1
    // Ties fall back to the name so the order is stable.
    return [...filtered].sort((a, b) => compare(a, b, sort.key) * direction || a.name.localeCompare(b.name, 'ar'))
  }, [rows, query, sort])

  const toggleSort = (key: SortKey) =>
    setSort((current) =>
      current.key === key ? { key, dir: current.dir === 'asc' ? 'desc' : 'asc' } : { key, dir: key === 'name' ? 'asc' : 'desc' },
    )

  const ariaSort = (key: SortKey) => (sort.key === key ? (sort.dir === 'asc' ? 'ascending' : 'descending') : 'none')

  return (
    <div>
      <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div className="relative w-full sm:max-w-xs">
          <label htmlFor="office-search" className="sr-only">
            ابحث في المكاتب
          </label>
          <Search aria-hidden="true" className="pointer-events-none absolute start-3.5 top-1/2 size-4 -translate-y-1/2 text-ink-muted" />
          <input
            id="office-search"
            ref={searchRef}
            type="search"
            value={query}
            onChange={(event) => setQuery(event.target.value)}
            placeholder="ابحث باسم المكتب… ( / )"
            autoComplete="off"
            className="h-11 w-full rounded-xl border border-line bg-white ps-10 pe-4 text-sm font-semibold text-ink placeholder:font-normal placeholder:text-ink-muted hover:border-navy-300"
          />
        </div>
        <p className="text-sm text-ink-muted" aria-live="polite">
          يعرض <span className="num font-bold text-ink">{formatNumber(visible.length)}</span> من{' '}
          <span className="num font-bold text-ink">{formatNumber(rows.length)}</span> مكتب
        </p>
      </div>

      <div className="overflow-x-auto rounded-xl border border-line">
        <table className="w-full min-w-[640px] border-collapse text-sm">
          <caption className="sr-only">
            تفاصيل الاستفادات المسجلة حسب المكتب. يمكن الفرز بالنقر على عناوين الأعمدة.
          </caption>
          <thead className="bg-navy-50">
            <tr>
              {COLUMNS.map((column) => {
                const active = sort.key === column.key
                const Icon = !active ? ArrowUpDown : sort.dir === 'asc' ? ArrowUp : ArrowDown
                return (
                  <th key={column.key} scope="col" aria-sort={ariaSort(column.key)} className="p-0 text-start font-bold text-ink-soft">
                    <button
                      type="button"
                      onClick={() => toggleSort(column.key)}
                      className="flex min-h-12 w-full cursor-pointer items-center gap-1.5 px-4 hover:bg-navy-100"
                    >
                      {column.label}
                      <Icon aria-hidden="true" className={`size-3.5 ${active ? 'text-brand-strong' : 'text-ink-muted/60'}`} />
                    </button>
                  </th>
                )
              })}
            </tr>
          </thead>
          <tbody>
            {visible.map((row) => {
              const selected = row.slug === selectedOffice
              return (
                <tr key={row.slug} className={`border-t border-line transition-colors hover:bg-navy-50 ${selected ? 'bg-brand-soft' : ''}`}>
                  <th scope="row" className="p-0 text-start font-semibold text-ink">
                    <button
                      type="button"
                      onClick={() => onSelectOffice(row.slug)}
                      aria-pressed={selected}
                      title={selected ? 'إلغاء تصفية هذا المكتب' : 'تصفية حسب هذا المكتب'}
                      className="flex min-h-12 w-full cursor-pointer items-center px-4 text-start hover:text-brand-strong"
                    >
                      {row.name}
                    </button>
                  </th>
                  <td className="px-4 py-3 text-ink"><span className="num">{formatNumber(row.male)}</span></td>
                  <td className="px-4 py-3 text-ink"><span className="num">{formatNumber(row.female)}</span></td>
                  <td className="px-4 py-3 font-extrabold text-ink"><span className="num">{formatNumber(row.total)}</span></td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-3">
                      <span className="num w-14 shrink-0 text-ink-soft">{formatPercent(row.share)}</span>
                      <span aria-hidden="true" className="h-2 w-full min-w-16 max-w-40 overflow-hidden rounded-full bg-navy-100">
                        <span className="block h-full rounded-full bg-total" style={{ width: `${row.share ?? 0}%` }} />
                      </span>
                    </div>
                  </td>
                </tr>
              )
            })}
          </tbody>
          {visible.length > 0 && (
            <tfoot>
              <tr className="border-t-2 border-navy-200 bg-navy-50 font-extrabold text-ink">
                <th scope="row" className="px-4 py-3 text-start">
                  الإجمالي (كل المكاتب في الفلتر)
                </th>
                <td className="px-4 py-3"><span className="num">{formatNumber(summary.male)}</span></td>
                <td className="px-4 py-3"><span className="num">{formatNumber(summary.female)}</span></td>
                <td className="px-4 py-3"><span className="num">{formatNumber(summary.total)}</span></td>
                <td className="px-4 py-3" />
              </tr>
            </tfoot>
          )}
        </table>

        {visible.length === 0 && (
          <div className="flex flex-col items-center gap-2 px-4 py-12 text-center">
            <SearchX className="size-8 text-ink-muted" aria-hidden="true" />
            <p className="font-bold text-ink">لا توجد مكاتب مطابقة للبحث</p>
            <p className="text-sm text-ink-muted">جرّب كلمة أخرى أو امسح مربع البحث.</p>
          </div>
        )}
      </div>
    </div>
  )
}
