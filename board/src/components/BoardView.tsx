import { toggleFullscreen, type Theme } from '../hooks'
import { isOnline, type BoardState, type ConnectionStatus } from '../types'
import { MicIcon, OfflineIcon } from './icons'
import {
  AnswerScreen,
  DrawingScreen,
  LobbyScreen,
  PairingScreen,
  PauseScreen,
  QuestionScreen,
  ScoreScreen,
  SummaryScreen,
} from './screens'
import { Shell } from './Shell'

interface BoardViewProps {
  state: BoardState
  connection: ConnectionStatus
  /** False when the paired phone stopped sending heartbeats. */
  controllerOnline?: boolean
  theme: Theme
  onToggleTheme: () => void
}

/** Picks the screen for the current board state (D1–D10). */
export function BoardView({ state, connection, controllerOnline = true, theme, onToggleTheme }: BoardViewProps) {
  if (state.screen === 'blackout') {
    return (
      <div className="blackout" data-testid="blackout">
        LessonLoop
      </div>
    )
  }

  const remoteLost = isOnline(connection) && state.is_paired && !controllerOnline && state.screen !== 'pairing'

  const answerKey = `${state.lesson?.id ?? 0}:${state.lesson?.asked_count ?? 0}:${state.current?.student?.full_name ?? ''}`

  return (
    <Shell
      state={state}
      connection={connection}
      remoteLost={remoteLost}
      theme={theme}
      onToggleTheme={onToggleTheme}
      onFullscreen={toggleFullscreen}
      footer={<Footer state={state} />}
    >
      {renderScreen(state, answerKey)}
      {!isOnline(connection) && <ConnectionLost />}
      {remoteLost && <RemoteLost />}
    </Shell>
  )
}

function renderScreen(state: BoardState, answerKey: string) {
  switch (state.screen) {
    case 'pairing':
      return <PairingScreen state={state} />
    case 'lobby':
      return <LobbyScreen lesson={state.lesson} />
    case 'drawing':
      return <DrawingScreen current={state.current} drawKey={answerKey} />
    case 'question':
      return <QuestionScreen key={answerKey} state={state} />
    case 'answer':
      return <AnswerScreen state={state} />
    case 'score':
      return <ScoreScreen state={state} />
    case 'summary':
      return <SummaryScreen summary={state.summary} />
    case 'pause':
      return <PauseScreen key={`pause:${answerKey}`} />
    default:
      return null
  }
}

function Footer({ state }: { state: BoardState }) {
  switch (state.screen) {
    case 'pairing':
      return <span>Bu ekranda faqat talabalar uchun ma'lumot ko'rinadi. To'g'ri javob va baholash — o'qituvchi telefonida.</span>
    case 'lobby':
      return state.lesson ? (
        <span>60+ ball — keyingi darslarda dam olasiz · 60 dan past — keyingi darsda yana navbat</span>
      ) : (
        <span>{state.name ?? 'LessonLoop'}</span>
      )
    case 'drawing':
      return <span>Tayyorlaning, savol chiqadi…</span>
    case 'question':
      return (
        <>
          <MicIcon />
          <span>Javobingizni ovoz chiqarib ayting</span>
        </>
      )
    case 'answer':
      return <span>O'qituvchi baholamoqda…</span>
    case 'score':
      return <span>Keyingi savol tayyorlanmoqda</span>
    case 'summary':
      return <span>Dars yakunlandi</span>
    case 'pause':
      return <span>Savol va javoblar vaqtincha yashirilgan</span>
    default:
      return null
  }
}

/** D9 — the board lost its connection; the last screen stays visible underneath. */
function ConnectionLost() {
  return (
    <div className="overlay-card" role="alert">
      <span className="overlay-icon">
        <OfflineIcon />
      </span>
      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
        <span className="overlay-title">Server bilan aloqa yo'q</span>
        <span className="overlay-text">Qayta ulanmoqda… Kompyuter tarmoqqa ulanganini tekshiring</span>
      </div>
    </div>
  )
}

/** D9 — the teacher's phone stopped sending heartbeats; the last screen stays visible. */
function RemoteLost() {
  return (
    <div className="overlay-card" role="alert" data-testid="remote-lost">
      <span className="overlay-icon">
        <OfflineIcon />
      </span>
      <div style={{ display: 'flex', flexDirection: 'column', gap: '0.25rem' }}>
        <span className="overlay-title">Pult uzildi</span>
        <span className="overlay-text">O'qituvchi telefoni bilan aloqa yo'q. Telefon qayta ulanganda dars shu joydan davom etadi</span>
      </div>
    </div>
  )
}
