import { useEffect, useState } from 'react'
import { Navigate, NavLink, Outlet, useLocation } from 'react-router'
import { useAuth } from '../auth'
import { ErrorState } from '../ui/components'
import { LockIcon, LogoutIcon, MoonIcon, SunIcon } from '../ui/icons'
import { Logo } from '../ui/Logo'
import { useTheme, type Theme } from './theme'

const BOARD_URL =
  (import.meta.env.VITE_BOARD_URL as string | undefined) ||
  (import.meta.env.DEV ? `http://${window.location.hostname}:5173` : `${window.location.origin}/board/`)

const links: { to: string; label: string; adminOnly?: boolean }[] = [
  { to: '/', label: 'Bosh sahifa' },
  { to: '/groups', label: 'Guruhlar' },
  { to: '/subjects', label: 'Fanlar va mavzular' },
  { to: '/students', label: 'Talabalar' },
  { to: '/questions', label: 'Savollar banki' },
  { to: '/lessons', label: 'Darslar' },
  { to: '/journal', label: 'Jurnal' },
  { to: '/reports', label: 'Hisobotlar' },
  { to: '/users', label: 'Foydalanuvchilar', adminOnly: true },
  { to: '/settings', label: 'Sozlamalar' },
]

/** Sidebar + page, for signed-in users who changed their temporary password. */
export function AppLayout() {
  const { user, loading, bootError, retry, logout } = useAuth()
  const location = useLocation()
  const [theme, toggleTheme] = useTheme()
  const online = useOnline()

  if (loading) {
    return (
      <div className="empty" style={{ minHeight: '100vh' }}>
        <span className="spinner" aria-label="Yuklanmoqda" />
      </div>
    )
  }

  if (user === null && bootError !== null) {
    return (
      <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24 }}>
        <div className="card">
          <ErrorState error={bootError} onRetry={retry} />
        </div>
      </div>
    )
  }

  if (user === null) {
    return <Navigate to="/login" replace state={{ from: location.pathname }} />
  }

  if (user.must_change_password) {
    return <Navigate to="/change-password" replace />
  }

  return (
    <div className="app">
      <nav className="sidebar" aria-label="Asosiy menyu">
        <div className="brand">
          <Logo className="brand-mark" />
          LessonLoop
        </div>
        {links
          .filter((link) => !link.adminOnly || user.role === 'admin')
          .map((link) => (
            <NavLink key={link.to} to={link.to} end={link.to === '/'} className="nav-link">
              {link.label}
            </NavLink>
          ))}
        <a className="nav-link" href={BOARD_URL} target="_blank" rel="noreferrer">
          Doska rejimi ↗
        </a>
        <div className="sidebar-foot">
          <div className="col grow" style={{ gap: 0 }}>
            <span style={{ fontSize: 13, fontWeight: 700 }}>{user.name}</span>
            <span className="small">{user.role === 'admin' ? 'Admin' : "O'qituvchi"}</span>
          </div>
          <button type="button" className="icon-btn" onClick={toggleTheme} aria-label={theme === 'dark' ? "Yorug' mavzu" : "Qorong'i mavzu"}>
            {theme === 'dark' ? <SunIcon /> : <MoonIcon />}
          </button>
          <button type="button" className="icon-btn" onClick={() => void logout()} aria-label="Chiqish">
            <LogoutIcon />
          </button>
        </div>
      </nav>
      <main className="content">
        {!online && <div className="banner error">Internet aloqasi uzildi. O'zgarishlar saqlanmaydi.</div>}
        <Outlet context={{ theme, toggleTheme } satisfies LayoutContext} />
      </main>
    </div>
  )
}

/** Shown on admin-only pages to teachers (W10 403 state). */
export function AdminOnly({ children }: { children: React.ReactNode }) {
  const { user } = useAuth()

  if (user?.role !== 'admin') {
    return (
      <div className="card empty">
        <span className="empty-icon error">
          <LockIcon />
        </span>
        <span className="empty-title">Bu bo'lim faqat admin uchun</span>
        <span className="muted">Kerak bo'lsa admin bilan bog'laning.</span>
      </div>
    )
  }

  return <>{children}</>
}

function useOnline(): boolean {
  const [online, setOnline] = useState(() => navigator.onLine)

  useEffect(() => {
    const update = (): void => setOnline(navigator.onLine)
    window.addEventListener('online', update)
    window.addEventListener('offline', update)
    return () => {
      window.removeEventListener('online', update)
      window.removeEventListener('offline', update)
    }
  }, [])

  return online
}

export interface LayoutContext {
  theme: Theme
  toggleTheme: () => void
}
