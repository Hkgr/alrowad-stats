import { useEffect, useState, type CSSProperties } from 'react'

/**
 * True until the first content (once `ready`) has had time to play its entrance, then false for
 * the rest of the session. Sections add the `intro` class only while this is true, so the
 * staggered entrance plays once and never again on filter changes.
 */
export function useIntro(ready: boolean, duration = 1100): boolean {
  const [done, setDone] = useState(false)

  useEffect(() => {
    if (!ready || done) return
    const timer = window.setTimeout(() => setDone(true), duration)
    return () => window.clearTimeout(timer)
  }, [ready, done, duration])

  return !done
}

/** Style props for one entrance step (0 = header, 1 = tracks, 2 = figures, 3 = charts). */
export function introProps(active: boolean, step: number): { className: string; style?: CSSProperties } {
  return active ? { className: 'intro', style: { animationDelay: `${step * 90}ms` } } : { className: '' }
}
