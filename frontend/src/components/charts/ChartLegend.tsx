export interface LegendItem {
  key: string
  label: string
  color: string
  /** Optional trailing text, e.g. "718 · 49.5%". */
  detail?: string
}

/** HTML legend shared by every chart so labels, spacing and typography stay uniform. */
export function ChartLegend({ items, className = '' }: { items: LegendItem[]; className?: string }) {
  return (
    <ul className={`flex flex-wrap items-center gap-x-5 gap-y-2 ${className}`} aria-label="وسيلة الإيضاح">
      {items.map((item) => (
        <li key={item.key} className="flex items-center gap-2 text-sm text-ink-soft">
          <span aria-hidden="true" className="size-3 shrink-0 rounded-[4px]" style={{ background: item.color }} />
          <span className="font-semibold text-ink">{item.label}</span>
          {item.detail && <span className="num text-ink-muted">{item.detail}</span>}
        </li>
      ))}
    </ul>
  )
}
