import { act, renderHook } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { controllerSeenAt, useControllerOnline } from './controller'
import { boardState } from './test/fixtures'

afterEach(() => {
  vi.useRealTimers()
})

describe('controllerSeenAt', () => {
  it('turns the server-side age into a time on the board clock', () => {
    expect(controllerSeenAt(boardState({ controller_seen_seconds_ago: 10 }), 100_000)).toBe(90_000)
  })

  it('is empty without a paired phone or a heartbeat', () => {
    expect(controllerSeenAt(boardState({ is_paired: false, controller_seen_seconds_ago: 3 }), 100_000)).toBeNull()
    expect(controllerSeenAt(boardState({ controller_seen_seconds_ago: null }), 100_000)).toBeNull()
  })
})

describe('useControllerOnline', () => {
  it('goes offline 45 seconds after the last heartbeat and back online with the next one', () => {
    vi.useFakeTimers()
    const { result, rerender } = renderHook(({ seenAt }) => useControllerOnline(true, seenAt, 45), {
      initialProps: { seenAt: Date.now() - 10_000 },
    })

    expect(result.current).toBe(true)

    act(() => {
      vi.advanceTimersByTime(34_000)
    })
    expect(result.current).toBe(true)

    act(() => {
      vi.advanceTimersByTime(1_000)
    })
    expect(result.current).toBe(false)

    rerender({ seenAt: Date.now() })
    expect(result.current).toBe(true)
  })

  it('stays online on the pairing screen and offline for a paired board that was never pinged', () => {
    expect(renderHook(() => useControllerOnline(false, null)).result.current).toBe(true)
    expect(renderHook(() => useControllerOnline(true, null)).result.current).toBe(false)
  })
})
