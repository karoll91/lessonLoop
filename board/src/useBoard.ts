import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError, fetchState, openBoard, refreshPairCode } from './api'
import { config } from './config'
import { createEcho, type BoardEcho } from './echo'
import type { BoardState, ConnectionStatus } from './types'

const TOKEN_KEY = 'lessonloop.board.token'

const storage = {
  get(): string | null {
    try {
      return window.localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set(token: string): void {
    try {
      window.localStorage.setItem(TOKEN_KEY, token)
    } catch {
      // Private mode: the board simply gets a new code after a reload.
    }
  },
}

export interface UseBoard {
  state: BoardState | null
  error: string | null
  connection: ConnectionStatus
  retry: () => void
}

/**
 * Keeps the board in sync with the backend: restores or opens a board
 * session, listens on board.{token}, re-reads the state after reconnects and
 * renews an expired pairing code.
 */
export function useBoard(): UseBoard {
  const [state, setState] = useState<BoardState | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [connection, setConnection] = useState<ConnectionStatus>('connecting')
  const [attempt, setAttempt] = useState(0)
  const tokenRef = useRef<string | null>(null)

  const retry = useCallback(() => setAttempt((value) => value + 1), [])

  useEffect(() => {
    let cancelled = false
    let echo: BoardEcho | null = null
    let stopConnectionWatch: (() => void) | null = null
    let poll: number | undefined
    let retryTimer: number | undefined

    const resync = async (): Promise<void> => {
      const token = tokenRef.current
      if (token === null) {
        return
      }
      try {
        const fresh = await fetchState(token)
        if (!cancelled) {
          setState(fresh)
        }
      } catch {
        // The connection overlay already tells the class something is wrong.
      }
    }

    const start = async (): Promise<void> => {
      try {
        let token = storage.get()
        let initial: BoardState | null = null

        if (token !== null) {
          try {
            initial = await fetchState(token)
          } catch (exception) {
            if (exception instanceof ApiError && exception.status === 404) {
              token = null
            } else {
              throw exception
            }
          }
        }

        if (token === null || initial === null) {
          const opened = await openBoard(config.boardName)
          token = opened.token
          initial = opened.state
        }

        if (cancelled) {
          return
        }

        storage.set(token)
        tokenRef.current = token
        setState(initial)
        setError(null)

        echo = createEcho()
        echo.channel(`board.${token}`).listen('.board.updated', (fresh: BoardState) => setState(fresh))

        let wasConnected = false
        stopConnectionWatch = echo.connector.onConnectionChange((status) => {
          setConnection(status)
          if (status === 'connected') {
            if (wasConnected) {
              void resync()
            }
            wasConnected = true
          }
        })

        poll = window.setInterval(() => void resync(), config.pollIntervalMs)
      } catch (exception) {
        if (cancelled) {
          return
        }
        setError(exception instanceof Error ? exception.message : 'Noma\'lum xato')
        retryTimer = window.setTimeout(() => setAttempt((value) => value + 1), 5000)
      }
    }

    void start()

    return () => {
      cancelled = true
      stopConnectionWatch?.()
      echo?.disconnect()
      window.clearInterval(poll)
      window.clearTimeout(retryTimer)
    }
  }, [attempt])

  const expiresAt = state?.screen === 'pairing' ? state.pair_code_expires_at : null

  useEffect(() => {
    if (expiresAt == null) {
      return
    }

    const delay = Math.max(0, Date.parse(expiresAt) - Date.now()) + 500
    const timer = window.setTimeout(async () => {
      const token = tokenRef.current
      if (token === null) {
        return
      }
      try {
        setState(await refreshPairCode(token))
      } catch {
        // Retried on the next poll or reconnect.
      }
    }, delay)

    return () => window.clearTimeout(timer)
  }, [expiresAt])

  return { state, error, connection, retry }
}
