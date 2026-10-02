import { Maximize2, Minimize2, Sparkles } from 'lucide-react'
import type { CSSProperties, ReactNode } from 'react'
import logo from '../assets/brand/rowad-logo.png'

interface SiteHeaderProps {
  /** Selected period label, or a short "all periods" label. */
  periodLabel: string | null
  search: ReactNode
  fullscreen: { active: boolean; supported: boolean; toggle: () => void }
  /** Background symbols animation preference. */
  backdrop: { enabled: boolean; toggle: () => void }
  className?: string
  style?: CSSProperties
}

/** Light header: logo, short title, the chosen period, project search and presentation mode. */
export function SiteHeader({ periodLabel, search, fullscreen, backdrop, className = '', style }: SiteHeaderProps) {
  const FullscreenIcon = fullscreen.active ? Minimize2 : Maximize2

  return (
    <header className={`relative z-30 border-b border-line bg-white/95 backdrop-blur lg:sticky lg:top-0 ${className}`} style={style}>
      <div className="brand-rule" aria-hidden="true" />
      <div className="mx-auto flex max-w-[1440px] flex-wrap items-center gap-x-5 gap-y-3 px-4 py-2.5 sm:px-6 lg:flex-nowrap lg:px-8">
        <a href="/" className="flex shrink-0 items-center gap-4 rounded-lg" aria-label="إحصاءات الرواد — النظرة العامة">
          <img
            src={logo}
            alt="مؤسسة الرواد للتعاون والتنمية"
            width={1000}
            height={381}
            className="h-14 w-auto sm:h-16 lg:h-[76px]"
          />
          <span className="hidden h-10 w-px bg-line-strong sm:block" aria-hidden="true" />
          <span className="hidden min-w-0 sm:block">
            <span className="block text-lg font-extrabold leading-tight text-ink">إحصاءات الرواد</span>
            <span className="block text-sm text-ink-muted">
              الاستفادات المسجلة
              {periodLabel && (
                <>
                  {' · '}
                  <span className="font-bold text-brand-ink">{periodLabel}</span>
                </>
              )}
            </span>
          </span>
        </a>

        <div className="order-last w-full lg:order-none lg:ms-auto lg:w-[26rem]">{search}</div>

        <button
          type="button"
          onClick={backdrop.toggle}
          aria-pressed={backdrop.enabled}
          title={backdrop.enabled ? 'إيقاف حركة الخلفية' : 'تشغيل حركة الخلفية'}
          className="ms-auto inline-flex h-11 shrink-0 cursor-pointer items-center gap-2 rounded-full border border-line-strong bg-white px-3.5 text-sm font-semibold text-ink hover:border-brand hover:text-brand-ink lg:ms-0"
        >
          <Sparkles className={`size-4 ${backdrop.enabled ? 'text-brand' : 'text-ink-muted'}`} aria-hidden="true" />
          <span className="hidden xl:inline">{backdrop.enabled ? 'إيقاف الحركة' : 'تشغيل الحركة'}</span>
          <span className="sr-only xl:hidden">{backdrop.enabled ? 'إيقاف حركة الخلفية' : 'تشغيل حركة الخلفية'}</span>
        </button>

        {fullscreen.supported && (
          <button
            type="button"
            onClick={fullscreen.toggle}
            aria-pressed={fullscreen.active}
            title="وضع العرض (F)"
            className="inline-flex h-11 shrink-0 cursor-pointer items-center gap-2 rounded-full border border-line-strong bg-white px-4 text-sm font-semibold text-ink hover:border-brand hover:text-brand-ink"
          >
            <FullscreenIcon className="size-4" aria-hidden="true" />
            <span className="hidden sm:inline">{fullscreen.active ? 'إنهاء العرض' : 'وضع العرض'}</span>
            <span className="sr-only sm:hidden">{fullscreen.active ? 'إنهاء ملء الشاشة' : 'ملء الشاشة'}</span>
          </button>
        )}
      </div>
    </header>
  )
}
