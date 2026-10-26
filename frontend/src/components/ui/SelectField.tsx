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
  /** `bar` puts the label above a full-width control, for a narrow rail. */
  variant?: 'inline' | 'bar'
}

/** Compact native select: accessible, keyboard friendly, small footprint. */
export function SelectField({ label, value, options, onChange, allLabel, disabled, variant = 'inline' }: SelectFieldProps) {
  const id = useId()
  const active = value !== ''
  const bar = variant === 'bar'

  return (
    <div className="min-w-0">
      {bar && (
        <label htmlFor={id} className="mb-1 block text-xs font-bold text-ink-soft">
          {label}
        </label>
      )}
      <div
        className={`relative flex h-10 min-w-0 items-center rounded-xl border bg-white transition-colors ${
          active ? 'border-brand/60 bg-brand-wash' : 'border-line-strong hover:border-ink-muted'
        } ${disabled ? 'opacity-60' : ''}`}
      >
        {!bar && (
          <label htmlFor={id} className="shrink-0 ps-3 text-xs font-bold text-ink-muted">
            {label}
          </label>
        )}
        <select
          id={id}
          value={value}
          disabled={disabled}
          onChange={(event) => onChange(event.target.value)}
          className={`h-full min-w-0 flex-1 cursor-pointer appearance-none bg-transparent pe-8 text-sm font-semibold text-ink outline-none disabled:cursor-not-allowed disabled:text-ink-muted ${bar ? 'ps-3' : 'ps-2'}`}
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
    </div>
  )
}
