import { useCallback, useState } from 'react'

const KEY = 'rowad.backdrop-motion'

function read(): boolean {
  try {
    return window.localStorage.getItem(KEY) !== 'off'
  } catch {
    return true
  }
}

/** Remembered on/off preference for the background animation (on by default). */
export function useBackdropMotion() {
  const [enabled, setEnabled] = useState(read)

  const toggle = useCallback(() => {
    setEnabled((value) => {
      try {
        window.localStorage.setItem(KEY, value ? 'off' : 'on')
      } catch {
        // Preference simply isn't remembered.
      }
      return !value
    })
  }, [])

  return { enabled, toggle }
}
