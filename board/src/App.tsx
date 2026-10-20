import { useEffect } from 'react'
import { BoardView } from './components/BoardView'
import { toggleFullscreen, useTheme } from './hooks'
import { useBoard } from './useBoard'

export default function App() {
  const { state, error, connection, controllerOnline, retry } = useBoard()
  const [theme, toggleTheme] = useTheme()

  useEffect(() => {
    const onKey = (event: KeyboardEvent): void => {
      if (event.target instanceof HTMLElement && ['INPUT', 'TEXTAREA'].includes(event.target.tagName)) {
        return
      }
      if (event.key === 'f' || event.key === 'F') {
        toggleFullscreen()
      } else if (event.key === 't' || event.key === 'T') {
        toggleTheme()
      }
    }

    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [toggleTheme])

  if (state === null) {
    return (
      <div className="fullscreen-error">
        {error === null ? (
          <>
            <span className="spinner" aria-label="Yuklanmoqda" />
            <span className="muted">Doska ulanmoqda…</span>
          </>
        ) : (
          <>
            <div className="big-title" style={{ fontSize: '2.5rem' }}>
              Serverga ulanib bo'lmadi
            </div>
            <span className="hint">{error}. 5 soniyadan keyin qayta urinadi.</span>
            <button type="button" className="button" onClick={retry}>
              Hozir qayta urinish
            </button>
          </>
        )}
      </div>
    )
  }

  return <BoardView state={state} connection={connection} controllerOnline={controllerOnline} theme={theme} onToggleTheme={toggleTheme} />
}
