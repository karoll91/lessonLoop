import { isRouteErrorResponse, Link, useRouteError } from 'react-router'
import { AlertIcon, EmptyIcon } from '../ui/icons'

/** W12 — an address that matches no page. */
export function NotFoundPage() {
  return (
    <div className="card empty" style={{ minHeight: 360 }}>
      <span className="empty-icon">
        <EmptyIcon />
      </span>
      <span className="empty-title">Sahifa topilmadi</span>
      <span className="muted">Manzil noto'g'ri yoki sahifa o'chirilgan.</span>
      <Link className="btn primary" to="/">
        Bosh sahifaga
      </Link>
    </div>
  )
}

/** W12 — an unexpected crash while rendering a page. */
export function RouteError() {
  const error = useRouteError()
  const notFound = isRouteErrorResponse(error) && error.status === 404

  if (notFound) {
    return (
      <div style={{ padding: 24 }}>
        <NotFoundPage />
      </div>
    )
  }

  const message = isRouteErrorResponse(error) ? `${error.status} ${error.statusText}` : error instanceof Error ? error.message : String(error)

  return (
    <div style={{ minHeight: '100vh', display: 'flex', alignItems: 'center', justifyContent: 'center', padding: 24 }}>
      <div className="card empty" role="alert" style={{ maxWidth: 520 }}>
        <span className="empty-icon error">
          <AlertIcon />
        </span>
        <span className="empty-title">Kutilmagan xatolik</span>
        <span className="muted">Sahifani qayta yuklang. Xato takrorlansa, quyidagi matnni administratorga yuboring.</span>
        <code className="small" style={{ wordBreak: 'break-word' }}>
          {message}
        </code>
        <div className="row">
          <button type="button" className="btn primary" onClick={() => window.location.reload()}>
            Qayta yuklash
          </button>
          <a className="btn" href="/">
            Bosh sahifa
          </a>
        </div>
      </div>
    </div>
  )
}
