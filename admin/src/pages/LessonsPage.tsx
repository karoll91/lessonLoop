import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { api, type Paginated } from '../api/client'
import { courseLabel, useCourseOptions } from '../api/queries'
import type { Lesson } from '../api/types'
import { EmptyState, ErrorState, formatDate, Pager, ScoreText, TableSkeleton } from '../ui/components'

export const lessonStatus: Record<Lesson['status'], { text: string; tone: string }> = {
  active: { text: 'Faol', tone: 'accent' },
  paused: { text: 'Pauza', tone: 'info' },
  finished: { text: 'Yakunlangan', tone: 'pass' },
}

/** W7 — history of lessons, filtered by course, status and date. */
export function LessonsPage() {
  const [params, setParams] = useSearchParams()
  const courses = useCourseOptions()
  const courseId = params.get('course') ? Number(params.get('course')) : null
  const [status, setStatus] = useState<Lesson['status'] | ''>('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [page, setPage] = useState(1)

  const list = useQuery({
    queryKey: ['lessons', { courseId, status, from, to, page }],
    queryFn: () => api.get<Paginated<Lesson>>('/lessons', { course_id: courseId, status, from, to, page }),
  })

  const lessons = list.data?.data ?? []
  const filtered = courseId !== null || status !== '' || from !== '' || to !== ''

  return (
    <>
      <div className="page-head">
        <div className="col" style={{ gap: 4 }}>
          <h1 className="page-title">Darslar</h1>
          <span className="muted">Darslar telefon ilovasidan o'tkaziladi; bu yerda tarix va baholarni tuzatish.</span>
        </div>
      </div>

      <div className="filters">
        <label className="visually-hidden" htmlFor="lesson-course">
          Kurs
        </label>
        <select
          id="lesson-course"
          className="select"
          style={{ width: 280 }}
          value={courseId ?? ''}
          onChange={(event) => {
            setParams(event.target.value === '' ? {} : { course: event.target.value })
            setPage(1)
          }}
        >
          <option value="">Barcha kurslar</option>
          {courses.data?.map((course) => (
            <option key={course.id} value={course.id}>
              {courseLabel(course)}
            </option>
          ))}
        </select>
        {(['', 'active', 'paused', 'finished'] as const).map((value) => (
          <button key={value} type="button" className="fchip" aria-pressed={status === value} onClick={() => (setStatus(value), setPage(1))}>
            {value === '' ? 'Hammasi' : lessonStatus[value].text}
          </button>
        ))}
        <label className="row small" style={{ gap: 6 }}>
          dan
          <input type="date" className="input" style={{ width: 150 }} value={from} onChange={(event) => (setFrom(event.target.value), setPage(1))} />
        </label>
        <label className="row small" style={{ gap: 6 }}>
          gacha
          <input type="date" className="input" style={{ width: 150 }} value={to} onChange={(event) => (setTo(event.target.value), setPage(1))} />
        </label>
      </div>

      <section className="card" style={{ overflow: 'hidden' }}>
        {list.isPending && <TableSkeleton />}
        {list.isError && <ErrorState error={list.error} onRetry={() => void list.refetch()} />}
        {list.data && lessons.length === 0 && <EmptyState title={filtered ? "Filtr bo'yicha dars yo'q" : "Hali dars o'tilmagan"} text={filtered ? undefined : 'Birinchi darsni telefon ilovasidan boshlang.'} />}
        {lessons.length > 0 && list.data && (
          <>
            <div className="table-wrap">
              <table className="tb">
                <thead>
                  <tr>
                    <th>Sana</th>
                    <th>Guruh</th>
                    <th>Fan</th>
                    <th>Dars</th>
                    <th>Savollar</th>
                    <th>Qayta topshirish</th>
                    <th>O'rtacha</th>
                    <th>Holat</th>
                  </tr>
                </thead>
                <tbody>
                  {lessons.map((lesson) => (
                    <tr key={lesson.id}>
                      <td>
                        <Link to={`/lessons/${lesson.id}`} style={{ fontWeight: 700 }}>
                          {formatDate(lesson.started_at)}
                        </Link>
                      </td>
                      <td>{lesson.group}</td>
                      <td>{lesson.subject}</td>
                      <td>{lesson.sequence}-dars</td>
                      <td>
                        {lesson.asked_count ?? 0} / {lesson.question_count}
                      </td>
                      <td>{lesson.retry_count ?? 0}</td>
                      <td>
                        <ScoreText score={lesson.average_score} />
                      </td>
                      <td>
                        <span className={`chip ${lessonStatus[lesson.status].tone}`}>{lessonStatus[lesson.status].text}</span>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pager page={list.data.meta.current_page} lastPage={list.data.meta.last_page} total={list.data.meta.total} onPage={setPage} />
          </>
        )}
      </section>
    </>
  )
}
