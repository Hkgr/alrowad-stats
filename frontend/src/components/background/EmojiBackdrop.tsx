import { memo, useEffect, useState } from 'react'

/**
 * Fixed slots (percent of the viewport) hugging the edges, so symbols stay in the margins and
 * behind the opaque cards; never regenerated on render or filter change. The first `MOBILE`
 * slots are the only ones shown on small screens.
 */
const SLOTS = [
  { top: 9, side: 'right', inset: 1.5, size: 2.6, duration: 31, delay: -4 },
  { top: 22, side: 'left', inset: 1.2, size: 2.2, duration: 38, delay: -12 },
  { top: 47, side: 'right', inset: 2.2, size: 2.4, duration: 27, delay: -7 },
  { top: 66, side: 'left', inset: 2, size: 2.8, duration: 43, delay: -20 },
  { top: 86, side: 'right', inset: 1, size: 2.2, duration: 34, delay: -15 },
  { top: 4, side: 'left', inset: 6, size: 2, duration: 40, delay: -9 },
  { top: 35, side: 'right', inset: 5.5, size: 2, duration: 29, delay: -18 },
  { top: 56, side: 'left', inset: 5, size: 2.3, duration: 36, delay: -2 },
  { top: 78, side: 'left', inset: 1, size: 2, duration: 45, delay: -25 },
  { top: 93, side: 'left', inset: 7, size: 2.4, duration: 32, delay: -11 },
  { top: 15, side: 'right', inset: 7, size: 1.9, duration: 41, delay: -30 },
  { top: 72, side: 'right', inset: 6.5, size: 2.1, duration: 26, delay: -5 },
] as const

const MOBILE = 5

interface Props {
  symbols: string[]
  /** User preference (persisted by the caller). */
  animate: boolean
}

/** Quiet humanitarian symbols behind the content. Decorative only: hidden from assistive tech. */
export const EmojiBackdrop = memo(function EmojiBackdrop({ symbols, animate }: Props) {
  const [pageVisible, setPageVisible] = useState(() => document.visibilityState !== 'hidden')

  // No animation work while the tab is in the background.
  useEffect(() => {
    const sync = () => setPageVisible(document.visibilityState !== 'hidden')
    document.addEventListener('visibilitychange', sync)
    return () => document.removeEventListener('visibilitychange', sync)
  }, [])

  const running = animate && pageVisible

  return (
    <div aria-hidden="true" className="emoji-backdrop" data-running={running ? 'true' : 'false'}>
      {SLOTS.map((slot, index) => (
        <span
          key={index}
          className={index >= MOBILE ? 'emoji-float hidden md:block' : 'emoji-float'}
          style={{
            top: `${slot.top}%`,
            [slot.side]: `${slot.inset}%`,
            fontSize: `${slot.size}rem`,
            animationDuration: `${slot.duration}s`,
            animationDelay: `${slot.delay}s`,
          }}
        >
          {symbols[index % symbols.length]}
        </span>
      ))}
    </div>
  )
})
