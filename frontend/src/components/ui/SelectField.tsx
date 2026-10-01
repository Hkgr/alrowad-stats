import { ChevronDown } from 'lucide-react'
import { useId } from 'react'

export interface SelectOption {
  value: string
  label: string
}

interface SelectFieldProps {
  label: string
  value: string
  options: SelectOption[]
  onChange: (value: string) => void
  /** Label of the "no filter" entry; omit to require a value (e.g. institution). */
  allLabel?: string
  disabled?: boolean
}

export function SelectField({ label, value, options, onChange, allLabel, disabled }: SelectFieldProps) {
  const id = useId()

  return (
    <div className="min-w-0">
      <label htmlFor={id} className="mb-1.5 block text-xs font-bold text-ink-muted">
        {label}
      </label>
      <div className="relative">
        <select
          id={id}
          value={value}
          disabled={disabled}
          onChange={(event) => onChange(event.target.value)}
          className="h-11 w-full cursor-pointer appearance-none rounded-xl border border-line bg-white ps-4 pe-10 text-sm font-semibold text-ink transition-colors hover:border-navy-300 disabled:cursor-not-allowed disabled:bg-navy-50 disabled:text-ink-muted"
        >
          {allLabel !== undefined && <option value="">{allLabel}</option>}
          {options.map((option) => (
            <option key={option.value} value={option.value}>
              {option.label}
            </option>
          ))}
        </select>
        <ChevronDown
          aria-hidden="true"
          className="pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2 text-ink-muted"
        />
      </div>
    </div>
  )
}
