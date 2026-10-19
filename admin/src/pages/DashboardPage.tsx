import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link } from 'react-router'
import { api } from '../api/client'
import type { Dashboard, DashboardLesson } from '../api/types'
import { useAuth } from '../auth'
import { EmptyState, ErrorState, formatLongDate, formatTime, ScoreText, TableSkeleton } from '../ui/components'

const statusChip: Record<string, { text: string; tone: string }> = {
  active: { text: 'Faol', tone: 'accent' },
  paused: { text: 'Pauza', tone: 'info' },
  finished: { text: 'Yakunlangan', tone: 'pass' },
}

/** W2 — today's lessons, the lesson in progress, debtors and coverage. */
export function DashboardPage() {
  const { user } = useAuth()
  const query = useQuery({ queryKey: ['dashboard'], queryFn: () => api.get<{ data: Dashboard }>('/dashboard').then((response) => response.data) })

  const [today] = useState(() => formatLongDate(new Date()))

  return (
    <>
      <div className="page-head">
        <div className="col" style={{ gap: 4 }}>
          <h1 className="page-title">Bosh sahifa</h1>
          <span className="muted">
            {today} · {user?.name}
          </span>
        </div>
      </div>

      {query.isPending && (
        <div className="card">
          <TableSkeleton rows={6} />
        </div>
      )}
      {query.isError && (
        <div className="card">
          <ErrorState error={query.error} onRetry={() => void query.refetch()} />
        </div>
      )}
      {query.data && <DashboardContent data={query.data} />}
    </>
  )
}

function DashboardContent({ data }: { data: Dashboard }) {
  const isNew = data.counts.groups === 0

  if (isNew) {
    return (
      <div className="card pad col" style={{ gap: 12, maxWidth: 560 }}>
        <span className="card-title">LessonLoop'ni sozlang</span>
        <span className="muted">Dars o'tkazish uchun quyidagilar kerak:</span>
        <ol className="col" style={{ margin: 0, paddingLeft: 20, gap: 6 }}>
          <li>
            <Link to="/groups">Guruh yarating</Link> va unga fan hamda o'qituvchini biriktiring
          </li>
          <li>
            <Link to="/subjects">Fan va mavzular</Link> qo'shing
          </li>
          <li>
            <Link to="/students">Talabalarni import qiling</Link>
          </li>
          <li>
            <Link to="/questions">Savollar</Link> qo'shing
          </li>
          <li>Telefon ilovasidan birinchi darsni boshlang</li>
        </ol>
      </div>
    )
  }

  return (
    <>
      <div className="kpi">
        <Kpi label="Guruhlar" value={data.counts.groups} />
        <Kpi label="Talabalar" value={data.counts.students} />
        <Kpi label="Savollar banki" value={data.counts.questions} sub={data.counts.questions_without_answer > 0 ? `${data.counts.questions_without_answer} tasi javobsiz` : undefined} />
        <div className="card pad col" style={{ gap: 6, background: 'var(--accent-soft)', borderColor: 'var(--accent-soft)' }}>
          <span className="small" style={{ color: 'var(--accent)', fontWeight: 700 }}>
            Hali so'ralmagan
          </span>
          <span className="kpi-value">{data.counts.never_asked}</span>
        </div>
      </div>

      {data.running_lesson && (
        <div className="banner info">
          Davom etayotgan dars: {data.running_lesson.group} · {data.running_lesson.subject}, {data.running_lesson.sequence}-dars ({data.running_lesson.asked_count} / {data.running_lesson.question_count} savol).{' '}
          <Link to={`/lessons/${data.running_lesson.id}`}>Ochish</Link>
        </div>
      )}

      <div className="split">
        <section className="card main-col">
          <div className="pad" style={{ paddingBottom: 12 }}>
            <h2 className="card-title">Bugungi darslar</h2>
          </div>
          {data.today_lessons.length === 0 ? (
            <EmptyState title="Bugun dars bo'lmadi" text="Darslar telefon ilovasidan boshlanadi." />
          ) : (
            <div className="table-wrap">
              <LessonsTable lessons={data.today_lessons} />
            </div>
          )}
        </section>
        <section className="card pad side-col col" style={{ gap: 4 }}>
          <div className="row" style={{ justifyContent: 'space-between' }}>
            <h2 className="card-title">Qarzdorlar</h2>
            <span className="chip warn">{data.debtors.length}</span>
          </div>
          {data.debtors.length === 0 && <span className="muted">Qarzdor talaba yo'q.</span>}
          {data.debtors.map((debtor) => (
            <div key={`${debtor.course_id}-${debtor.student_id}`} className="row" style={{ padding: '8px 0', borderTop: '1px solid var(--line)' }}>
              <div className="col grow" style={{ gap: 0 }}>
                <span style={{ fontWeight: 700, fontSize: 14 }}>{debtor.full_name}</span>
                <span className="small">
                  {debtor.group} · {debtor.subject}
                </span>
              </div>
              <ScoreText score={debtor.last_score} />
            </div>
          ))}
        </section>
      </div>
    </>
  )
}

function Kpi({ label, value, sub }: { label: string; value: number; sub?: string }) {
  return (
    <div className="card pad col" style={{ gap: 6 }}>
      <span className="small">{label}</span>
      <span className="kpi-value">{value}</span>
      {sub && (
        <span className="small" style={{ color: 'var(--warn)' }}>
          {sub}
        </span>
      )}
    </div>
  )
}

function LessonsTable({ lessons }: { lessons: DashboardLesson[] }) {
  return (
    <table className="tb">
      <thead>
        <tr>
          <th>Vaqt</th>
          <th>Guruh</th>
          <th>Fan</th>
          <th>Savollar</th>
          <th>O'rtacha</th>
          <th>Holat</th>
        </tr>
      </thead>
      <tbody>
        {lessons.map((lesson) => (
          <tr key={lesson.id}>
            <td>{formatTime(lesson.started_at)}</td>
            <td style={{ fontWeight: 700 }}>
              <Link to={`/lessons/${lesson.id}`}>{lesson.group}</Link>
            </td>
            <td>{lesson.subject}</td>
            <td>
              {lesson.asked_count} / {lesson.question_count}
            </td>
            <td>
              <ScoreText score={lesson.average_score} />
            </td>
            <td>
              <span className={`chip ${statusChip[lesson.status]?.tone ?? ''}`}>{statusChip[lesson.status]?.text ?? lesson.status}</span>
            </td>
          </tr>
        ))}
      </tbody>
    </table>
  )
}
