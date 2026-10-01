import { useEffect, useRef, useState } from 'react'
import { useReducedMotion } from './useReducedMotion'

/**
 * Tweens toward `target` for a light "data updated" feel. The final value is always the exact
 * target; with reduced motion (or no target) it is returned immediately.
 */
export function useAnimatedNumber(target: number | null, duration = 600): number | null {
  const reduced = useReducedMotion()
  const [value, setValue] = useState<number | null>(target)
  const current = useRef<number | null>(target)

  useEffect(() => {
    const from = current.current
    if (target == null || from == null || reduced || from === target) {
      current.current = target
      setValue(target)
      return
    }

    let frame = 0
    const start = performance.now()
    const tick = (now: number) => {
      const progress = Math.min(1, (now - start) / duration)
      const eased = 1 - Math.pow(1 - progress, 3)
      const next = progress === 1 ? target : Math.round(from + (target - from) * eased)
      current.current = next
      setValue(next)
      if (progress < 1) frame = requestAnimationFrame(tick)
    }
    frame = requestAnimationFrame(tick)
    return () => cancelAnimationFrame(frame)
  }, [target, duration, reduced])

  return value
}
