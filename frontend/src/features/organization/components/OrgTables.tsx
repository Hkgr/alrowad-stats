import { useMemo, useState } from 'react'
import { formatNumber, normalizeArabic } from '../../../lib/format'
import type { OrgCenterRow, OrgProjectRow, OrgRegionRow } from '../types'

const head = 'border-b border-line px-3 py-2 text-start font-bold whitespace-nowrap'
const numHead = 'border-b border-line px-3 py-2 text-start font-bold whitespace-nowrap'
const cell = 'border-b border-line px-3 py-2 text-start'
const numCell = 'border-b border-line px-3 py-2 text-start whitespace-nowrap'

function SearchField({ value, onChange, label }: { value: string; onChange: (value: string) => void; label: string }) {
  return (
    <input
      value={value}
      onChange={(event) => onChange(event.target.value)}
      placeholder={label}
      aria-label={label}
      className="h-10 w-full rounded-xl border border-line-strong bg-white px-3 text-sm font-semibold text-ink outline-none placeholder:font-medium placeholder:text-ink-muted focus:border-brand sm:w-64"
    />
  )
}

export function ProjectsTable({
  rows,
  showSnapshot,
  onSelect,
}: {
  rows: OrgProjectRow[]
  showSnapshot: boolean
  onSelect: (slug: string | null, programSlug: string) => void
}) {
  const [query, setQuery] = useState('')
  const visible = useMemo(() => {
    const needle = normalizeArabic(query)
    if (!needle) return rows
    return rows.filter((row) => normalizeArabic(`${row.name} ${row.program_name}`).includes(needle))
  }, [rows, query])

  return (
    <div className="space-y-3">
      <SearchField value={query} onChange={setQuery} label="بحث في المشاريع" />
      <div className="max-h-[32rem] overflow-auto">
        <table className="w-full min-w-[36rem] border-separate border-spacing-0 text-sm">
          <thead className="sticky top-0 bg-white">
            <tr className="text-ink-muted">
              <th className={head}>المشروع</th>
              <th className={head}>البرنامج</th>
              <th className={numHead}>المستفيدون</th>
              {showSnapshot && (
                <>
                  <th className={numHead}>احتياجات 2024</th>
                  <th className={numHead}>الكادر 2024</th>
                </>
              )}
            </tr>
          </thead>
          <tbody>
            {visible.map((row) => (
              <tr key={row.slug} className={row.selected ? 'bg-[var(--t-light)]' : 'hover:bg-paper'}>
                <td className={cell}>
                  <button
                    type="button"
                    aria-pressed={row.selected}
                    onClick={() => onSelect(row.selected ? null : row.slug, row.program_slug)}
                    className="cursor-pointer text-start font-bold text-ink hover:text-brand-ink"
                  >
                    {row.name}
                  </button>
                </td>
                <td className={`${cell} text-ink-soft`}>{row.program_name}</td>
                <td className={`${numCell} font-bold text-ink`}><span className="num">{formatNumber(row.beneficiaries)}</span></td>
                {showSnapshot && (
                  <>
                    <td className={`${numCell} text-ink-soft`}><span className="num">{formatNumber(row.disabled)}</span></td>
                    <td className={`${numCell} text-ink-soft`}><span className="num">{formatNumber(row.staff)}</span></td>
                  </>
                )}
              </tr>
            ))}
            {visible.length === 0 && (
              <tr>
                <td colSpan={showSnapshot ? 5 : 3} className="px-3 py-6 text-center text-ink-muted">
                  لا مشاريع مطابقة
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  )
}

export function RegionsTable({ rows, showSnapshot, onSelect }: { rows: OrgRegionRow[]; showSnapshot: boolean; onSelect: (slug: string | null) => void }) {
  return (
    <div className="overflow-auto">
      <table className="w-full min-w-[32rem] border-separate border-spacing-0 text-sm">
        <thead>
          <tr className="text-ink-muted">
            <th className={head}>المنطقة</th>
            <th className={numHead}>المستفيدون</th>
            <th className={numHead}>المراكز</th>
            {showSnapshot && (
              <>
                <th className={numHead}>المكاتب</th>
                <th className={numHead}>احتياجات</th>
                <th className={numHead}>الكادر</th>
              </>
            )}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={row.slug} className={row.selected ? 'bg-[var(--t-light)]' : 'hover:bg-paper'}>
              <td className={cell}>
                <button
                  type="button"
                  aria-pressed={row.selected}
                  onClick={() => onSelect(row.selected ? null : row.slug)}
                  className="cursor-pointer text-start font-bold text-ink hover:text-brand-ink"
                >
                  {row.name}
                </button>
              </td>
              <td className={`${numCell} font-bold`}><span className="num">{formatNumber(row.beneficiaries)}</span></td>
              <td className={numCell}><span className="num">{formatNumber(row.centers)}</span></td>
              {showSnapshot && (
                <>
                  <td className={numCell}><span className="num">{formatNumber(row.offices)}</span></td>
                  <td className={numCell}><span className="num">{formatNumber(row.disabled)}</span></td>
                  <td className={numCell}><span className="num">{formatNumber(row.staff)}</span></td>
                </>
              )}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}

export function CentersTable({ rows, onSelect }: { rows: OrgCenterRow[]; onSelect: (slug: string | null, regionSlug: string | null) => void }) {
  const [query, setQuery] = useState('')
  const visible = useMemo(() => {
    const needle = normalizeArabic(query)
    if (!needle) return rows
    return rows.filter((row) => normalizeArabic(`${row.name} ${row.region_name ?? ''}`).includes(needle))
  }, [rows, query])

  return (
    <div className="space-y-3">
      <SearchField value={query} onChange={setQuery} label="بحث في المراكز" />
      <div className="max-h-[28rem] overflow-auto">
        <table className="w-full min-w-[28rem] border-separate border-spacing-0 text-sm">
          <thead className="sticky top-0 bg-white">
            <tr className="text-ink-muted">
              <th className={head}>المركز</th>
              <th className={head}>المنطقة</th>
              <th className={numHead}>المستفيدون</th>
            </tr>
          </thead>
          <tbody>
            {visible.map((row) => (
              <tr key={row.slug} className={row.selected ? 'bg-[var(--t-light)]' : 'hover:bg-paper'}>
                <td className={cell}>
                  <button
                    type="button"
                    aria-pressed={row.selected}
                    onClick={() => onSelect(row.selected ? null : row.slug, row.region_slug)}
                    className="cursor-pointer text-start font-bold text-ink hover:text-brand-ink"
                  >
                    {row.name}
                  </button>
                </td>
                <td className={`${cell} text-ink-soft`}>{row.region_name}</td>
                <td className={`${numCell} font-bold`}><span className="num">{formatNumber(row.beneficiaries)}</span></td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  )
}
