import { createContext, use, useCallback, useEffect, useMemo, useState, type ReactNode } from 'react'
import { api, ApiError, onUnauthorized, tokenStore } from './api/client'
import type { User } from './api/types'

interface AuthContextValue {
  user: User | null
  loading: boolean
  /** /me failed for a reason other than an expired token (server down). */
  bootError: unknown
  retry: () => void
  login: (login: string, password: string) => Promise<User>
  logout: () => Promise<void>
  setUser: (user: User) => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

/** Keeps the Sanctum token and the signed-in user; a 401 anywhere signs out. */
export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [loading, setLoading] = useState(() => tokenStore.get() !== null)
  const [bootError, setBootError] = useState<unknown>(null)
  const [attempt, setAttempt] = useState(0)

  useEffect(() => {
    if (tokenStore.get() === null) {
      return
    }
    api
      .get<{ user: User }>('/me')
      .then((response) => {
        setUser(response.user)
        setBootError(null)
      })
      .catch((error: unknown) => {
        // Only an expired token signs out; a server outage keeps it for a retry.
        if (error instanceof ApiError && error.status === 401) {
          tokenStore.set(null)
        } else {
          setBootError(error)
        }
      })
      .finally(() => setLoading(false))
  }, [attempt])

  const retry = useCallback(() => {
    setLoading(true)
    setAttempt((current) => current + 1)
  }, [])

  useEffect(
    () =>
      onUnauthorized(() => {
        tokenStore.set(null)
        setUser(null)
      }),
    [],
  )

  const login = useCallback(async (login: string, password: string) => {
    const response = await api.post<{ token: string; user: User }>('/login', { login, password, device_name: 'web-admin' })
    tokenStore.set(response.token)
    setUser(response.user)
    return response.user
  }, [])

  const logout = useCallback(async () => {
    try {
      await api.post('/logout')
    } finally {
      tokenStore.set(null)
      setUser(null)
    }
  }, [])

  const value = useMemo(() => ({ user, loading, bootError, retry, login, logout, setUser }), [user, loading, bootError, retry, login, logout])

  return <AuthContext value={value}>{children}</AuthContext>
}

export function useAuth(): AuthContextValue {
  const value = use(AuthContext)
  if (value === null) {
    throw new Error('useAuth outside AuthProvider')
  }
  return value
}
