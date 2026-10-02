import type { CSSProperties, ReactNode } from 'react'

interface CardProps {
  title?: string
  /** One short line under the title; keep it to a label, not an explanation. */
  hint?: string
  actions?: ReactNode
  children: ReactNode
  className?: string
  style?: CSSProperties
  id?: string
}

export function Card({ title, hint, actions, children, className = '', style, id }: CardProps) {
  const headingId = id ? `${id}-title` : undefined
  return (
    <section
      id={id}
      style={style}
      aria-labelledby={title ? headingId : undefined}
      className={`min-w-0 rounded-2xl border border-line bg-surface p-4 shadow-card sm:p-5 ${className}`}
    >
      {(title || actions) && (
        <header className="mb-3 flex flex-wrap items-start justify-between gap-x-3 gap-y-1">
          <div className="min-w-0">
            {title && (
              <h2 id={headingId} className="text-base font-bold leading-tight text-ink sm:text-lg">
                {title}
              </h2>
            )}
            {hint && <p className="mt-0.5 text-sm text-ink-muted">{hint}</p>}
          </div>
          {actions}
        </header>
      )}
      {children}
    </section>
  )
}
