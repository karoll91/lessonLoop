import { useCallback, useEffect, useRef, useState } from 'react'
import { ApiError, fetchState, openBoard, refreshPairCode } from './api'
import { config } from './config'
import { controllerSeenAt, useControllerOnline } from './controller'
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
  /** False while the paired phone is silent (D9 «Pult uzildi»). */
  controllerOnline: boolean
  /** This board's secret token (teacher mode claims the board with it). */
  token: string | null
  retry: () => void
}

/**
 * Keeps the board in sync with the backend: restores or opens a board
 * session, listens on board.{token}, re-reads the state after reconnects and
 * renews an expired pairing code. Also tracks the phone's heartbeats. While
 * the WebSocket is down it asks for the state every few seconds instead.
 */
export function useBoard(): UseBoard {
  const [state, setBoardState] = useState<BoardState | null>(null)
  const [seenAt, setSeenAt] = useState<number | null>(null)
  const [error, setError] = useState<string | null>(null)
  const [socket, setSocket] = useState<ConnectionStatus>('connecting')
  const [serverOk, setServerOk] = useState(true)
  const [attempt, setAttempt] = useState(0)
  const [token, setToken] = useState<string | null>(null)
  const tokenRef = useRef<string | null>(null)

  const retry = useCallback(() => setAttempt((value) => value + 1), [])

  const setState = useCallback((fresh: BoardState) => {
    setBoardState(fresh)
    setSeenAt(controllerSeenAt(fresh, Date.now()))
  }, [])

  useEffect(() => {
    let cancelled = false
    let echo: BoardEcho | null = null
    let stopConnectionWatch: (() => void) | null = null
    let poll: number | undefined
    let live = false
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
          setServerOk(true)
        }
      } catch {
        // Shown as «Server bilan aloqa yo'q» once the WebSocket is down too.
        if (!cancelled) {
          setServerOk(false)
        }
      }
    }

    const schedulePoll = (): void => {
      window.clearTimeout(poll)
      poll = window.setTimeout(
        async () => {
          await resync()
          if (!cancelled) {
            schedulePoll()
          }
        },
        live ? config.pollIntervalMs : config.fallbackPollMs,
      )
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
        setToken(token)
        setState(initial)
        setError(null)

        echo = createEcho()
        echo
          .channel(`board.${token}`)
          .listen('.board.updated', (fresh: BoardState) => setState(fresh))
          .listen('.board.controller-seen', () => setSeenAt(Date.now()))

        let wasConnected = false
        stopConnectionWatch = echo.connector.onConnectionChange((status) => {
          setSocket(status)
          const wasLive = live
          live = status === 'connected'
          if (live) {
            if (wasConnected) {
              void resync()
            }
            wasConnected = true
          }
          if (live !== wasLive) {
            schedulePoll()
          }
        })

        schedulePoll()
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
      window.clearTimeout(poll)
      window.clearTimeout(retryTimer)
    }
  }, [attempt, setState])

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
  }, [expiresAt, setState])

  const controllerOnline = useControllerOnline(state?.is_paired ?? false, seenAt, state?.controller_timeout)

  const connection: ConnectionStatus = socket === 'connected' ? 'connected' : serverOk ? 'polling' : socket

  return { state, error, connection, controllerOnline, token, retry }
}
