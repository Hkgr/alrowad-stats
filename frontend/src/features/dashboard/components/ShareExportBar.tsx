import { Check, ChevronDown, Download, FileSpreadsheet, FileText, Link2, Printer } from 'lucide-react'
import { useEffect, useRef, useState } from 'react'
import { filtersQuery } from '../../../lib/query'
import { apiUrl } from '../../../services/api/client'
import type { FilterState } from '../../../types/api'

async function copyText(text: string): Promise<boolean> {
  try {
    await navigator.clipboard.writeText(text)
    return true
  } catch {
    // Clipboard API unavailable (insecure origin, permissions): fall back to a temporary selection.
    const field = document.createElement('textarea')
    field.value = text
    field.setAttribute('readonly', '')
    field.style.position = 'fixed'
    field.style.opacity = '0'
    document.body.appendChild(field)
    field.select()
    try {
      return document.execCommand('copy')
    } catch {
      return false
    } finally {
      field.remove()
    }
  }
}

const button =
  'inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg border border-line-strong bg-white px-3 text-sm font-semibold text-ink hover:border-brand hover:text-brand-ink'

/**
 * Copy the link of the current view, download its numbers (Excel / CSV, same filters) or open the
 * print-ready report. The exploration state already lives in the URL, so the link is the view.
 */
export function ShareExportBar({ filters }: { filters: FilterState }) {
  const [copied, setCopied] = useState<'ok' | 'failed' | null>(null)
  const [menuOpen, setMenuOpen] = useState(false)
  const menu = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!copied) return
    const timer = window.setTimeout(() => setCopied(null), 2500)
    return () => window.clearTimeout(timer)
  }, [copied])

  useEffect(() => {
    if (!menuOpen) return
    const close = (event: Event) => {
      if (event instanceof KeyboardEvent ? event.key === 'Escape' : !menu.current?.contains(event.target as Node)) setMenuOpen(false)
    }
    document.addEventListener('mousedown', close)
    document.addEventListener('keydown', close)
    return () => {
      document.removeEventListener('mousedown', close)
      document.removeEventListener('keydown', close)
    }
  }, [menuOpen])

  const download = (format: 'xlsx' | 'csv') => apiUrl('/export', { ...filters, format })
  const item = 'flex min-h-10 w-full cursor-pointer items-center gap-2 px-3 text-start text-sm font-semibold text-ink hover:bg-paper-deep'

  return (
    <div className="flex flex-wrap items-center gap-2" role="group" aria-label="مشاركة وتصدير">
      <button
        type="button"
        className={button}
        onClick={async () => setCopied((await copyText(window.location.href)) ? 'ok' : 'failed')}
      >
        {copied === 'ok' ? <Check className="size-4 text-[#1a6b3a]" aria-hidden="true" /> : <Link2 className="size-4" aria-hidden="true" />}
        نسخ الرابط
      </button>

      <div ref={menu} className="relative">
        <button type="button" className={button} aria-expanded={menuOpen} aria-haspopup="true" onClick={() => setMenuOpen((open) => !open)}>
          <Download className="size-4" aria-hidden="true" />
          تصدير
          <ChevronDown className={`size-4 transition-transform ${menuOpen ? 'rotate-180' : ''}`} aria-hidden="true" />
        </button>
        {menuOpen && (
          <div className="absolute end-0 top-full z-30 mt-1 w-56 overflow-hidden rounded-xl border border-line-strong bg-white shadow-card">
            <a className={item} href={download('xlsx')} download onClick={() => setMenuOpen(false)}>
              <FileSpreadsheet className="size-4 text-ink-muted" aria-hidden="true" />
              Excel (عدة أوراق)
            </a>
            <a className={item} href={download('csv')} download onClick={() => setMenuOpen(false)}>
              <FileText className="size-4 text-ink-muted" aria-hidden="true" />
              CSV (جدول التفاصيل)
            </a>
            <p className="border-t border-line px-3 py-2 text-xs leading-5 text-ink-muted">بحسب الفلاتر المطبّقة الآن.</p>
          </div>
        )}
      </div>

      <a className={button} href={`/report?${filtersQuery(filters)}`} target="_blank" rel="noopener">
        <Printer className="size-4" aria-hidden="true" />
        تقرير للطباعة
      </a>

      <span role="status" aria-live="polite" className="text-sm font-semibold text-ink-muted">
        {copied === 'ok' ? 'تم نسخ الرابط' : copied === 'failed' ? 'تعذّر النسخ، انسخ الرابط من شريط العنوان' : ''}
      </span>
    </div>
  )
}
