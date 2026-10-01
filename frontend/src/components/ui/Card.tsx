import type { ReactNode } from 'react'

interface CardProps {
  title?: string
  description?: string
  actions?: ReactNode
  children: ReactNode
  className?: string
  id?: string
}

export function Card({ title, description, actions, children, className = '', id }: CardProps) {
  const headingId = id ? `${id}-title` : undefined
  return (
    <section
      id={id}
      aria-labelledby={title ? headingId : undefined}
      className={`min-w-0 rounded-2xl border border-line bg-surface p-5 shadow-card sm:p-6 ${className}`}
    >
      {(title || actions) && (
        <header className="mb-4 flex flex-wrap items-start justify-between gap-3">
          <div className="min-w-0">
            {title && (
              <h2 id={headingId} className="text-lg font-bold leading-tight text-ink">
                {title}
              </h2>
            )}
            {description && <p className="mt-1 text-sm text-ink-muted">{description}</p>}
          </div>
          {actions}
        </header>
      )}
      {children}
    </section>
  )
}
