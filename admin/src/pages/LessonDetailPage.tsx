import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { Fragment, useState } from 'react'
import { Link, useParams } from 'react-router'
import { api } from '../api/client'
import type { Answer, Lesson } from '../api/types'
import { EmptyState, ErrorState, errorMessage, Field, fieldError, formatDateTime, Modal, queueLabel, ScoreText, TableSkeleton, useToast } from '../ui/components'
import { lessonStatus } from './LessonsPage'

const answerStatus: Record<Answer['status'], { text: string; tone: string }> = {
  pending: { text: 'Javob kutilmoqda', tone: 'info' },
  graded: { text: 'Baholangan', tone: '' },
  no_answer: { text: 'Javob bermadi', tone: 'warn' },
  absent: { text: "Yo'q edi", tone: '' },
}

/** W7 detail — every answer of a lesson, with regrading inside the edit window. */
export function LessonDetailPage() {
  const { lessonId } = useParams()
  const [regrading, setRegrading] = useState<Answer | null>(null)
  const [openHistory, setOpenHistory] = useState<number | null>(null)
  const [now] = useState(() => Date.now())

  const query = useQuery({
    queryKey: ['lessons', Number(lessonId)],
    queryFn: () => api.get<{ data: Lesson }>(`/lessons/${lessonId}`).then((response) => response.data),
  })

  if (query.isPending) {
    return (
      <div className="card">
        <TableSkeleton rows={8} />
      </div>
    )
  }
  if (query.isError) {
    return (
      <div className="card">
        <ErrorState error={query.error} onRetry={() => void query.refetch()} />
      </div>
    )
  }

  const lesson = query.data
  const answers = lesson.answers ?? []
  const scored = answers.filter((answer) => answer.status === 'graded' || answer.status === 'no_answer')
  const average = scored.length > 0 ? Math.round(scored.reduce((sum, answer) => sum + (answer.score ?? 0), 0) / scored.length) : null
  const passed = scored.filter((answer) => answer.passed).length

  return (
    <>
      <div className="col" style={{ gap: 6 }}>
        <div className="crumb">
          <Link to="/lessons">Darslar</Link>
          <span>/</span>
          <span>
            {lesson.group} · {lesson.sequence}-dars
          </span>
        </div>
        <div className="page-head">
          <div className="col" style={{ gap: 4 }}>
            <h1 className="page-title">
              {lesson.group} · {lesson.subject}
            </h1>
            <span className="muted">
              {lesson.sequence}-dars · {formatDateTime(lesson.started_at)}
              {lesson.finished_at && ` — ${formatDateTime(lesson.finished_at)}`}
            </span>
          </div>
          <span className={`chip ${lessonStatus[lesson.status].tone}`}>{lessonStatus[lesson.status].text}</span>
        </div>
      </div>

      <div className="kpi">
        <div className="card pad col" style={{ gap: 6 }}>
          <span className="small">Savollar</span>
          <span className="kpi-value">
            {scored.length} / {lesson.question_count}
          </span>
        </div>
        <div className="card pad col" style={{ gap: 6 }}>
          <span className="small">O'rtacha ball</span>
          <span className="kpi-value">
            <ScoreText score={average} />
          </span>
        </div>
        <div className="card pad col" style={{ gap: 6 }}>
          <span className="small">O'tdi (≥60)</span>
          <span className="kpi-value" style={{ color: 'var(--pass)' }}>
            {passed}
          </span>
        </div>
        <div className="card pad col" style={{ gap: 6 }}>
          <span className="small">Qarzdor bo'ldi</span>
          <span className="kpi-value" style={{ color: 'var(--warn)' }}>
            {scored.length - passed}
          </span>
        </div>
      </div>

      <section className="card" style={{ overflow: 'hidden' }}>
        {answers.length === 0 ? (
          <EmptyState title="Bu darsda hali hech kim so'ralmagan" />
        ) : (
          <div className="table-wrap">
            <table className="tb">
              <thead>
                <tr>
                  <th style={{ width: 40 }}>№</th>
                  <th>Talaba</th>
                  <th>Savol</th>
                  <th>Holat</th>
                  <th>Ball</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {answers.map((answer, index) => {
                  const editable = answer.editable_until !== null && new Date(answer.editable_until).getTime() > now
                  const edits = answer.edits ?? []
                  return (
                    <Fragment key={answer.id}>
                      <tr>
                        <td className="num">{index + 1}</td>
                        <td style={{ fontWeight: 700 }}>
                          {answer.student?.full_name}
                          {answer.pick_reason && <div className="small">Random: {queueLabel[answer.pick_reason]?.text ?? answer.pick_reason}</div>}
                        </td>
                        <td style={{ minWidth: 260, maxWidth: 420 }}>
                          {answer.question?.text}
                          {answer.chosen_option && (
                            <div className="small">
                              Tanlagan: {answer.chosen_option}
                              {answer.question?.options.find((option) => option.is_correct)?.label === answer.chosen_option ? ' ✓' : ` · to'g'risi ${answer.question?.options.find((option) => option.is_correct)?.label ?? '?'}`}
                            </div>
                          )}
                          {answer.comment && <div className="small">Izoh: {answer.comment}</div>}
                        </td>
                        <td>
                          <span className={`chip ${answerStatus[answer.status].tone}`}>{answerStatus[answer.status].text}</span>
                        </td>
                        <td>
                          <ScoreText score={answer.score} />
                        </td>
                        <td>
                          <div className="row" style={{ justifyContent: 'flex-end' }}>
                            {edits.length > 0 && (
                              <button type="button" className="btn sm ghost" aria-expanded={openHistory === answer.id} onClick={() => setOpenHistory(openHistory === answer.id ? null : answer.id)}>
                                Tarix ({edits.length})
                              </button>
                            )}
                            {(answer.status === 'graded' || answer.status === 'no_answer') && (
                              <button
                                type="button"
                                className="btn sm"
                                disabled={!editable}
                                title={editable ? `${formatDateTime(answer.editable_until)} gacha tuzatish mumkin` : 'Tuzatish muddati tugagan'}
                                onClick={() => setRegrading(answer)}
                              >
                                Bahoni tuzatish
                              </button>
                            )}
                          </div>
                        </td>
                      </tr>
                      {openHistory === answer.id && (
                        <tr>
                          <td />
                          <td colSpan={5} style={{ background: 'var(--surface2)' }}>
                            <div className="col" style={{ gap: 6 }}>
                              {edits.map((edit, at) => (
                                <div key={at} className="small">
                                  {formatDateTime(edit.at)} · {edit.by ?? '—'}: {edit.old_score ?? '—'} → <b>{edit.new_score ?? '—'}</b> — «{edit.reason}»
                                </div>
                              ))}
                            </div>
                          </td>
                        </tr>
                      )}
                    </Fragment>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {regrading !== null && <RegradeModal answer={regrading} lessonId={lesson.id} onClose={() => setRegrading(null)} />}
    </>
  )
}

function RegradeModal({ answer, lessonId, onClose }: { answer: Answer; lessonId: number; onClose: () => void }) {
  const [score, setScore] = useState(String(answer.score ?? ''))
  const [reason, setReason] = useState('')
  const toast = useToast()
  const client = useQueryClient()

  const save = useMutation({
    mutationFn: () => api.patch(`/answers/${answer.id}`, { score: Number(score), reason: reason.trim() }),
    onSuccess: () => {
      toast('Baho tuzatildi')
      void client.invalidateQueries({ queryKey: ['lessons', lessonId] })
      void client.invalidateQueries({ queryKey: ['journal'] })
      onClose()
    },
  })

  const value = Number(score)
  const valid = score !== '' && Number.isInteger(value) && value >= 0 && value <= 100

  return (
    <Modal title={`${answer.student?.full_name} — bahoni tuzatish`} onClose={onClose}>
      <form
        className="col"
        style={{ gap: 14 }}
        onSubmit={(event) => {
          event.preventDefault()
          save.mutate()
        }}
      >
        <span className="muted">
          Hozirgi baho: <ScoreText score={answer.score} />. Tuzatish tarixda saqlanadi.
        </span>
        <Field label="Yangi ball (0–100)" error={fieldError(save.error, 'score') ?? (score !== '' && !valid ? "0 dan 100 gacha butun son" : undefined)}>
          {(id, invalid) => <input id={id} type="number" min={0} max={100} inputMode="numeric" className={`input ${invalid ? 'invalid' : ''}`} value={score} onChange={(event) => setScore(event.target.value)} />}
        </Field>
        <div className="row wrap" style={{ gap: 6 }}>
          {[0, 40, 60, 75, 90, 100].map((preset) => (
            <button key={preset} type="button" className="fchip" aria-pressed={value === preset && score !== ''} onClick={() => setScore(String(preset))}>
              {preset}
            </button>
          ))}
        </div>
        {valid && (answer.score ?? 0) >= 60 !== value >= 60 && (
          <div className="banner warn">{value >= 60 ? "Talaba qarzdorlikdan chiqadi va dam olish navbatiga o'tadi." : "Talaba qarzdor bo'lib, random tanlovga qaytadi."}</div>
        )}
        <Field label="Sabab *" help="Masalan: javobni qayta ko'rib chiqdim" error={fieldError(save.error, 'reason')}>
          {(id, invalid) => <input id={id} className={`input ${invalid ? 'invalid' : ''}`} value={reason} onChange={(event) => setReason(event.target.value)} />}
        </Field>
        {save.isError && !fieldError(save.error, 'score') && !fieldError(save.error, 'reason') && <div className="banner error">{errorMessage(save.error)}</div>}
        <div className="modal-actions">
          <button type="button" className="btn" onClick={onClose}>
            Bekor
          </button>
          <button type="submit" className="btn primary" disabled={!valid || reason.trim().length < 3 || save.isPending}>
            Saqlash
          </button>
        </div>
      </form>
    </Modal>
  )
}
