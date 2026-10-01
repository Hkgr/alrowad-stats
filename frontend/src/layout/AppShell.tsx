import { useCallback, useEffect, useState, type ReactNode } from 'react'
import { readStored, writeStored } from '../lib/storage'
import { Sidebar } from './Sidebar'

const COLLAPSE_KEY = 'rowad.sidebar.collapsed'

interface AppShellProps {
  /** Receives helpers so the page header can open the mobile drawer. */
  children: (shell: { openMenu: () => void }) => ReactNode
  /** Presentation mode (fullscreen) hides the sidebar. */
  presenting: boolean
}

export function AppShell({ children, presenting }: AppShellProps) {
  const [collapsed, setCollapsed] = useState(() => readStored(COLLAPSE_KEY) === '1')
  const [mobileOpen, setMobileOpen] = useState(false)

  const toggleCollapsed = useCallback(() => {
    setCollapsed((value) => {
      writeStored(COLLAPSE_KEY, value ? '0' : '1')
      return !value
    })
  }, [])

  useEffect(() => {
    if (!mobileOpen) return
    const onKey = (event: KeyboardEvent) => event.key === 'Escape' && setMobileOpen(false)
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [mobileOpen])

  return (
    <div className="min-h-screen lg:flex">
      <a
        href="#main"
        className="sr-only focus:not-sr-only focus:fixed focus:start-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand focus:px-4 focus:py-2 focus:font-bold focus:text-navy-950"
      >
        تخطي إلى المحتوى
      </a>

      <div className={presenting ? 'hidden' : 'contents'}>
        <Sidebar
          collapsed={collapsed}
          onToggleCollapsed={toggleCollapsed}
          mobileOpen={mobileOpen}
          onCloseMobile={() => setMobileOpen(false)}
        />
      </div>

      <div className="min-w-0 flex-1">
        <main id="main" tabIndex={-1} className="mx-auto w-full max-w-[1480px] px-4 pb-16 pt-4 sm:px-6 lg:px-8 lg:pt-6">
          {children({ openMenu: () => setMobileOpen(true) })}
        </main>
      </div>
    </div>
  )
}
