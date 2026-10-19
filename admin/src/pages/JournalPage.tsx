import { useQuery } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useSearchParams } from 'react-router'
import { api } from '../api/client'
import { courseLabel, useCourseOptions } from '../api/queries'
import type { Journal } from '../api/types'
import { EmptyState, ErrorState, errorMessage, formatDate, ScoreText, TableSkeleton, useToast } from '../ui/components'
import { DownloadIcon } from '../ui/icons'

type Cell = Journal['rows'][number]['cells'][number]

/** W8 — the course journal: students × lessons, with xlsx/csv export. */
export function JournalPage() {
  const [params, setParams] = useSearchParams()
  const courses = useCourseOptions()
  const courseId = params.get('course') ? Number(params.get('course')) : (courses.data?.[0]?.id ?? null)
  const [debtorsOnly, setDebtorsOnly] = useState(false)
  const [exporting, setExporting] = useState<'xlsx' | 'csv' | null>(null)
  const toast = useToast()

  const query = useQuery({
    queryKey: ['journal', courseId],
    queryFn: () => api.get<{ data: Journal }>(`/courses/${courseId}/journal`).then((response) => response.data),
    enabled: courseId !== null,
  })

  const download = async (format: 'xlsx' | 'csv'): Promise<void> => {
    setExporting(format)
    try {
      await api.download(`/courses/${courseId}/journal/export`, `jurnal.${format}`, { format })
    } catch (error) {
      toast(errorMessage(error), 'error')
    } finally {
      setExporting(null)
    }
  }

  const journal = query.data
  const rows = (journal?.rows ?? []).filter((row) => {
    const score = lastScored(row.cells)?.score
    return !debtorsOnly || (score !== null && score !== undefined && score < 60)
  })

  return (
    <>
      <div className="page-head">
        <div className="col" style={{ gap: 4 }}>
          <h1 className="page-title">Jurnal</h1>
          {journal && (
            <span className="muted">
              {journal.course.group} · {journal.course.subject} · {journal.lessons.length} dars
            </span>
          )}
        </div>
        <div className="row">
          <button type="button" className="btn" disabled={!journal || exporting !== null} onClick={() => void download('csv')}>
            {exporting === 'csv' ? <span className="spinner" /> : <DownloadIcon />}
            CSV
          </button>
          <button type="button" className="btn primary" disabled={!journal || exporting !== null} onClick={() => void download('xlsx')}>
            {exporting === 'xlsx' ? <span className="spinner" /> : <DownloadIcon />}
            Excel
          </button>
        </div>
      </div>

      <div className="filters">
        <label className="visually-hidden" htmlFor="journal-course">
          Kurs
        </label>
        <select id="journal-course" className="select" style={{ width: 300 }} value={courseId ?? ''} onChange={(event) => setParams({ course: event.target.value })}>
          {courses.data?.map((course) => (
            <option key={course.id} value={course.id}>
              {courseLabel(course)}
            </option>
          ))}
        </select>
        <button type="button" className="fchip" aria-pressed={debtorsOnly} onClick={() => setDebtorsOnly(!debtorsOnly)}>
          Faqat qarzdorlar
        </button>
        <span className="small row" style={{ gap: 12 }}>
          <span>
            <span className="score-pass">≥60</span> o'tdi
          </span>
          <span>
            <span className="score-fail">&lt;60</span> qarzdor
          </span>
          <span>Y — yo'q edi</span>
          <span>· — so'ralmagan</span>
        </span>
      </div>

      <section className="card" style={{ overflow: 'hidden' }}>
        {courses.data?.length === 0 && <EmptyState title="Kurs yo'q" text="Guruhga fan biriktirilgach jurnal paydo bo'ladi." />}
        {query.isPending && courseId !== null && <TableSkeleton rows={8} />}
        {query.isError && <ErrorState error={query.error} onRetry={() => void query.refetch()} />}
        {journal && journal.lessons.length === 0 && <EmptyState title="Hali dars o'tilmagan" text="Jurnal birinchi darsdan keyin to'ladi." />}
        {journal && journal.lessons.length > 0 && rows.length === 0 && <EmptyState title={debtorsOnly ? "Qarzdor yo'q" : "Talaba yo'q"} />}
        {journal && journal.lessons.length > 0 && rows.length > 0 && (
          <div className="table-wrap journal-wrap">
            <table className="tb journal">
              <thead>
                <tr>
                  <th className="sticky">Talaba</th>
                  {journal.lessons.map((lesson) => (
                    <th key={lesson.id} className="num" title={formatDate(lesson.date)}>
                      <Link to={`/lessons/${lesson.id}`}>{lesson.sequence}</Link>
                    </th>
                  ))}
                  <th className="num">O'rt.</th>
                  <th className="num">So'r.</th>
                </tr>
              </thead>
              <tbody>
                {rows.map((row) => (
                  <tr key={row.student_id} style={row.is_active ? undefined : { opacity: 0.6 }}>
                    <td className="sticky" style={{ fontWeight: 700, whiteSpace: 'nowrap' }}>
                      {row.full_name}
                    </td>
                    {row.cells.map((cell) => (
                      <td key={cell.lesson_id} className="num">
                        <JournalCell cell={cell} />
                      </td>
                    ))}
                    <td className="num" style={{ fontWeight: 800 }}>
                      <ScoreText score={row.average} />
                    </td>
                    <td className="num">{row.asked_count}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>
    </>
  )
}

function lastScored(cells: Cell[]): Cell | undefined {
  return [...cells].reverse().find((cell) => cell.status === 'graded' || cell.status === 'no_answer')
}

function JournalCell({ cell }: { cell: Cell }) {
  switch (cell.status) {
    case 'graded':
    case 'no_answer':
      return <ScoreText score={cell.score} />
    case 'absent':
      return (
        <span className="muted" title="Yo'q edi">
          Y
        </span>
      )
    case 'pending':
      return (
        <span className="chip info" title="Javob kutilmoqda">
          …
        </span>
      )
    default:
      return <span className="muted">·</span>
  }
}
