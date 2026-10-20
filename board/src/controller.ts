import { useEffect, useState } from 'react'
import type { BoardState } from './types'

/** Used when the backend does not send controller_timeout. */
const DEFAULT_TIMEOUT_SECONDS = 45

/**
 * When the paired phone was last heard from, on this computer's clock. The
 * server sends an age in seconds rather than a timestamp, so a board PC with
 * a wrong clock still counts correctly.
 */
export function controllerSeenAt(state: BoardState, now: number): number | null {
  if (!state.is_paired || state.controller_seen_seconds_ago == null) {
    return null
  }
  return now - state.controller_seen_seconds_ago * 1000
}

/**
 * D9 «Pult uzildi»: false once the paired phone has been silent for the
 * timeout, true again as soon as a heartbeat moves seenAt forward.
 */
export function useControllerOnline(isPaired: boolean, seenAt: number | null, timeoutSeconds = DEFAULT_TIMEOUT_SECONDS): boolean {
  const [expired, setExpired] = useState<number | null>(null)

  useEffect(() => {
    if (!isPaired || seenAt === null) {
      return
    }
    const timer = window.setTimeout(() => setExpired(seenAt), Math.max(0, seenAt + timeoutSeconds * 1000 - Date.now()))
    return () => window.clearTimeout(timer)
  }, [isPaired, seenAt, timeoutSeconds])

  if (!isPaired) {
    return true
  }
  if (seenAt === null) {
    return false
  }
  return expired !== seenAt
}
