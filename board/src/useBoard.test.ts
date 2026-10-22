import { act, renderHook } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { boardState } from './test/fixtures'
import type { BoardState } from './types'

const echo = vi.hoisted(() => ({ status: (_status: string) => {} }))
const api = vi.hoisted(() => ({ fetchState: vi.fn() }))

vi.mock('./api', () => ({
  ApiError: class extends Error {
    status = 0
  },
  fetchState: api.fetchState,
  openBoard: vi.fn(),
  refreshPairCode: vi.fn(),
}))

vi.mock('./echo', () => ({
  createEcho: () => {
    const channel = { listen: () => channel }
    return {
      channel: () => channel,
      connector: {
        onConnectionChange: (callback: (status: string) => void) => {
          echo.status = callback
          return () => {}
        },
      },
      disconnect: () => {},
    }
  },
}))

const { useBoard } = await import('./useBoard')

describe('useBoard', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    window.localStorage.setItem('lessonloop.board.token', 'token-1')
  })

  afterEach(() => {
    vi.useRealTimers()
    window.localStorage.clear()
    api.fetchState.mockReset()
  })

  it('without the WebSocket it follows the phone every few seconds and does not report an outage', async () => {
    let screenNow: BoardState['screen'] = 'lobby'
    api.fetchState.mockImplementation(async () => boardState({ screen: screenNow }))

    const { result } = renderHook(() => useBoard())
    await act(async () => {
      await vi.advanceTimersByTimeAsync(0)
    })
    act(() => echo.status('unavailable'))

    screenNow = 'question'
    await act(async () => {
      await vi.advanceTimersByTimeAsync(3_100)
    })

    expect(result.current.state?.screen).toBe('question')
    expect(result.current.connection).toBe('polling')
  })

  it('reports the outage when the server itself does not answer', async () => {
    api.fetchState.mockResolvedValueOnce(boardState())

    const { result } = renderHook(() => useBoard())
    await act(async () => {
      await vi.advanceTimersByTimeAsync(0)
    })
    act(() => echo.status('reconnecting'))

    api.fetchState.mockRejectedValue(new Error('offline'))
    await act(async () => {
      await vi.advanceTimersByTimeAsync(3_100)
    })

    expect(result.current.connection).toBe('reconnecting')
  })
})
