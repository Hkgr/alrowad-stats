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
  /** Label of the "no filter" entry. */
  allLabel: string
  disabled?: boolean
}

/** Compact native select with an inline label: accessible, keyboard friendly, small footprint. */
export function SelectField({ label, value, options, onChange, allLabel, disabled }: SelectFieldProps) {
  const id = useId()
  const active = value !== ''

  return (
    <div
      className={`relative flex h-10 min-w-0 items-center rounded-xl border bg-white transition-colors ${
        active ? 'border-brand/60 bg-brand-wash' : 'border-line-strong hover:border-ink-muted'
      }`}
    >
      <label htmlFor={id} className="shrink-0 ps-3 text-xs font-bold text-ink-muted">
        {label}
      </label>
      <select
        id={id}
        value={value}
        disabled={disabled}
        onChange={(event) => onChange(event.target.value)}
        className="h-full min-w-0 flex-1 cursor-pointer appearance-none bg-transparent ps-2 pe-8 text-sm font-semibold text-ink outline-none disabled:cursor-not-allowed disabled:text-ink-muted"
      >
        <option value="">{allLabel}</option>
        {options.map((option) => (
          <option key={option.value} value={option.value}>
            {option.label}
          </option>
        ))}
      </select>
      <ChevronDown aria-hidden="true" className="pointer-events-none absolute end-2.5 size-4 text-ink-muted" />
    </div>
  )
}
