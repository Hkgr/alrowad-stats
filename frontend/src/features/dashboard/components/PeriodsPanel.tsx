import { CalendarDays } from 'lucide-react'
import { formatNumber } from '../../../lib/format'
import type { PeriodRow } from '../../../types/api'
import { ComparisonBarChart } from './ComparisonBarChart'

/**
 * Periods that have records in the current scope. A comparison chart appears only when there
 * are at least two periods; there is no growth rate or trend line.
 */
export function PeriodsPanel({ periods, selected, onSelect }: { periods: PeriodRow[]; selected: string | null; onSelect: (key: string) => void }) {
  return (
    <div className="space-y-4">
      <ul className="flex flex-wrap gap-2">
        {periods.map((period) => (
          <li key={period.key}>
            <button
              type="button"
              onClick={() => onSelect(period.key)}
              aria-pressed={selected === period.key}
              className={`inline-flex min-h-10 cursor-pointer items-center gap-2 rounded-xl border px-3.5 text-sm transition-colors ${
                selected === period.key ? 'border-brand bg-brand-wash' : 'border-line-strong bg-white hover:border-brand/60'
              }`}
            >
              <CalendarDays className="size-4 text-brand-ink" aria-hidden="true" />
              <span className="font-bold text-ink">{period.label}</span>
              <span className="num font-semibold text-ink-soft">{formatNumber(period.total)}</span>
            </button>
          </li>
        ))}
      </ul>
      {periods.length >= 2 && (
        <ComparisonBarChart
          ariaLabel="الاستفادات المسجلة حسب الفترة"
          items={periods.map((p) => ({ key: p.key, name: p.label, value: p.total ?? 0, male: p.male ?? 0, female: p.female ?? 0, selected: p.key === selected }))}
          onSelect={onSelect}
        />
      )}
    </div>
  )
}
