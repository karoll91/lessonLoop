import { useState, type FormEvent } from 'react'
import { Navigate, useLocation, useNavigate } from 'react-router'
import { useAuth } from '../auth'
import { errorMessage, Field, fieldError } from '../ui/components'
import { LoopIcon } from '../ui/icons'

/** W1 — sign in with the login and password the admin gave. */
export function LoginPage() {
  const { user, login } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()
  const [form, setForm] = useState({ login: '', password: '' })
  const [error, setError] = useState<unknown>(null)
  const [busy, setBusy] = useState(false)

  if (user !== null) {
    return <Navigate to={user.must_change_password ? '/change-password' : '/'} replace />
  }

  const submit = async (event: FormEvent): Promise<void> => {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      const signedIn = await login(form.login.trim(), form.password)
      const from = (location.state as { from?: string } | null)?.from ?? '/'
      navigate(signedIn.must_change_password ? '/change-password' : from, { replace: true })
    } catch (exception) {
      setError(exception)
    } finally {
      setBusy(false)
    }
  }

  const loginError = fieldError(error, 'login')

  return (
    <div style={{ minHeight: '100vh', display: 'flex', flexWrap: 'wrap' }}>
      <section
        style={{ flex: '1 1 420px', background: 'var(--accent)', color: 'var(--on-accent)', padding: 56, display: 'flex', flexDirection: 'column', justifyContent: 'space-between', gap: 32 }}
      >
        <div className="row" style={{ gap: 12, fontSize: 20, fontWeight: 800 }}>
          <span style={{ width: 40, height: 40, borderRadius: 12, background: 'var(--on-accent)', color: 'var(--accent)', display: 'flex', alignItems: 'center', justifyContent: 'center' }}>
            <LoopIcon />
          </span>
          LessonLoop
        </div>
        <div className="col" style={{ gap: 16 }}>
          <div style={{ fontSize: 40, fontWeight: 800, lineHeight: 1.15, letterSpacing: '-0.02em' }}>Har dars — o'tilganni takrorlash.</div>
          <div style={{ fontSize: 16, opacity: 0.85 }}>Guruhlar, savollar banki, adolatli random va 100 ballik baholar bir joyda.</div>
        </div>
        <div className="row wrap" style={{ gap: 24, fontSize: 13, opacity: 0.8 }}>
          <span>4 darsda 1 marta</span>
          <span>60 — o'tish chegarasi</span>
          <span>Doska + telefon pulti</span>
        </div>
      </section>
      <section style={{ flex: '1 1 420px', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24 }}>
        <form className="col" style={{ width: '100%', maxWidth: 400, gap: 20 }} onSubmit={(event) => void submit(event)} noValidate>
          <div className="col" style={{ gap: 6 }}>
            <h1 className="page-title">Admin panelga kirish</h1>
            <span className="muted">Login va parolingizni kiriting</span>
          </div>
          {error !== null && !loginError && <div className="banner error">{errorMessage(error)}</div>}
          <Field label="Login" error={loginError}>
            {(id, invalid) => (
              <input
                id={id}
                className={`input ${invalid ? 'invalid' : ''}`}
                autoComplete="username"
                value={form.login}
                onChange={(event) => setForm({ ...form, login: event.target.value })}
                required
              />
            )}
          </Field>
          <Field label="Parol" error={fieldError(error, 'password')}>
            {(id, invalid) => (
              <input
                id={id}
                type="password"
                className={`input ${invalid ? 'invalid' : ''}`}
                autoComplete="current-password"
                value={form.password}
                onChange={(event) => setForm({ ...form, password: event.target.value })}
                required
              />
            )}
          </Field>
          <button type="submit" className="btn primary" disabled={busy || form.login === '' || form.password === ''}>
            {busy && <span className="spinner" />}
            Kirish
          </button>
          <span className="small">Parolni unutgan bo'lsangiz, admin yangi vaqtinchalik parol beradi.</span>
        </form>
      </section>
    </div>
  )
}
