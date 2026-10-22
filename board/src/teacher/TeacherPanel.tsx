import { useCallback, useEffect, useState, type FormEvent, type ReactNode } from 'react'
import { config } from '../config'
import type { BoardState } from '../types'
import { TeacherApiError, type Course, type Lesson, type Student, type TeacherApi, type Topic } from './api'

/*
 * Teacher mode on the board: everything the phone remote does, on the big
 * screen. Students see this panel too, so it never shows the correct answer
 * or a grade before the teacher puts it on the board.
 */

const QUICK_SCORES = [0, 40, 60, 75, 90, 100]
const FLOW_ENDS = ['no_students_left', 'question_limit_reached', 'no_questions_left']

const wait = (ms: number) => new Promise((resolve) => window.setTimeout(resolve, ms))

interface PanelProps {
  state: BoardState
  api: TeacherApi
  name: string
  onSignOut: () => void
}

export function TeacherPanel({ state, api, name, onSignOut }: PanelProps) {
  const [open, setOpen] = useState(true)
  const [busy, setBusy] = useState(false)
  const [message, setMessage] = useState<string | null>(null)
  const [canFinish, setCanFinish] = useState(false)
  const [setup, setSetup] = useState(false)

  const lessonOnBoard = state.lesson !== undefined && state.screen !== 'summary'
  const showSetup = setup || !lessonOnBoard

  /** Run one step; flow errors become a message (and «finish» when nothing is left). */
  const run = useCallback(async (action: () => Promise<void>) => {
    setBusy(true)
    setMessage(null)
    setCanFinish(false)
    try {
      await action()
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Xatolik')
      setCanFinish(error instanceof TeacherApiError && error.code !== null && FLOW_ENDS.includes(error.code))
    } finally {
      setBusy(false)
    }
  }, [])

  const finish = () =>
    run(async () => {
      if (window.confirm('Darsni yakunlaysizmi?')) {
        await api.command(state.board_id, 'finish')
      }
    })

  return (
    <section className={`teacher-panel${open ? '' : ' closed'}`} aria-label="O'qituvchi paneli">
      <header className="t-head">
        <span className="t-badge">O'qituvchi rejimi</span>
        <span className="t-name">{name}</span>
        <span className="grow" />
        {open && lessonOnBoard && !setup && (
          <button type="button" className="t-btn" disabled={busy} onClick={() => setSetup(true)}>
            Boshqa dars
          </button>
        )}
        <button type="button" className="t-btn" onClick={() => setOpen((value) => !value)} aria-expanded={open}>
          {open ? 'Yashirish ▾' : 'Panel ▴'}
        </button>
        <button type="button" className="t-btn" onClick={onSignOut}>
          Chiqish
        </button>
      </header>

      {open && (
        <div className="t-body">
          {showSetup ? (
            <LessonSetup api={api} boardId={state.board_id} busy={busy} run={run} onDone={() => setSetup(false)} onCancel={lessonOnBoard ? () => setSetup(false) : undefined} />
          ) : (
            <LessonControls state={state} api={api} busy={busy} run={run} onFinish={finish} />
          )}
          {message !== null && (
            <div className="t-message" role="alert">
              {message}
              {canFinish && (
                <button type="button" className="t-btn danger" onClick={finish}>
                  Darsni yakunlash
                </button>
              )}
            </div>
          )}
        </div>
      )}
    </section>
  )
}

type Run = (action: () => Promise<void>) => Promise<void>

/* ---------- running lesson ---------- */

function LessonControls({ state, api, busy, run, onFinish }: { state: BoardState; api: TeacherApi; busy: boolean; run: Run; onFinish: () => void }) {
  const board = state.board_id
  const lesson = state.lesson!
  const [answerId, setAnswerId] = useState<number | null>(null)
  const [score, setScore] = useState(60)

  // The pending answer (who is being asked) comes from the lesson itself.
  useEffect(() => {
    let cancelled = false
    api
      .lesson(lesson.id)
      .then((fresh: Lesson) => {
        if (!cancelled) {
          setAnswerId(fresh.answers?.find((answer) => answer.status === 'pending')?.id ?? null)
        }
      })
      .catch(() => undefined)
    return () => {
      cancelled = true
    }
  }, [api, lesson.id, lesson.asked_count, state.screen])

  const command = (name: string) => api.command(board, name)

  const draw = (name: 'draw' | 'redraw') =>
    run(async () => {
      const result = await command(name)
      setAnswerId(result.answer_id)
      await wait(config.drawAnimationMs)
      await command('show_question')
    })

  const grade = (body: { score?: number; chosen_option?: string }) =>
    run(async () => {
      if (answerId === null) {
        throw new Error('Javob beruvchi talaba topilmadi')
      }
      await api.grade(answerId, body)
      await command('show_score')
    })

  const skip = (reason: 'no_answer' | 'absent') =>
    run(async () => {
      if (answerId === null) {
        throw new Error('Javob beruvchi talaba topilmadi')
      }
      await api.skip(answerId, reason)
      await command('show_lobby')
    })

  const asking = ['drawing', 'question', 'answer'].includes(state.screen) && answerId !== null
  const suspended = state.screen === 'pause' || state.screen === 'blackout'
  const question = state.current?.question
  const options = state.options

  return (
    <div className="t-grid">
      <div className="t-row">
        <span className="t-label">
          {lesson.group} · {lesson.subject} · {lesson.sequence}-dars · savol {lesson.asked_count}/{lesson.question_count}
        </span>
      </div>

      {suspended ? (
        <div className="t-row">
          <Button primary disabled={busy} onClick={() => run(async () => void (await command('resume')))}>
            Davom ettirish
          </Button>
        </div>
      ) : asking ? (
        <>
          <div className="t-row">
            <Button disabled={busy} onClick={() => run(async () => void (await command(state.screen === 'answer' ? 'hide_answer' : 'reveal_answer')))}>
              {state.screen === 'answer' ? 'Javobni yashirish' : "To'g'ri javobni ko'rsatish"}
            </Button>
            <Button disabled={busy} onClick={() => draw('redraw')}>
              Boshqa talaba
            </Button>
            <Button disabled={busy} onClick={() => skip('no_answer')}>
              Javob bermadi
            </Button>
            <Button disabled={busy} onClick={() => skip('absent')}>
              Darsda yo'q
            </Button>
          </div>
          {question?.type === 'test' ? (
            <div className="t-row">
              <span className="t-label">Talaba aytgan variant:</span>
              {question.options.map((option) => (
                <Button key={option.label} disabled={busy} onClick={() => grade({ chosen_option: option.label })}>
                  {option.label}
                </Button>
              ))}
            </div>
          ) : (
            <div className="t-row">
              <span className="t-label">Baho:</span>
              {QUICK_SCORES.map((value) => (
                <Button key={value} active={score === value} disabled={busy} onClick={() => setScore(value)}>
                  {value}
                </Button>
              ))}
              <input className="t-range" type="range" min={0} max={100} value={score} onChange={(event) => setScore(Number(event.target.value))} aria-label="Ball" />
              <Button primary disabled={busy} onClick={() => grade({ score })}>
                {score} ball qo'yish
              </Button>
            </div>
          )}
        </>
      ) : (
        <div className="t-row">
          <Button primary disabled={busy} onClick={() => draw('draw')}>
            Random — keyingi talaba
          </Button>
          <Button danger disabled={busy} onClick={onFinish}>
            Darsni yakunlash
          </Button>
        </div>
      )}

      <div className="t-row">
        <span className="t-label">Ekran:</span>
        {!suspended && (
          <>
            <Button disabled={busy} onClick={() => run(async () => void (await command('pause')))}>
              Tanaffus
            </Button>
            <Button disabled={busy} onClick={() => run(async () => void (await command('blackout')))}>
              Qora ekran
            </Button>
          </>
        )}
        <Toggle label="Ism" on={options.show_student_name} disabled={busy} onChange={(value) => run(async () => void (await api.options(board, { show_student_name: value })))} />
        <Toggle label="Javob" on={options.show_answer} disabled={busy} onChange={(value) => run(async () => void (await api.options(board, { show_answer: value })))} />
        <Toggle label="Baho" on={options.show_score} disabled={busy} onChange={(value) => run(async () => void (await api.options(board, { show_score: value })))} />
        {asking && (
          <Button danger disabled={busy} onClick={onFinish}>
            Darsni yakunlash
          </Button>
        )}
      </div>
    </div>
  )
}

/* ---------- start or continue a lesson ---------- */

function LessonSetup({ api, boardId, busy, run, onDone, onCancel }: { api: TeacherApi; boardId: number; busy: boolean; run: Run; onDone: () => void; onCancel?: () => void }) {
  const [active, setActive] = useState<Lesson[]>([])
  const [courses, setCourses] = useState<Course[]>([])
  const [course, setCourse] = useState<Course | null>(null)
  const [topics, setTopics] = useState<Topic[]>([])
  const [chosen, setChosen] = useState<number[]>([])
  const [students, setStudents] = useState<Student[]>([])
  const [absent, setAbsent] = useState<number[]>([])
  const [count, setCount] = useState(8)

  useEffect(() => {
    void run(async () => {
      const [lessons, list, rules] = await Promise.all([api.activeLessons(), api.courses(), api.rules()])
      setActive(lessons)
      setCourses(list)
      setCount(rules.data.default_question_count ?? 8)
    })
  }, [api, run])

  const pickCourse = (picked: Course) =>
    run(async () => {
      setCourse(picked)
      setChosen([])
      setAbsent([])
      const [topicList, studentList] = await Promise.all([api.topics(picked.subject_id), api.students(picked.study_group_id, picked.id)])
      setTopics([...topicList].sort((a, b) => a.position - b.position))
      setStudents(studentList)
    })

  const attach = (lessonId: number) =>
    run(async () => {
      await api.attach(boardId, lessonId)
      onDone()
    })

  const start = (event: FormEvent) => {
    event.preventDefault()
    if (course === null) {
      return
    }
    void run(async () => {
      if (chosen.length === 0) {
        throw new Error('Kamida bitta mavzuni tanlang')
      }
      const lesson = await api.startLesson(course.id, { topic_ids: chosen, question_count: count, absent_student_ids: absent })
      await api.attach(boardId, lesson.id)
      onDone()
    })
  }

  const toggle = (list: number[], id: number) => (list.includes(id) ? list.filter((item) => item !== id) : [...list, id])

  return (
    <form className="t-grid" onSubmit={start}>
      {active.length > 0 && (
        <div className="t-row">
          <span className="t-label">Davom etayotgan dars:</span>
          {active.map((lesson) => (
            <Button key={lesson.id} disabled={busy} onClick={() => attach(lesson.id)}>
              {lesson.group} · {lesson.subject} · {lesson.sequence}-dars
            </Button>
          ))}
        </div>
      )}

      <div className="t-row">
        <span className="t-label">Yangi dars — kurs:</span>
        {courses.map((item) => (
          <Button key={item.id} active={course?.id === item.id} disabled={busy} onClick={() => pickCourse(item)}>
            {item.group?.name} · {item.subject?.name}
          </Button>
        ))}
        {courses.length === 0 && !busy && <span className="t-label">Sizga kurs biriktirilmagan</span>}
      </div>

      {course !== null && (
        <>
          <div className="t-row">
            <span className="t-label">Mavzular:</span>
            {topics.map((topic) => (
              <Button key={topic.id} active={chosen.includes(topic.id)} disabled={busy} onClick={() => setChosen((list) => toggle(list, topic.id))}>
                {topic.position}. {topic.title}
              </Button>
            ))}
          </div>
          <div className="t-row">
            <span className="t-label">Kelmaganlar (bosib belgilang):</span>
            {students.map((student) => (
              <Button key={student.id} active={absent.includes(student.id)} danger={absent.includes(student.id)} disabled={busy} onClick={() => setAbsent((list) => toggle(list, student.id))}>
                {student.full_name}
              </Button>
            ))}
          </div>
          <div className="t-row">
            <label className="t-label">
              Savollar soni:{' '}
              <input className="t-number" type="number" min={1} max={100} value={count} onChange={(event) => setCount(Math.max(1, Number(event.target.value) || 1))} />
            </label>
            <button type="submit" className="t-btn primary" disabled={busy}>
              Darsni boshlash
            </button>
          </div>
        </>
      )}

      {onCancel && (
        <div className="t-row">
          <Button disabled={busy} onClick={onCancel}>
            Joriy darsga qaytish
          </Button>
        </div>
      )}
    </form>
  )
}

/* ---------- sign-in ---------- */

export function TeacherLogin({ onSignIn, onClose }: { onSignIn: (login: string, password: string) => Promise<void>; onClose: () => void }) {
  const [loginName, setLoginName] = useState('')
  const [password, setPassword] = useState('')
  const [busy, setBusy] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const submit = async (event: FormEvent) => {
    event.preventDefault()
    setBusy(true)
    setError(null)
    try {
      await onSignIn(loginName, password)
      onClose()
    } catch (exception) {
      setError(exception instanceof Error ? exception.message : 'Xatolik')
    } finally {
      setBusy(false)
    }
  }

  return (
    <div className="t-modal" role="dialog" aria-modal="true" aria-label="O'qituvchi rejimi">
      <form className="t-login" onSubmit={submit}>
        <h2>O'qituvchi rejimi</h2>
        <p className="t-label">Doskani shu yerdan boshqarish uchun o'z login va parolingiz bilan kiring.</p>
        <input className="t-input" placeholder="Login" autoComplete="username" value={loginName} onChange={(event) => setLoginName(event.target.value)} autoFocus />
        <input className="t-input" placeholder="Parol" type="password" autoComplete="current-password" value={password} onChange={(event) => setPassword(event.target.value)} />
        {error !== null && (
          <div className="t-message" role="alert">
            {error}
          </div>
        )}
        <div className="t-row">
          <button type="submit" className="t-btn primary" disabled={busy || loginName === '' || password === ''}>
            {busy ? 'Tekshirilmoqda…' : 'Kirish'}
          </button>
          <button type="button" className="t-btn" onClick={onClose}>
            Bekor
          </button>
        </div>
      </form>
    </div>
  )
}

/* ---------- small parts ---------- */

function Button({ children, onClick, disabled, primary, danger, active }: { children: ReactNode; onClick: () => void; disabled?: boolean; primary?: boolean; danger?: boolean; active?: boolean }) {
  const classes = ['t-btn', primary ? 'primary' : '', danger ? 'danger' : '', active ? 'active' : ''].filter(Boolean).join(' ')
  return (
    <button type="button" className={classes} disabled={disabled} onClick={onClick} aria-pressed={active}>
      {children}
    </button>
  )
}

function Toggle({ label, on, disabled, onChange }: { label: string; on: boolean; disabled: boolean; onChange: (value: boolean) => void }) {
  return (
    <Button active={on} disabled={disabled} onClick={() => onChange(!on)}>
      {on ? '✓ ' : ''}
      {label}
    </Button>
  )
}
