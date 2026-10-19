import { config } from './config'
import type { BoardState } from './types'

export class ApiError extends Error {
  readonly status: number

  constructor(status: number, message: string) {
    super(message)
    this.status = status
  }
}

async function request<T>(method: 'GET' | 'POST', path: string, body?: unknown): Promise<T> {
  let response: Response

  try {
    response = await fetch(config.apiUrl + path, {
      method,
      headers: { Accept: 'application/json', 'Content-Type': 'application/json' },
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    throw new ApiError(0, 'Serverga ulanib bo\'lmadi')
  }

  const json = await response.json().catch(() => ({}))

  if (!response.ok) {
    throw new ApiError(response.status, (json as { message?: string }).message ?? `HTTP ${response.status}`)
  }

  return json as T
}

export interface OpenedBoard {
  token: string
  channel: string
  state: BoardState
}

/** A new board session with its secret token and pairing code. */
export function openBoard(name: string | null): Promise<OpenedBoard> {
  return request<OpenedBoard>('POST', '/boards', { name })
}

export async function fetchState(token: string): Promise<BoardState> {
  return (await request<{ state: BoardState }>('GET', `/boards/${token}/state`)).state
}

export async function refreshPairCode(token: string): Promise<BoardState> {
  return (await request<{ state: BoardState }>('POST', `/boards/${token}/pair-code`)).state
}
