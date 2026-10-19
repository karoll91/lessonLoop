const API_URL = (import.meta.env.VITE_API_URL as string | undefined) ?? `http://${window.location.hostname}:8000/api`

const TOKEN_KEY = 'lessonloop.admin.token'

export const tokenStore = {
  get(): string | null {
    try {
      return window.localStorage.getItem(TOKEN_KEY)
    } catch {
      return null
    }
  },
  set(token: string | null): void {
    try {
      if (token === null) {
        window.localStorage.removeItem(TOKEN_KEY)
      } else {
        window.localStorage.setItem(TOKEN_KEY, token)
      }
    } catch {
      // Session only lasts until reload.
    }
  },
}

/** Error from the API, with Laravel validation messages per field. */
export class ApiError extends Error {
  readonly status: number
  readonly code: string | null
  readonly errors: Record<string, string[]>

  constructor(status: number, message: string, code: string | null = null, errors: Record<string, string[]> = {}) {
    super(message)
    this.status = status
    this.code = code
    this.errors = errors
  }

  /** First message for a field, e.g. `fieldError('name')`. */
  fieldError(field: string): string | undefined {
    return this.errors[field]?.[0]
  }
}

type Listener = () => void
const unauthorizedListeners = new Set<Listener>()

/** Called when the API answers 401: the token expired or the account was blocked. */
export function onUnauthorized(listener: Listener): () => void {
  unauthorizedListeners.add(listener)
  return () => unauthorizedListeners.delete(listener)
}

export type Query = Record<string, string | number | boolean | null | undefined>

function url(path: string, query?: Query): string {
  const params = new URLSearchParams()
  Object.entries(query ?? {}).forEach(([key, value]) => {
    if (value !== null && value !== undefined && value !== '' && value !== false) {
      params.set(key, value === true ? '1' : String(value))
    }
  })
  const search = params.toString()
  return `${API_URL}${path}${search ? `?${search}` : ''}`
}

async function send(method: string, path: string, body?: unknown, query?: Query): Promise<Response> {
  const token = tokenStore.get()
  const isForm = body instanceof FormData
  const headers: Record<string, string> = { Accept: 'application/json' }

  if (!isForm && body !== undefined) {
    headers['Content-Type'] = 'application/json'
  }
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  let response: Response
  try {
    response = await fetch(url(path, query), {
      method,
      headers,
      body: body === undefined ? undefined : isForm ? body : JSON.stringify(body),
    })
  } catch {
    throw new ApiError(0, "Serverga ulanib bo'lmadi. Internet yoki serverni tekshiring.")
  }

  if (!response.ok) {
    const json = (await response.json().catch(() => ({}))) as { message?: string; code?: string; errors?: Record<string, string[]> }

    if (response.status === 401 && token) {
      unauthorizedListeners.forEach((listener) => listener())
    }

    throw new ApiError(response.status, json.message ?? `Xatolik (${response.status})`, json.code ?? null, json.errors ?? {})
  }

  return response
}

export const api = {
  async get<T>(path: string, query?: Query): Promise<T> {
    return (await send('GET', path, undefined, query)).json() as Promise<T>
  },
  async post<T>(path: string, body?: unknown): Promise<T> {
    const response = await send('POST', path, body ?? {})
    return (response.status === 204 ? undefined : await response.json()) as T
  },
  async patch<T>(path: string, body: unknown): Promise<T> {
    return (await send('PATCH', path, body)).json() as Promise<T>
  },
  async delete(path: string): Promise<void> {
    await send('DELETE', path)
  },
  /** Download a file (journal export, import template) with the auth header. */
  async download(path: string, fallbackName: string, query?: Query): Promise<void> {
    const response = await send('GET', path, undefined, query)
    const disposition = response.headers.get('Content-Disposition') ?? ''
    const name = /filename="?([^";]+)"?/.exec(disposition)?.[1] ?? fallbackName
    const blob = await response.blob()
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = name
    link.click()
    URL.revokeObjectURL(link.href)
  },
}

export interface Paginated<T> {
  data: T[]
  meta: { current_page: number; last_page: number; total: number; per_page: number }
}
