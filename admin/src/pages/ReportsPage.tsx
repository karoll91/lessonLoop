import { useQuery } from '@tanstack/react-query'
import { Link, useSearchParams } from 'react-router'
import { api } from '../api/client'
import { courseLabel, useCourseOptions } from '../api/queries'
import type { Report } from '../api/types'
import { EmptyState, ErrorState, formatDate, ScoreText, TableSkeleton } from '../ui/components'

const PASS = 60

/** W9 — course statistics: trend per lesson, distribution, coverage, hard spots. */
export function ReportsPage() {
  const [params, setParams] = useSearchParams()
  const courses = useCourseOptions()
  const courseId = params.get('course') ? Number(params.get('course')) : (courses.data?.[0]?.id ?? null)

  const query = useQuery({
    queryKey: ['report', courseId],
    queryFn: () => api.get<{ data: Report }>(`/courses/${courseId}/report`).then((response) => response.data),
    enabled: courseId !== null,
  })

  return (
    <>
      <div className="page-head">
        <div className="col" style={{ gap: 4 }}>
          <h1 className="page-title">Hisobotlar</h1>
          {query.data && (
            <span className="muted">
              {query.data.course.group} · {query.data.course.subject}
            </span>
          )}
        </div>
        <label className="visually-hidden" htmlFor="report-course">
          Kurs
        </label>
        <select id="report-course" className="select" style={{ width: 300 }} value={courseId ?? ''} onChange={(event) => setParams({ course: event.target.value })}>
          {courses.data?.map((course) => (
            <option key={course.id} value={course.id}>
              {courseLabel(course)}
            </option>
          ))}
        </select>
      </div>

      {courses.data?.length === 0 && (
        <div className="card">
          <EmptyState title="Kurs yo'q" text="Guruhga fan biriktirilgach hisobot paydo bo'ladi." />
        </div>
      )}
      {query.isPending && courseId !== null && (
        <div className="card">
          <TableSkeleton rows={6} />
        </div>
      )}
      {query.isError && (
        <div className="card">
          <ErrorState error={query.error} onRetry={() => void query.refetch()} />
        </div>
      )}
      {query.data && query.data.summary.answers === 0 && (
        <div className="card">
          <EmptyState title="Hisobot uchun baholar yo'q" text="Birinchi darsdan keyin statistika shu yerda chiqadi." />
        </div>
      )}
      {query.data && query.data.summary.answers > 0 && <ReportContent report={query.data} />}
    </>
  )
}

function ReportContent({ report }: { report: Report }) {
  const { summary, coverage } = report
  const maxBucket = Math.max(1, ...report.distribution.map((bucket) => bucket.count))
  const coveragePercent = coverage.students === 0 ? 0 : Math.round((coverage.asked * 100) / coverage.students)

  return (
    <>
      <div className="kpi">
        <div className="card pad col" style={{ gap: 6 }}>
          <span className="small">O'rtacha ball</span>
          <span className="kpi-value">
            <ScoreText score={summary.average} />
          </span>
          <span className="small">
            {summary.answers} ta javob · {summary.lessons} dars
          </span>
        </div>
        <div className="card pad col" style={{ gap: 6 }}>
          <span className="small">O'tish ulushi (≥{PASS})</span>
          <span className="kpi-value">{summary.pass_rate ?? '—'}%</span>
        </div>
        <div className="card pad col" style={{ gap: 6 }}>
          <span className="small">Qamrov</span>
          <span className="kpi-value">{coveragePercent}%</span>
          <span className="small">
            {coverage.asked} / {coverage.students} talaba so'ralgan
          </span>
        </div>
        <div className="card pad col" style={{ gap: 6 }}>
          <span className="small">Qarzdorlar</span>
          <span className="kpi-value" style={{ color: 'var(--warn)' }}>
            {summary.debtors}
          </span>
          <Link className="small" to={`/journal?course=${report.course.id}`}>
            Jurnalda ko'rish
          </Link>
        </div>
      </div>

      <section className="card pad col" style={{ gap: 12 }}>
        <h2 className="card-title">Darslar bo'yicha o'rtacha ball</h2>
        <TrendChart lessons={report.lessons} />
      </section>

      <div className="split">
        <section className="card pad main-col col" style={{ gap: 12 }}>
          <h2 className="card-title">Ballar taqsimoti</h2>
          <div className="dist" role="img" aria-label={report.distribution.map((bucket) => `${bucket.from}–${bucket.to}: ${bucket.count}`).join(', ')}>
            {report.distribution.map((bucket) => (
              <div key={bucket.from} className="dist-col">
                <span className="small" style={{ fontWeight: 700 }}>
                  {bucket.count}
                </span>
                <div className={`dist-bar ${bucket.from >= PASS ? 'pass' : 'fail'}`} style={{ height: `${(bucket.count / maxBucket) * 140}px` }} />
                <span className="small">
                  {bucket.from}–{bucket.to}
                </span>
              </div>
            ))}
          </div>
        </section>
        <section className="card pad side-col col" style={{ gap: 12 }}>
          <h2 className="card-title">Mavzular</h2>
          <div className="bars">
            {report.topics.map((topic) => (
              <div key={topic.id} className="col" style={{ gap: 4 }}>
                <div className="row" style={{ justifyContent: 'space-between', fontSize: 13 }}>
                  <span style={{ fontWeight: 700 }}>{topic.title}</span>
                  <ScoreText score={topic.average} />
                </div>
                <div className="bar-track">
                  <div className="bar-fill" style={{ width: `${topic.average ?? 0}%`, background: (topic.average ?? 0) >= PASS ? 'var(--pass)' : 'var(--warn)' }} />
                  <span className="bar-mark" style={{ left: `${PASS}%` }} aria-hidden="true" />
                </div>
              </div>
            ))}
          </div>
        </section>
      </div>

      <section className="card" style={{ overflow: 'hidden' }}>
        <div className="pad" style={{ paddingBottom: 12 }}>
          <h2 className="card-title">Eng qiyin savollar</h2>
        </div>
        <div className="table-wrap">
          <table className="tb">
            <thead>
              <tr>
                <th>Savol</th>
                <th>Mavzu</th>
                <th>So'ralgan</th>
                <th>O'rtacha</th>
              </tr>
            </thead>
            <tbody>
              {report.hardest_questions.map((question) => (
                <tr key={question.id}>
                  <td style={{ minWidth: 260, maxWidth: 480, fontWeight: 600 }}>{question.text}</td>
                  <td className="muted">{question.topic}</td>
                  <td>{question.times_asked}</td>
                  <td>
                    <ScoreText score={question.average} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </>
  )
}

/** Line of average scores per lesson with the pass line at 60. */
function TrendChart({ lessons }: { lessons: Report['lessons'] }) {
  const points = lessons.filter((lesson) => lesson.average !== null)
  if (points.length === 0) {
    return <span className="muted">Baholangan dars yo'q.</span>
  }

  const width = 720
  const height = 200
  const left = 32
  const bottom = 24
  const step = points.length > 1 ? (width - left - 16) / (points.length - 1) : 0
  const x = (index: number): number => left + (points.length > 1 ? index * step : (width - left) / 2)
  const y = (score: number): number => 8 + (height - bottom - 8) * (1 - score / 100)
  const path = points.map((lesson, index) => `${index === 0 ? 'M' : 'L'}${x(index)},${y(lesson.average ?? 0)}`).join(' ')

  return (
    <svg viewBox={`0 0 ${width} ${height}`} className="trend" role="img" aria-label={points.map((lesson) => `${lesson.sequence}-dars: ${lesson.average}`).join(', ')}>
      {[0, 50, 100].map((tick) => (
        <g key={tick}>
          <line x1={left} x2={width} y1={y(tick)} y2={y(tick)} stroke="var(--line)" />
          <text x={left - 6} y={y(tick) + 4} textAnchor="end" fontSize="11" fill="var(--ink3)">
            {tick}
          </text>
        </g>
      ))}
      <line x1={left} x2={width} y1={y(PASS)} y2={y(PASS)} stroke="var(--warn)" strokeDasharray="4 4" />
      <path d={path} fill="none" stroke="var(--accent)" strokeWidth="2.5" />
      {points.map((lesson, index) => (
        <g key={lesson.id}>
          <circle cx={x(index)} cy={y(lesson.average ?? 0)} r="4" fill={(lesson.average ?? 0) >= PASS ? 'var(--pass)' : 'var(--warn)'}>
            <title>
              {lesson.sequence}-dars · {formatDate(lesson.date)} · o'rtacha {lesson.average} · {lesson.passed}/{lesson.answers} o'tdi
            </title>
          </circle>
          <text x={x(index)} y={height - 6} textAnchor="middle" fontSize="11" fill="var(--ink3)">
            {lesson.sequence}
          </text>
        </g>
      ))}
    </svg>
  )
}
