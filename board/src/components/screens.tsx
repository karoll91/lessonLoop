import { config } from '../config'
import { clock, difficultyLabel, initials } from '../format'
import { useMountedAt, useNow, useRevealAfter } from '../hooks'
import type { BoardCurrent, BoardLesson, BoardQuestion, BoardState, BoardSummary } from '../types'
import { CheckIcon, PauseIcon } from './icons'
import { QrCode } from './QrCode'

/** D1 — waiting for the teacher's phone: QR code and six-digit code. */
export function PairingScreen({ state }: { state: BoardState }) {
  const now = useNow()
  const code = state.pair_code ?? '······'
  const secondsLeft = state.pair_code_expires_at ? (Date.parse(state.pair_code_expires_at) - now) / 1000 : null

  return (
    <main className="main">
      <div className="pair">
        <QrCode value={`lessonloop://pair?code=${code}`} label={`Ulanish kodi ${code}`} />
        <div className="steps" style={{ gap: '1.5rem' }}>
          <div className="pair-title">Telefoningizdan ulang</div>
          <div className="steps">
            <div className="row">
              <span className="step-no">1</span>Telefonda LessonLoop'ni oching
            </div>
            <div className="row">
              <span className="step-no">2</span>«Doskaga ulash» → QR'ni skanerlang
            </div>
            <div className="row">
              <span className="step-no">3</span>yoki kodni kiriting:
            </div>
          </div>
          <div className="code" data-testid="pair-code" aria-label={`Kod ${code}`}>
            {code.slice(0, 3).split('').map((digit, index) => (
              <span key={`a${index}`}>{digit}</span>
            ))}
            <span className="gap" aria-hidden="true" />
            {code.slice(3).split('').map((digit, index) => (
              <span key={`b${index}`}>{digit}</span>
            ))}
          </div>
          {secondsLeft !== null && <span className="muted">Kod {clock(secondsLeft)} dan keyin yangilanadi</span>}
        </div>
      </div>
    </main>
  )
}

/** D2 — lesson chosen, waiting for the first draw (or for the teacher to pick a lesson). */
export function LobbyScreen({ lesson }: { lesson?: BoardLesson }) {
  if (!lesson) {
    return (
      <main className="main center">
        <span className="chip pass">Pult ulandi</span>
        <div className="big-title">Dars tanlanishini kuting</div>
        <span className="hint">O'qituvchi darsni telefonda yoki shu doskada tanlaydi</span>
      </main>
    )
  }

  return (
    <main className="main center">
      <span className="chip accent">Takrorlash darsi</span>
      <div className="big-title">{lesson.topics.join(' · ') || lesson.subject}</div>
      <div className="stats">
        <Stat value={lesson.question_count} label="savol" />
        <Stat value={lesson.present_count} label="talaba keldi" />
      </div>
      <span className="hint">Tayyorlaning — savollar tasodifiy tartibda beriladi</span>
    </main>
  )
}

function Stat({ value, label }: { value: number; label: string }) {
  return (
    <div className="stat">
      <b>{value}</b>
      <span>{label}</span>
    </div>
  )
}

/** D3 — the drum spins for a moment, then lands on the drawn student. */
export function DrawingScreen({ current, drawKey }: { current?: BoardCurrent; drawKey: string }) {
  const revealed = useRevealAfter(drawKey, config.drawAnimationMs)
  const name = current?.student?.full_name ?? null

  return (
    <main className={`main center ${revealed ? 'revealed' : 'spinning'}`}>
      <div className="drum" aria-live="polite">
        <div className="drum-row faint">
          <span className="drum-blur" />
        </div>
        <div className="drum-row">
          <span className="drum-blur" style={{ width: '24rem' }} />
        </div>
        <div className="drum-pick">
          {revealed ? (
            <>
              {name !== null && <span className="avatar">{initials(name)}</span>}
              <span className="drum-name">{name ?? 'Talaba tanlandi'}</span>
            </>
          ) : (
            <div className="drum-strip" aria-label="Tanlanmoqda">
              <span className="drum-blur" style={{ width: '30rem', height: '2rem' }} />
              <span className="drum-blur" style={{ width: '22rem', height: '2rem' }} />
            </div>
          )}
        </div>
        <div className="drum-row">
          <span className="drum-blur" style={{ width: '20rem' }} />
        </div>
        <div className="drum-row faint">
          <span className="drum-blur" />
        </div>
      </div>
    </main>
  )
}

function Who({ current, label, small = false }: { current?: BoardCurrent; label: string; small?: boolean }) {
  const name = current?.student?.full_name

  if (!name) {
    return null
  }

  return (
    <div className="who">
      <span className="avatar" style={small ? { width: '3rem', height: '3rem', fontSize: '1rem' } : undefined}>
        {initials(name)}
      </span>
      <div style={{ display: 'flex', flexDirection: small ? 'row' : 'column', gap: small ? '0.75rem' : '0.125rem', alignItems: small ? 'baseline' : 'flex-start' }}>
        {!small && <span className="who-label">{label}</span>}
        <span className="who-name" style={small ? { fontSize: '1.5rem' } : undefined}>
          {name}
        </span>
        {small && <span className="who-label">{label}</span>}
      </div>
    </div>
  )
}

function Timer({ seconds, startedAt }: { seconds: number; startedAt: number }) {
  const now = useNow()
  const left = Math.max(0, seconds - (now - startedAt) / 1000)
  const radius = 46
  const circumference = 2 * Math.PI * radius

  return (
    <div className={`timer ${left <= 10 ? 'low' : ''}`} role="timer" aria-label={`Qolgan vaqt ${clock(left)}`}>
      <svg viewBox="0 0 100 100" aria-hidden="true">
        <circle cx="50" cy="50" r={radius} fill="none" stroke="var(--track)" strokeWidth="8" />
        <circle
          cx="50"
          cy="50"
          r={radius}
          fill="none"
          stroke={left <= 10 ? 'var(--warn)' : 'var(--accent)'}
          strokeWidth="8"
          strokeLinecap="round"
          strokeDasharray={circumference}
          strokeDashoffset={circumference * (1 - left / seconds)}
        />
      </svg>
      {clock(left)}
    </div>
  )
}

function imageUrl(path: string): string {
  return path.startsWith('http') ? path : `${config.apiUrl.replace(/\/api\/?$/, '')}/storage/${path}`
}

function QuestionMeta({ question }: { question: BoardQuestion }) {
  return (
    <div className="row wrap" style={{ gap: '0.625rem' }}>
      <span className="chip">{question.topic}</span>
      <span className="chip">{difficultyLabel[question.difficulty] ?? question.difficulty}</span>
      {question.type === 'test' && <span className="chip accent">Test</span>}
    </div>
  )
}

/** D4 / D7 — the question is shown; the correct answer is not. */
export function QuestionScreen({ state }: { state: BoardState }) {
  const current = state.current
  const question = current?.question
  const startedAt = useMountedAt()
  const timerSeconds = state.options.timer_seconds

  if (!question) {
    return null
  }

  return (
    <main className="main">
      <div className="row" style={{ justifyContent: 'space-between' }}>
        <Who current={current} label="Javob beradi" />
        {timerSeconds ? <Timer seconds={timerSeconds} startedAt={startedAt} /> : null}
      </div>
      <QuestionMeta question={question} />
      <div className="question" data-testid="question-text">
        {question.text}
      </div>
      {question.image_path && <img className="question-image" src={imageUrl(question.image_path)} alt="" />}
      {question.type === 'test' && <Options question={question} />}
    </main>
  )
}

function Options({ question, correctLabel = null, chosen = null }: { question: BoardQuestion; correctLabel?: string | null; chosen?: string | null }) {
  return (
    <div className="options">
      {question.options.map((option) => {
        const isCorrect = correctLabel === option.label
        const isWrongChoice = chosen === option.label && !isCorrect
        return (
          <div key={option.label} className={`option ${isCorrect ? 'ok' : ''} ${isWrongChoice ? 'no' : ''}`}>
            <b>{option.label}</b>
            <span>{option.text}</span>
            {isCorrect && (
              <span className="option-mark" style={{ color: 'var(--pass)' }}>
                To'g'ri
              </span>
            )}
            {isWrongChoice && (
              <span className="option-mark" style={{ color: 'var(--warn)' }}>
                Talaba javobi
              </span>
            )}
          </div>
        )
      })}
    </div>
  )
}

/** D5 / D7 — the teacher revealed the correct answer. */
export function AnswerScreen({ state }: { state: BoardState }) {
  const current = state.current
  const question = current?.question
  const correct = current?.correct

  if (!question) {
    return null
  }

  return (
    <main className="main" style={{ gap: '1.375rem' }}>
      <Who current={current} label="javob berdi" small />
      <div className="question small">{question.text}</div>
      {question.type === 'test' ? (
        <Options question={question} correctLabel={correct?.option_label ?? null} chosen={correct?.chosen_option ?? null} />
      ) : correct?.answer_text ? (
        <div className="correct" data-testid="correct-answer">
          <div className="correct-head">
            <CheckIcon />
            To'g'ri javob
          </div>
          <div className="correct-text">{correct.answer_text}</div>
        </div>
      ) : (
        <span className="hint">To'g'ri javobni o'qituvchi aytib beradi</span>
      )}
    </main>
  )
}

/** D6 — the grade, and what it means for the student's turn in the queue. */
export function ScoreScreen({ state }: { state: BoardState }) {
  const current = state.current
  const result = current?.result
  const name = current?.student?.full_name

  return (
    <main className="main center" style={{ gap: '1.125rem' }}>
      {name && (
        <div className="row">
          <span className={`avatar ${result && !result.passed ? 'warn' : ''}`} style={{ width: '3.5rem', height: '3.5rem', fontSize: '1.25rem' }}>
            {initials(name)}
          </span>
          <span className="who-name" style={{ fontSize: '2.125rem' }}>
            {name}
          </span>
        </div>
      )}
      {result ? (
        <>
          <div className={`score score-in ${result.passed ? 'pass' : 'fail'}`} data-testid="score">
            <b>{result.score}</b>
            <span>/ 100</span>
          </div>
          <span className={`chip ${result.passed ? 'pass' : 'warn'}`} style={{ fontSize: '1.5rem', padding: '0.75rem 1.5rem' }}>
            {result.passed ? "O'tdi" : 'Qoniqarsiz'}
          </span>
          <span className="hint">
            {result.passed
              ? `Keyingi ${result.rest_lessons} darsda savol berilmaydi`
              : 'Mavzuni takrorlang — keyingi darsda yana savol beriladi'}
          </span>
        </>
      ) : (
        <div className="big-title">Baholandi</div>
      )}
    </main>
  )
}

/** D8 — end of the lesson. */
export function SummaryScreen({ summary }: { summary?: BoardSummary }) {
  if (!summary) {
    return null
  }

  return (
    <main className="main" style={{ paddingTop: '2.25rem', paddingBottom: '2.25rem' }}>
      <div className="summary">
        <div className="summary-average">
          <span className="hint">O'rtacha ball</span>
          <b data-testid="average">{summary.average ?? '—'}</b>
          <div className="row" style={{ gap: '0.625rem' }}>
            <span className="chip pass">{summary.passed_count} o'tdi</span>
            <span className="chip warn">{summary.retry_count} qayta</span>
          </div>
        </div>
        {summary.results.length > 0 && (
          <section className="summary-results">
            <h2>Natijalar</h2>
            <div className="results">
              {summary.results.map((result) => (
                <div key={result.full_name} className="result">
                  <span>{result.full_name}</span>
                  {result.score !== null && <b className={result.passed ? 'pass' : 'fail'}>{result.score}</b>}
                </div>
              ))}
            </div>
          </section>
        )}
      </div>
    </main>
  )
}

/** D10 — short break: lesson paused, questions hidden. */
export function PauseScreen() {
  const since = useMountedAt()
  const now = useNow()

  return (
    <main className="main center" style={{ gap: '1.25rem' }}>
      <div className="pause-icon">
        <PauseIcon />
      </div>
      <div className="big-title" style={{ fontSize: '3.5rem' }}>
        Qisqa tanaffus
      </div>
      <div className="clock">{clock((now - since) / 1000)}</div>
      <span className="hint">Dars tez orada davom etadi</span>
    </main>
  )
}
