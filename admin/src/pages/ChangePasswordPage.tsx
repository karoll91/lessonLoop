import { useState, type FormEvent } from 'react'
import { Navigate, useNavigate } from 'react-router'
import { api } from '../api/client'
import type { User } from '../api/types'
import { useAuth } from '../auth'
import { errorMessage, Field, fieldError } from '../ui/components'

/** Required after signing in with a temporary password; also reachable from settings. */
export function ChangePasswordPage() {
  const { user, setUser, logout } = useAuth()
  const navigate = useNavigate()
  const [form, setForm] = useState({ current_password: '', password: '', password_confirmation: '' })
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)

  if (user === null) {
    return <Navigate to="/login" replace />
  }

  const submit = async (event: FormEvent): Promise<void> => {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const response = await api.post<{ user: User }>('/me/password', form)
      setUser({ ...user, ...response.user })
      navigate('/', { replace: true })
    } catch (exception) {
      setError(exception)
    } finally {
      setBusy(false)
    }
  }

  return (
    <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24 }}>
      <form className="card pad col" style={{ width: '100%', maxWidth: 440, gap: 16 }} onSubmit={(event) => void submit(event)}>
        <h1 className="page-title" style={{ fontSize: 22 }}>
          Yangi parol o'rnating
        </h1>
        {user.must_change_password && <div className="banner info">Siz vaqtinchalik parol bilan kirdingiz. Davom etish uchun o'z parolingizni o'rnating.</div>}
        {error !== null && Object.keys((error as { errors?: object }).errors ?? {}).length === 0 && <div className="banner error">{errorMessage(error)}</div>}
        <Field label="Joriy (vaqtinchalik) parol" error={fieldError(error, 'current_password')}>
          {(id, invalid) => (
            <input id={id} type="password" autoComplete="current-password" className={`input ${invalid ? 'invalid' : ''}`} value={form.current_password} onChange={(event) => setForm({ ...form, current_password: event.target.value })} />
          )}
        </Field>
        <Field label="Yangi parol" help="Kamida 8 belgi" error={fieldError(error, 'password')}>
          {(id, invalid) => (
            <input id={id} type="password" autoComplete="new-password" className={`input ${invalid ? 'invalid' : ''}`} value={form.password} onChange={(event) => setForm({ ...form, password: event.target.value })} />
          )}
        </Field>
        <Field label="Yangi parolni takrorlang">
          {(id) => (
            <input id={id} type="password" autoComplete="new-password" className="input" value={form.password_confirmation} onChange={(event) => setForm({ ...form, password_confirmation: event.target.value })} />
          )}
        </Field>
        <div className="row" style={{ justifyContent: 'space-between' }}>
          {user.must_change_password ? (
            <button type="button" className="btn ghost" onClick={() => void logout()}>
              Chiqish
            </button>
          ) : (
            <button type="button" className="btn ghost" onClick={() => navigate(-1)}>
              Orqaga
            </button>
          )}
          <button type="submit" className="btn primary" disabled={busy}>
            {busy && <span className="spinner" />}
            Saqlash
          </button>
        </div>
      </form>
    </div>
  )
}
