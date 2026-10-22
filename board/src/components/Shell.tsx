import type { ReactNode } from 'react'
import type { Theme } from '../hooks'
import { isOnline, type BoardState, type ConnectionStatus } from '../types'
import { ExpandIcon, MoonIcon, SunIcon } from './icons'

interface ShellProps {
  state: BoardState
  connection: ConnectionStatus
  remoteLost?: boolean
  theme: Theme
  onToggleTheme: () => void
  onFullscreen: () => void
  footer: ReactNode
  children: ReactNode
}

/** Top bar (lesson, progress, link status), content and footer of every screen. */
export function Shell({ state, connection, remoteLost = false, theme, onToggleTheme, onFullscreen, footer, children }: ShellProps) {
  const lesson = state.lesson

  return (
    <div className="screen">
      <header className="top">
        {lesson ? (
          <>
            <span className="top-title">
              {lesson.group} · {lesson.subject}
            </span>
            <span className="top-sub">{lesson.sequence}-dars</span>
            <span className="grow" />
            {isQuestionFlow(state) && <Progress state={state} />}
          </>
        ) : (
          <>
            <img className="logo" src={`${import.meta.env.BASE_URL}logo.png`} alt="LessonLoop" />
            <span className="top-title">LessonLoop · Doska rejimi</span>
            <span className="grow" />
          </>
        )}
        <LinkStatus state={state} connection={connection} remoteLost={remoteLost} />
      </header>

      {children}

      <footer className="foot">
        {footer}
        <span className="foot-tools">
          <button type="button" className="tool" onClick={onToggleTheme} aria-label={theme === 'dark' ? 'Yorug\' mavzu' : 'Qorong\'i mavzu'} title="T">
            {theme === 'dark' ? <SunIcon /> : <MoonIcon />}
          </button>
          <button type="button" className="tool" onClick={onFullscreen} aria-label="To'liq ekran" title="F">
            <ExpandIcon />
          </button>
        </span>
      </footer>
    </div>
  )
}

function isQuestionFlow(state: BoardState): boolean {
  return ['drawing', 'question', 'answer', 'score'].includes(state.screen)
}

/** One bar per question: done, the current one highlighted, the rest empty. */
export function Progress({ state }: { state: BoardState }) {
  const lesson = state.lesson
  if (!lesson) {
    return null
  }

  const current = lesson.asked_count
  const bars = Array.from({ length: lesson.question_count }, (_, index) => {
    if (index < current - 1) {
      return 'done'
    }
    return index === current - 1 ? 'now' : ''
  })

  return (
    <>
      <span className="dots" aria-hidden="true">
        {bars.map((kind, index) => (
          <span key={index} className={kind} />
        ))}
      </span>
      <span className="progress-text">
        {current} / {lesson.question_count}
      </span>
    </>
  )
}

function LinkStatus({ state, connection, remoteLost }: { state: BoardState; connection: ConnectionStatus; remoteLost: boolean }) {
  if (!isOnline(connection)) {
    return <span className="live off">Aloqa yo'q</span>
  }

  if (!state.is_paired) {
    return <span className="live wait">Pult kutilmoqda</span>
  }

  if (remoteLost) {
    return <span className="live off">Pult uzildi</span>
  }

  return <span className="live">Pult ulangan</span>
}
