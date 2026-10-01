import { useCallback, useEffect, useState } from 'react'

/** Browser fullscreen for presentations/meetings. State follows the real fullscreen element (Esc exits). */
export function useFullscreen() {
  const [active, setActive] = useState(() => document.fullscreenElement != null)
  const supported = typeof document.documentElement.requestFullscreen === 'function'

  useEffect(() => {
    const sync = () => setActive(document.fullscreenElement != null)
    document.addEventListener('fullscreenchange', sync)
    return () => document.removeEventListener('fullscreenchange', sync)
  }, [])

  const toggle = useCallback(async () => {
    if (!supported) return
    try {
      if (document.fullscreenElement) await document.exitFullscreen()
      else await document.documentElement.requestFullscreen()
    } catch {
      // The browser refused (e.g. no user gesture); stay in the current mode.
    }
  }, [supported])

  return { active, supported, toggle }
}
