import { Building2, Landmark } from 'lucide-react'
import { useEffect } from 'react'
import { Link, Navigate, useSearchParams } from 'react-router-dom'
import { EmojiBackdrop } from '../../components/background/EmojiBackdrop'
import { useBackdropMotion } from '../../hooks/useBackdropMotion'
import { useFullscreen } from '../../hooks/useFullscreen'
import { useReducedMotion } from '../../hooks/useReducedMotion'
import { INSTITUTION_THEME, themeStyle } from '../../lib/themes'
import { SiteHeader } from '../../layout/SiteHeader'

const INSTITUTION_KEYS = ['institution', 'sector', 'main_activity', 'sub_activity', 'period', 'office', 'gender', 'age', 'disabled', 'view']

/** Shared dashboard links from before the gate still open the institution board. */
export function EntryPage() {
  const [params] = useSearchParams()
  if (INSTITUTION_KEYS.some((key) => params.has(key))) {
    return <Navigate to={`/institution?${params.toString()}`} replace />
  }
  return <HomePage />
}

export function HomePage() {
  const fullscreen = useFullscreen()
  const reducedMotion = useReducedMotion()
  const backdrop = useBackdropMotion()
  const theme = INSTITUTION_THEME

  useEffect(() => {
    document.title = 'إحصاءات الرواد'
  }, [])

  return (
    <div className="relative min-h-screen" style={{ ...themeStyle(theme), background: `linear-gradient(180deg, ${theme.light} 0, var(--color-paper) 28rem)` }}>
      <EmojiBackdrop symbols={theme.backdrop} animate={backdrop.enabled && !reducedMotion} />
      <div className="relative z-10">
        <SiteHeader context="المنظمة والمؤسسة" periodLabel={null} fullscreen={fullscreen} backdrop={backdrop} />
        <main className="mx-auto flex w-full max-w-5xl flex-col px-4 py-10 sm:px-6 sm:py-16">
          <div className="max-w-2xl">
            <h1 className="text-3xl font-extrabold text-ink sm:text-4xl">إحصاءات الرواد</h1>
            <p className="mt-3 text-base leading-8 text-ink-soft">اختر مجموعة البيانات. المنظمة تغطي البرامج والمناطق من 2017 حتى 2024، والمؤسسة تغطي الاستفادات المسجلة شهرياً.</p>
          </div>
          <div className="mt-8 grid gap-4 md:grid-cols-2">
            <Link
              to="/organization"
              className="group relative flex min-h-64 flex-col overflow-hidden rounded-3xl border border-line bg-white p-6 shadow-card transition-[transform,box-shadow] duration-200 hover:-translate-y-0.5 hover:shadow-lift"
            >
              <span aria-hidden="true" className="absolute inset-x-0 top-0 h-1.5" style={{ background: theme.primary }} />
              <span className="grid size-12 place-items-center rounded-2xl text-brand-ink" style={{ background: theme.light }}>
                <Landmark className="size-6" aria-hidden="true" />
              </span>
              <span className="mt-5 text-2xl font-extrabold text-ink">بيانات المنظمة</span>
              <span className="mt-2 text-sm leading-7 text-ink-soft">المستفيدون حسب البرنامج والمشروع والمنطقة والمركز، مع لقطة 2024 للكادر وذوي الاحتياجات والمكاتب.</span>
              <span className="mt-auto pt-6 text-sm font-extrabold text-brand-ink">عرض لوحة المنظمة</span>
            </Link>
            <Link
              to="/institution"
              className="group relative flex min-h-64 flex-col overflow-hidden rounded-3xl border border-line bg-white p-6 shadow-card transition-[transform,box-shadow] duration-200 hover:-translate-y-0.5 hover:shadow-lift"
            >
              <span aria-hidden="true" className="absolute inset-x-0 top-0 h-1.5" style={{ background: theme.primary }} />
              <span className="grid size-12 place-items-center rounded-2xl text-brand-ink" style={{ background: theme.light }}>
                <Building2 className="size-6" aria-hidden="true" />
              </span>
              <span className="mt-5 text-2xl font-extrabold text-ink">بيانات المؤسسة</span>
              <span className="mt-2 text-sm leading-7 text-ink-soft">الاستفادات المسجلة حسب المسار والمشروع والمكتب والشهر، مع الجنس والعمر والاحتياجات الخاصة.</span>
              <span className="mt-auto pt-6 text-sm font-extrabold text-brand-ink">عرض لوحة المؤسسة</span>
            </Link>
          </div>
        </main>
      </div>
    </div>
  )
}
