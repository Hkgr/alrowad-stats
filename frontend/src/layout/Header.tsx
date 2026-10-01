import { Maximize2, Menu, Minimize2, RotateCcw } from 'lucide-react'
import { Button } from '../components/ui/Button'

interface HeaderProps {
  institutionName: string | null
  /** Short description of what is currently on screen, e.g. "لعبة وفرحة · أيار 2026". */
  scopeLabel: string | null
  fullscreen: { active: boolean; supported: boolean; toggle: () => void }
  onOpenMenu: () => void
  onReset: () => void
  canReset: boolean
}

export function Header({ institutionName, scopeLabel, fullscreen, onOpenMenu, onReset, canReset }: HeaderProps) {
  const FullscreenIcon = fullscreen.active ? Minimize2 : Maximize2

  return (
    <header className="relative overflow-hidden rounded-3xl bg-gradient-to-br from-navy-950 via-navy-900 to-navy-700 p-5 text-white shadow-pop sm:p-7">
      {/* Calm depth: two soft glows, no ornament. */}
      <div aria-hidden="true" className="pointer-events-none absolute -start-24 -top-28 size-80 rounded-full bg-brand/20 blur-3xl" />
      <div aria-hidden="true" className="pointer-events-none absolute -bottom-32 end-10 size-96 rounded-full bg-navy-600/40 blur-3xl" />

      <div className="relative flex flex-wrap items-start justify-between gap-4">
        <div className="flex min-w-0 items-start gap-3">
          <button
            type="button"
            onClick={onOpenMenu}
            aria-label="فتح القائمة"
            aria-controls="app-sidebar"
            className="grid size-11 shrink-0 cursor-pointer place-items-center rounded-xl border border-white/20 bg-white/10 hover:bg-white/20 lg:hidden"
          >
            <Menu className="size-5" aria-hidden={true} />
          </button>
          <div className="min-w-0">
            <p className="text-sm font-semibold text-brand">{institutionName ?? 'إحصاءات الرواد'}</p>
            <h1 className="mt-1 text-2xl font-extrabold leading-tight sm:text-3xl lg:text-4xl">لوحة الاستفادات المسجلة</h1>
            <p className="mt-2 max-w-2xl text-sm leading-7 text-navy-200">
              عرض وتحليل بيانات مشاريع المؤسسة حسب المكتب والفترة والجنس، مع تفاعل مباشر بين الفلاتر والرسوم.
            </p>
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-2">
          {scopeLabel && (
            <span className="rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-sm font-semibold text-white">
              {scopeLabel}
            </span>
          )}
          {canReset && (
            <Button variant="onDark" onClick={onReset}>
              <RotateCcw className="size-4" aria-hidden={true} />
              إعادة الضبط
            </Button>
          )}
          {fullscreen.supported && (
            <Button
              variant="onDark"
              onClick={fullscreen.toggle}
              aria-pressed={fullscreen.active}
              title="ملء الشاشة (F)"
            >
              <FullscreenIcon className="size-4" aria-hidden={true} />
              {fullscreen.active ? 'إنهاء ملء الشاشة' : 'وضع العرض'}
            </Button>
          )}
        </div>
      </div>
    </header>
  )
}
