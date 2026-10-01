import { AlertTriangle, DatabaseZap, RefreshCw, RotateCcw } from 'lucide-react'
import type { ReactNode } from 'react'
import { Button } from './Button'

interface StateProps {
  title: string
  message: string
  children?: ReactNode
  compact?: boolean
}

function StateShell({ icon, tone, title, message, children, compact }: StateProps & { icon: ReactNode; tone: string }) {
  return (
    <div
      className={`flex flex-col items-center justify-center gap-3 text-center ${compact ? 'min-h-52 py-6' : 'min-h-72 py-10'}`}
    >
      <div className={`grid size-14 place-items-center rounded-2xl ${tone}`} aria-hidden="true">
        {icon}
      </div>
      <h3 className="text-lg font-bold text-ink">{title}</h3>
      <p className="max-w-md text-sm leading-7 text-ink-muted">{message}</p>
      {children}
    </div>
  )
}

/** No records match the filters: absence of data, not a zero. */
export function EmptyState({ onReset, compact }: { onReset?: () => void; compact?: boolean }) {
  return (
    <StateShell
      compact={compact}
      icon={<DatabaseZap className="size-7 text-navy-600" />}
      tone="bg-navy-100"
      title="لا توجد بيانات مسجلة لهذا التحديد"
      message="لا توجد سجلات للمشروع والفترة والمكتب المختارة. غياب السجلات لا يعني صفرًا، بل أن البيانات غير متوفرة لهذا النطاق."
    >
      {onReset && (
        <Button variant="primary" onClick={onReset}>
          <RotateCcw className="size-4" aria-hidden="true" />
          إعادة ضبط الفلاتر
        </Button>
      )}
    </StateShell>
  )
}

export function ErrorState({
  message,
  onRetry,
  onReset,
}: {
  message: string
  onRetry?: () => void
  onReset?: () => void
}) {
  return (
    <div role="alert">
      <StateShell
        icon={<AlertTriangle className="size-7 text-danger" />}
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
              إعادة ضبط الفلاتر
            </Button>
          )}
        </div>
      </StateShell>
    </div>
  )
}
