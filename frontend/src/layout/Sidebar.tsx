import { BarChart3, Building2, ChevronsLeft, ChevronsRight, DatabaseBackup, LayoutDashboard, Layers, X } from 'lucide-react'
import type { ComponentType } from 'react'

interface NavItem {
  label: string
  icon: ComponentType<{ className?: string; 'aria-hidden'?: boolean }>
  current?: boolean
  hint?: string
}

const NAV: NavItem[] = [
  { label: 'لوحة العرض', icon: LayoutDashboard, current: true },
  { label: 'المشاريع', icon: Layers, hint: 'قريبًا' },
  { label: 'المكاتب', icon: Building2, hint: 'قريبًا' },
  { label: 'إدارة البيانات', icon: DatabaseBackup, hint: 'المرحلة الثانية' },
]

interface SidebarProps {
  collapsed: boolean
  onToggleCollapsed: () => void
  mobileOpen: boolean
  onCloseMobile: () => void
}

export function Sidebar({ collapsed, onToggleCollapsed, mobileOpen, onCloseMobile }: SidebarProps) {
  const CollapseIcon = collapsed ? ChevronsLeft : ChevronsRight

  return (
    <>
      {/* Mobile backdrop */}
      <div
        aria-hidden="true"
        onClick={onCloseMobile}
        className={`fixed inset-0 z-30 bg-navy-950/60 backdrop-blur-[2px] transition-opacity lg:hidden ${
          mobileOpen ? 'opacity-100' : 'pointer-events-none opacity-0'
        }`}
      />

      <aside
        id="app-sidebar"
        aria-label="التنقل الرئيسي"
        className={[
          'z-40 flex flex-col bg-gradient-to-b from-navy-950 via-navy-900 to-navy-800 text-white',
          'fixed inset-y-0 right-0 w-72 transition-transform duration-300',
          mobileOpen ? 'translate-x-0' : 'invisible translate-x-full',
          'lg:visible lg:sticky lg:top-0 lg:h-screen lg:shrink-0 lg:translate-x-0 lg:transition-[width]',
          collapsed ? 'lg:w-[84px]' : 'lg:w-72',
        ].join(' ')}
      >
        <div className="flex items-center gap-3 px-5 pb-4 pt-6">
          <div className="grid size-11 shrink-0 place-items-center rounded-xl bg-brand text-navy-950 shadow-lg shadow-brand/30">
            <BarChart3 className="size-6" aria-hidden={true} />
          </div>
          <div className={`min-w-0 ${collapsed ? 'lg:hidden' : ''}`}>
            <p className="truncate text-lg font-extrabold leading-tight">إحصاءات الرواد</p>
            <p className="truncate text-xs text-navy-300">منصة عرض وتحليل البيانات</p>
          </div>
          <button
            type="button"
            onClick={onCloseMobile}
            aria-label="إغلاق القائمة"
            className="ms-auto grid size-10 cursor-pointer place-items-center rounded-lg text-navy-200 hover:bg-white/10 lg:hidden"
          >
            <X className="size-5" aria-hidden={true} />
          </button>
        </div>

        <nav className="mt-2 flex-1 px-3" aria-label="الأقسام">
          <ul className="space-y-1">
            {NAV.map(({ label, icon: Icon, current, hint }) => (
              <li key={label}>
                {current ? (
                  <a
                    href="/"
                    aria-current="page"
                    title={label}
                    className="flex min-h-11 items-center gap-3 rounded-xl bg-white/12 px-3 text-sm font-bold text-white ring-1 ring-white/15"
                  >
                    <Icon className="size-5 shrink-0 text-brand" aria-hidden={true} />
                    <span className={collapsed ? 'lg:hidden' : ''}>{label}</span>
                  </a>
                ) : (
                  <span
                    aria-disabled="true"
                    title={`${label} — ${hint}`}
                    className="flex min-h-11 cursor-not-allowed items-center gap-3 rounded-xl px-3 text-sm font-semibold text-navy-300/70"
                  >
                    <Icon className="size-5 shrink-0" aria-hidden={true} />
                    <span className={`flex-1 ${collapsed ? 'lg:hidden' : ''}`}>{label}</span>
                    <span
                      className={`rounded-full bg-white/8 px-2 py-0.5 text-[11px] font-medium text-navy-200 ${collapsed ? 'lg:hidden' : ''}`}
                    >
                      {hint}
                    </span>
                  </span>
                )}
              </li>
            ))}
          </ul>
        </nav>

        <div className="hidden border-t border-white/10 p-3 lg:block">
          <button
            type="button"
            onClick={onToggleCollapsed}
            aria-expanded={!collapsed}
            aria-controls="app-sidebar"
            className="flex min-h-11 w-full cursor-pointer items-center gap-3 rounded-xl px-3 text-sm font-semibold text-navy-200 hover:bg-white/10"
          >
            <CollapseIcon className="size-5 shrink-0" aria-hidden={true} />
            <span className={collapsed ? 'hidden' : ''}>طيّ الشريط الجانبي</span>
            {collapsed && <span className="sr-only">توسيع الشريط الجانبي</span>}
          </button>
        </div>
      </aside>
    </>
  )
}
