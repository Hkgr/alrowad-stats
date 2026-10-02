import { AlertTriangle, CircleDashed, RefreshCw, RotateCcw } from 'lucide-react'
import type { ReactNode } from 'react'
import { Button } from './Button'

function StateShell({
  icon,
  tone,
  title,
  message,
  children,
  compact,
}: {
  icon: ReactNode
  tone: string
  title: string
  message?: string
  children?: ReactNode
  compact?: boolean
}) {
  return (
    <div className={`flex flex-col items-center justify-center gap-2.5 text-center ${compact ? 'py-8' : 'min-h-64 py-10'}`}>
      <div className={`grid size-12 place-items-center rounded-2xl ${tone}`} aria-hidden="true">
        {icon}
      </div>
      <h3 className="text-base font-bold text-ink">{title}</h3>
      {message && <p className="max-w-md text-sm leading-7 text-ink-muted">{message}</p>}
      {children}
    </div>
  )
}

/** No records for the current choice: absence of data, never a zero. */
export function NoDataState({ onReset, compact }: { onReset?: () => void; compact?: boolean }) {
  return (
    <StateShell
      compact={compact}
      icon={<CircleDashed className="size-6 text-ink-muted" />}
      tone="bg-paper-deep"
      title="لا توجد بيانات لهذه الفترة"
    >
      {onReset && (
        <Button onClick={onReset}>
          <RotateCcw className="size-4" aria-hidden="true" />
          إعادة الضبط
        </Button>
      )}
    </StateShell>
  )
}

export function ErrorState({ message, onRetry, onReset }: { message: string; onRetry?: () => void; onReset?: () => void }) {
  return (
    <div role="alert">
      <StateShell
        icon={<AlertTriangle className="size-6 text-danger" />}
        tone="bg-danger-soft"
        title="تعذّر تحميل البيانات"
        message={message}
      >
        <div className="flex flex-wrap justify-center gap-2">
          {onRetry && (
            <Button variant="primary" onClick={onRetry}>
              <RefreshCw className="size-4" aria-hidden="true" />
              إعادة المحاولة
            </Button>
          )}
          {onReset && (
            <Button onClick={onReset}>
              <RotateCcw className="size-4" aria-hidden="true" />
              إعادة الضبط
            </Button>
          )}
        </div>
      </StateShell>
    </div>
  )
}
