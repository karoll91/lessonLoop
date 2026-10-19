import { useCallback, useEffect, useState } from 'react'

/** Current time, refreshed every second while mounted. */
export function useNow(): number {
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    const timer = window.setInterval(() => setNow(Date.now()), 1000)
    return () => window.clearInterval(timer)
  }, [])

  return now
}

/**
 * Time the component mounted. Screens that count time (question timer,
 * pause clock) are keyed by the current answer, so they remount — and the
 * count restarts — when the board moves on.
 */
export function useMountedAt(): number {
  const [mountedAt] = useState(() => Date.now())

  return mountedAt
}

/** True once `delayMs` has passed since `key` last changed. */
export function useRevealAfter(key: string, delayMs: number): boolean {
  const [revealedKey, setRevealedKey] = useState<string | null>(null)

  useEffect(() => {
    const timer = window.setTimeout(() => setRevealedKey(key), delayMs)
    return () => window.clearTimeout(timer)
  }, [key, delayMs])

  return revealedKey === key
}

export type Theme = 'light' | 'dark'

const THEME_KEY = 'lessonloop.board.theme'

function initialTheme(): Theme {
  try {
    const saved = window.localStorage.getItem(THEME_KEY)
    if (saved === 'light' || saved === 'dark') {
      return saved
    }
  } catch {
    // Storage blocked: fall back to the system preference.
  }
  return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'
}

/** Light/dark theme with a toggle; remembered per board PC. */
export function useTheme(): [Theme, () => void] {
  const [theme, setTheme] = useState<Theme>(initialTheme)

  useEffect(() => {
    document.documentElement.dataset.theme = theme
    try {
      window.localStorage.setItem(THEME_KEY, theme)
    } catch {
      // Not remembered, still applied.
    }
  }, [theme])

  const toggle = useCallback(() => setTheme((current) => (current === 'dark' ? 'light' : 'dark')), [])

  return [theme, toggle]
}

export function toggleFullscreen(): void {
  if (document.fullscreenElement) {
    void document.exitFullscreen()
  } else {
    void document.documentElement.requestFullscreen?.()
  }
}
