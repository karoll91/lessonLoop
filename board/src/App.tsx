import { useEffect, useState } from 'react'
import { BoardView } from './components/BoardView'
import { KeyIcon } from './components/icons'
import { toggleFullscreen, useTheme } from './hooks'
import { TeacherLogin, TeacherPanel } from './teacher/TeacherPanel'
import { useTeacher } from './teacher/useTeacher'
import { useBoard } from './useBoard'

export default function App() {
  const { state, error, connection, controllerOnline, token, retry } = useBoard()
  const { teacher, api, signIn, signOut } = useTeacher(token, state?.board_id ?? null)
  const [loginOpen, setLoginOpen] = useState(false)
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

  const teacherTool =
    teacher === null ? (
      <button type="button" className="tool" onClick={() => setLoginOpen(true)} aria-label="O'qituvchi rejimi" title="O'qituvchi rejimi">
        <KeyIcon />
      </button>
    ) : null

  return (
    <>
      <BoardView state={state} connection={connection} controllerOnline={controllerOnline} theme={theme} onToggleTheme={toggleTheme} tool={teacherTool} />
      {teacher !== null && api !== null && <TeacherPanel state={state} api={api} name={teacher.name} onSignOut={() => void signOut()} />}
      {loginOpen && teacher === null && <TeacherLogin onSignIn={signIn} onClose={() => setLoginOpen(false)} />}
    </>
  )
}
