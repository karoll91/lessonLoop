import { createContext, use, useCallback, useEffect, useId, useRef, useState, type ReactNode } from 'react'
import { ApiError } from '../api/client'
import { AlertIcon, CloseIcon, EmptyIcon } from './icons'

/* ---------- Modal ---------- */

export function Modal({ title, onClose, children, wide = false }: { title: string; onClose: () => void; children: ReactNode; wide?: boolean }) {
  const titleId = useId()
  const dialogRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    const onKey = (event: KeyboardEvent): void => {
      if (event.key === 'Escape') {
        onClose()
      }
    }
    window.addEventListener('keydown', onKey)
    dialogRef.current?.querySelector<HTMLElement>('input, select, textarea, button:not([aria-label="Yopish"])')?.focus()
    return () => window.removeEventListener('keydown', onKey)
  }, [onClose])

  return (
    <div className="overlay" onMouseDown={(event) => event.target === event.currentTarget && onClose()}>
      <div ref={dialogRef} className={`modal ${wide ? 'wide' : ''}`} role="dialog" aria-modal="true" aria-labelledby={titleId}>
        <div className="row">
          <h2 id={titleId} className="modal-title grow">
            {title}
          </h2>
          <button type="button" className="icon-btn" onClick={onClose} aria-label="Yopish">
            <CloseIcon />
          </button>
        </div>
        {children}
      </div>
    </div>
  )
}

/* ---------- Confirm dialog ---------- */

export function ConfirmDialog({
  title,
  text,
  confirmLabel,
  danger = false,
  busy = false,
  error,
  onConfirm,
  onClose,
}: {
  title: string
  text: ReactNode
  confirmLabel: string
  danger?: boolean
  busy?: boolean
  error?: string | null
  onConfirm: () => void
  onClose: () => void
}) {
  return (
    <Modal title={title} onClose={onClose}>
      <div className="muted">{text}</div>
      {error && <div className="banner error">{error}</div>}
      <div className="modal-actions">
        <button type="button" className="btn" onClick={onClose}>
          Bekor qilish
        </button>
        <button type="button" className={`btn ${danger ? 'danger' : 'primary'}`} onClick={onConfirm} disabled={busy}>
          {busy && <span className="spinner" />}
          {confirmLabel}
        </button>
      </div>
    </Modal>
  )
}

/* ---------- Form field ---------- */

export function Field({ label, error, help, children }: { label: string; error?: string; help?: string; children: (id: string, invalid: boolean) => ReactNode }) {
  const id = useId()
  return (
    <div className="field">
      <label className="label" htmlFor={id}>
        {label}
      </label>
      {children(id, error !== undefined)}
      {error ? (
        <span className="help error" role="alert">
          {error}
        </span>
      ) : (
        help && <span className="help">{help}</span>
      )}
    </div>
  )
}

/** Validation message of a field from a failed mutation. */
export function fieldError(error: unknown, field: string): string | undefined {
  return error instanceof ApiError ? error.fieldError(field) : undefined
}

/** A message to show for any failed request. */
export function errorMessage(error: unknown): string {
  if (error instanceof ApiError) {
    const first = Object.values(error.errors)[0]?.[0]
    return error.status === 422 && first ? first : error.message
  }
  return error instanceof Error ? error.message : "Noma'lum xato"
}

/* ---------- States ---------- */

export function EmptyState({ title, text, action }: { title: string; text?: string; action?: ReactNode }) {
  return (
    <div className="empty">
      <span className="empty-icon">
        <EmptyIcon />
      </span>
      <span className="empty-title">{title}</span>
      {text && <span className="muted">{text}</span>}
      {action}
    </div>
  )
}

export function ErrorState({ error, onRetry }: { error: unknown; onRetry?: () => void }) {
  return (
    <div className="empty" role="alert">
      <span className="empty-icon error">
        <AlertIcon />
      </span>
      <span className="empty-title">Ma'lumot yuklanmadi</span>
      <span className="muted">{errorMessage(error)}</span>
      {onRetry && (
        <button type="button" className="btn" onClick={onRetry}>
          Qayta urinish
        </button>
      )}
    </div>
  )
}

export function TableSkeleton({ rows = 5 }: { rows?: number }) {
  return (
    <div className="col" style={{ padding: 16, gap: 14 }} aria-label="Yuklanmoqda">
      {Array.from({ length: rows }, (_, index) => (
        <div key={index} className="skeleton" style={{ height: 18, width: `${90 - index * 8}%` }} />
      ))}
    </div>
  )
}

export function Pager({ page, lastPage, total, onPage }: { page: number; lastPage: number; total: number; onPage: (page: number) => void }) {
  if (lastPage <= 1) {
    return null
  }
  return (
    <div className="pager">
      <span className="small">
        {page}-sahifa / {lastPage} · jami {total}
      </span>
      <div className="row">
        <button type="button" className="btn sm" disabled={page <= 1} onClick={() => onPage(page - 1)}>
          Oldingi
        </button>
        <button type="button" className="btn sm" disabled={page >= lastPage} onClick={() => onPage(page + 1)}>
          Keyingi
        </button>
      </div>
    </div>
  )
}

/* ---------- Toasts ---------- */

interface Toast {
  id: number
  text: string
  tone: 'success' | 'error'
}

const ToastContext = createContext<(text: string, tone?: Toast['tone']) => void>(() => {})

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([])

  const show = useCallback((text: string, tone: Toast['tone'] = 'success') => {
    const id = Date.now() + Math.random()
    setToasts((current) => [...current, { id, text, tone }])
    window.setTimeout(() => setToasts((current) => current.filter((toast) => toast.id !== id)), 4000)
  }, [])

  return (
    <ToastContext value={show}>
      {children}
      <div className="toasts" role="status" aria-live="polite">
        {toasts.map((toast) => (
          <div key={toast.id} className={`toast ${toast.tone === 'error' ? 'error' : ''}`}>
            {toast.text}
          </div>
        ))}
      </div>
    </ToastContext>
  )
}

export function useToast(): (text: string, tone?: Toast['tone']) => void {
  return use(ToastContext)
}

/* ---------- Labels ---------- */

export const difficultyLabel: Record<string, string> = { easy: 'Oson', medium: "O'rta", hard: 'Qiyin' }

export const queueLabel: Record<string, { text: string; tone: string }> = {
  never_asked: { text: "So'ralmagan", tone: 'accent' },
  queue: { text: 'Navbatda', tone: '' },
  debtor: { text: 'Qarzdor', tone: 'warn' },
  resting: { text: 'Dam olmoqda', tone: 'info' },
}

export function ScoreText({ score }: { score: number | null | undefined }) {
  if (score === null || score === undefined) {
    return <span className="muted">—</span>
  }
  return <span className={score >= 60 ? 'score-pass' : 'score-fail'}>{score}</span>
}

// Browsers often ship without Uzbek locale data, so dates are formatted by hand.
const MONTHS = ['yanvar', 'fevral', 'mart', 'aprel', 'may', 'iyun', 'iyul', 'avgust', 'sentabr', 'oktabr', 'noyabr', 'dekabr']
const WEEKDAYS = ['yakshanba', 'dushanba', 'seshanba', 'chorshanba', 'payshanba', 'juma', 'shanba']
const pad = (value: number): string => String(value).padStart(2, '0')

/** 19.10.2026 */
export function formatDate(iso: string | null | undefined): string {
  if (!iso) {
    return '—'
  }
  const date = new Date(iso)
  return `${pad(date.getDate())}.${pad(date.getMonth() + 1)}.${date.getFullYear()}`
}

/** 14:05 */
export function formatTime(iso: string | null | undefined): string {
  if (!iso) {
    return '—'
  }
  const date = new Date(iso)
  return `${pad(date.getHours())}:${pad(date.getMinutes())}`
}

/** 19.10 14:05 */
export function formatDateTime(iso: string | null | undefined): string {
  if (!iso) {
    return '—'
  }
  const date = new Date(iso)
  return `${pad(date.getDate())}.${pad(date.getMonth() + 1)} ${formatTime(iso)}`
}

/** dushanba, 19-oktabr */
export function formatLongDate(date: Date): string {
  return `${WEEKDAYS[date.getDay()]}, ${date.getDate()}-${MONTHS[date.getMonth()]}`
}
