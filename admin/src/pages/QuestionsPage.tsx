import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { api, ApiError, type Paginated } from '../api/client'
import { useSubjectOptions, useTopics } from '../api/queries'
import type { Difficulty, Question, QuestionOption, QuestionType, Topic } from '../api/types'
import { ConfirmDialog, difficultyLabel, EmptyState, ErrorState, errorMessage, Field, fieldError, Modal, Pager, ScoreText, TableSkeleton, useToast } from '../ui/components'
import { ImportModal } from '../ui/ImportModal'
import { PlusIcon, SearchIcon, UploadIcon } from '../ui/icons'

const LABELS = ['A', 'B', 'C', 'D'] as const

/** W6 — the question bank of a subject, filtered by topic, type and difficulty. */
export function QuestionsPage() {
  const [params, setParams] = useSearchParams()
  const subjects = useSubjectOptions()
  const subjectId = params.get('subject') ? Number(params.get('subject')) : (subjects.data?.[0]?.id ?? null)
  const topicId = params.get('topic') ? Number(params.get('topic')) : null
  const topics = useTopics(subjectId)
  const [search, setSearch] = useState('')
  const [type, setType] = useState<QuestionType | ''>('')
  const [difficulty, setDifficulty] = useState<Difficulty | ''>('')
  const [withoutAnswer, setWithoutAnswer] = useState(false)
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<Question | 'new' | null>(null)
  const [deleting, setDeleting] = useState<Question | null>(null)
  const [importing, setImporting] = useState(false)
  const toast = useToast()
  const client = useQueryClient()

  const list = useQuery({
    queryKey: ['questions', subjectId, topicId, { search, type, difficulty, withoutAnswer, page }],
    queryFn: () =>
      api.get<Paginated<Question>>(topicId === null ? `/subjects/${subjectId}/questions` : `/topics/${topicId}/questions`, {
        q: search,
        type,
        difficulty,
        without_answer: withoutAnswer,
        page,
      }),
    enabled: subjectId !== null,
  })

  const refresh = (): void => {
    void client.invalidateQueries({ queryKey: ['questions'] })
    void client.invalidateQueries({ queryKey: ['topics'] })
    void client.invalidateQueries({ queryKey: ['subjects'] })
  }

  const remove = useMutation({
    mutationFn: (question: Question) => api.delete(`/questions/${question.id}`),
    onSuccess: () => {
      toast("Savol o'chirildi")
      setDeleting(null)
      refresh()
    },
  })

  const select = (key: 'subject' | 'topic', value: string): void => {
    const next = new URLSearchParams(params)
    if (value === '') {
      next.delete(key)
    } else {
      next.set(key, value)
    }
    if (key === 'subject') {
      next.delete('topic')
    }
    setParams(next)
    setPage(1)
  }

  const topicTitle = (id: number): string => topics.data?.find((topic) => topic.id === id)?.title ?? ''
  const currentTopic = topics.data?.find((topic) => topic.id === topicId) ?? null
  const questions = list.data?.data ?? []
  const filtered = search !== '' || type !== '' || difficulty !== '' || withoutAnswer

  return (
    <>
      <div className="page-head">
        <div className="col" style={{ gap: 4 }}>
          <h1 className="page-title">Savollar banki</h1>
          {list.data && <span className="muted">{list.data.meta.total} ta savol</span>}
        </div>
        {subjectId !== null && (
          <div className="row">
            <button type="button" className="btn" disabled={currentTopic === null} title={currentTopic === null ? 'Import uchun mavzuni tanlang' : undefined} onClick={() => setImporting(true)}>
              <UploadIcon />
              Import
            </button>
            <button type="button" className="btn primary" disabled={(topics.data?.length ?? 0) === 0} onClick={() => setEditing('new')}>
              <PlusIcon />
              Yangi savol
            </button>
          </div>
        )}
      </div>

      <div className="filters">
        <label className="visually-hidden" htmlFor="q-subject">
          Fan
        </label>
        <select id="q-subject" className="select" style={{ width: 220 }} value={subjectId ?? ''} onChange={(event) => select('subject', event.target.value)}>
          {subjects.data?.map((subject) => (
            <option key={subject.id} value={subject.id}>
              {subject.name}
            </option>
          ))}
        </select>
        <label className="visually-hidden" htmlFor="q-topic">
          Mavzu
        </label>
        <select id="q-topic" className="select" style={{ width: 220 }} value={topicId ?? ''} onChange={(event) => select('topic', event.target.value)}>
          <option value="">Barcha mavzular</option>
          {topics.data?.map((topic) => (
            <option key={topic.id} value={topic.id}>
              {topic.position}. {topic.title}
            </option>
          ))}
        </select>
        <label className="row input" style={{ width: 240, gap: 8 }}>
          <SearchIcon />
          <span className="visually-hidden">Savol matni bo'yicha qidirish</span>
          <input
            style={{ border: 0, outline: 0, background: 'transparent', flex: 1, minWidth: 0 }}
            placeholder="Savol matni"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setPage(1)
            }}
          />
        </label>
      </div>
      <div className="filters">
        {(['', 'open', 'test'] as const).map((value) => (
          <button key={value} type="button" className="fchip" aria-pressed={type === value} onClick={() => (setType(value), setPage(1))}>
            {value === '' ? 'Barcha turlar' : value === 'open' ? 'Ochiq' : 'Test'}
          </button>
        ))}
        <span style={{ width: 8 }} />
        {(['', 'easy', 'medium', 'hard'] as const).map((value) => (
          <button key={value} type="button" className="fchip" aria-pressed={difficulty === value} onClick={() => (setDifficulty(value), setPage(1))}>
            {value === '' ? 'Har qanday qiyinlik' : difficultyLabel[value]}
          </button>
        ))}
        <span style={{ width: 8 }} />
        <button type="button" className="fchip" aria-pressed={withoutAnswer} onClick={() => (setWithoutAnswer(!withoutAnswer), setPage(1))}>
          Javobsizlar
        </button>
      </div>

      <section className="card" style={{ overflow: 'hidden' }}>
        {subjects.data?.length === 0 && <EmptyState title="Avval fan yarating" />}
        {subjectId !== null && topics.data?.length === 0 && <EmptyState title="Fanda mavzu yo'q" text="Savollar mavzuga biriktiriladi. «Fanlar va mavzular» bo'limida mavzu qo'shing." />}
        {list.isPending && subjectId !== null && <TableSkeleton />}
        {list.isError && <ErrorState error={list.error} onRetry={() => void list.refetch()} />}
        {list.data && questions.length === 0 && (topics.data?.length ?? 0) > 0 && (
          <EmptyState title={filtered ? "Filtr bo'yicha savol yo'q" : "Hali savol yo'q"} text={filtered ? undefined : "Savollarni bittalab qo'shing yoki Excel'dan import qiling."} />
        )}
        {questions.length > 0 && list.data && (
          <>
            <div className="table-wrap">
              <table className="tb">
                <thead>
                  <tr>
                    <th>Savol</th>
                    {topicId === null && <th>Mavzu</th>}
                    <th>Tur</th>
                    <th>Qiyinlik</th>
                    <th>So'ralgan</th>
                    <th>O'rtacha</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {questions.map((question) => (
                    <tr key={question.id}>
                      <td style={{ minWidth: 280, maxWidth: 480 }}>
                        <div style={{ fontWeight: 600 }}>{question.text}</div>
                        {!question.has_answer && <span className="chip warn">To'g'ri javob kiritilmagan</span>}
                      </td>
                      {topicId === null && <td className="muted">{topicTitle(question.topic_id)}</td>}
                      <td>
                        <span className={`chip ${question.type === 'test' ? 'info' : ''}`}>{question.type === 'test' ? 'Test' : 'Ochiq'}</span>
                      </td>
                      <td>{difficultyLabel[question.difficulty]}</td>
                      <td>{question.times_asked ?? 0}</td>
                      <td>
                        <ScoreText score={question.average_score} />
                      </td>
                      <td>
                        <div className="row" style={{ justifyContent: 'flex-end' }}>
                          <button type="button" className="btn sm" onClick={() => setEditing(question)}>
                            Tahrirlash
                          </button>
                          <button type="button" className="btn sm ghost" style={{ color: 'var(--danger)' }} onClick={() => setDeleting(question)}>
                            O'chirish
                          </button>
                        </div>
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

      {editing !== null && topics.data && (
        <QuestionEditor question={editing === 'new' ? null : editing} topics={topics.data} defaultTopicId={topicId ?? topics.data[0]?.id} onClose={() => setEditing(null)} onSaved={refresh} />
      )}
      {importing && currentTopic !== null && (
        <ImportModal
          title={`«${currentTopic.title}» — savollar importi`}
          endpoint={`/topics/${currentTopic.id}/questions/import`}
          template="questions"
          hint="Ustunlar: savol | to'g'ri javob | qiyinlik | A | B | C | D | to'g'ri variant. Variantlar bo'lsa — test savoli. Shablonni yuklab oling."
          placeholder={'Jarayon nima?;Bajarilayotgan dastur nusxasi;oson'}
          onClose={() => setImporting(false)}
          onImported={refresh}
        />
      )}
      {deleting !== null && (
        <ConfirmDialog
          title="Savolni o'chirasizmi?"
          text={`«${deleting.text.slice(0, 80)}» — baholangan savolni o'chirib bo'lmaydi, uni tahrirlang.`}
          confirmLabel="O'chirish"
          danger
          busy={remove.isPending}
          error={remove.isError ? errorMessage(remove.error) : null}
          onConfirm={() => remove.mutate(deleting)}
          onClose={() => {
            setDeleting(null)
            remove.reset()
          }}
        />
      )}
    </>
  )
}

export function QuestionEditor({
  question,
  topics,
  defaultTopicId,
  onClose,
  onSaved,
}: {
  question: Question | null
  topics: Topic[]
  defaultTopicId: number | undefined
  onClose: () => void
  onSaved: () => void
}) {
  const [topicId, setTopicId] = useState(String(question?.topic_id ?? defaultTopicId ?? ''))
  const [type, setType] = useState<QuestionType>(question?.type ?? 'open')
  const [difficulty, setDifficulty] = useState<Difficulty>(question?.difficulty ?? 'medium')
  const [text, setText] = useState(question?.text ?? '')
  const [answer, setAnswer] = useState(question?.answer_text ?? '')
  const [options, setOptions] = useState<QuestionOption[]>(() =>
    question?.options && question.options.length > 0 ? question.options : LABELS.map((label, index) => ({ label, text: '', is_correct: index === 0 })),
  )
  const toast = useToast()

  const save = useMutation({
    mutationFn: () => {
      const body = {
        type,
        difficulty,
        text: text.trim(),
        answer_text: answer.trim() === '' ? null : answer.trim(),
        // Empty trailing variants are dropped; a test needs 2–4.
        options: type === 'test' ? options.filter((option) => option.text.trim() !== '').map((option) => ({ ...option, text: option.text.trim() })) : undefined,
      }
      return question === null ? api.post(`/topics/${topicId}/questions`, body) : api.patch(`/questions/${question.id}`, { ...body, topic_id: Number(topicId) })
    },
    onSuccess: () => {
      toast(question === null ? "Savol qo'shildi" : 'Savol saqlandi')
      onSaved()
      onClose()
    },
  })

  const setOption = (index: number, patch: Partial<QuestionOption>): void => {
    setOptions((current) => current.map((option, at) => (at === index ? { ...option, ...patch } : patch.is_correct ? { ...option, is_correct: false } : option)))
  }

  const optionErrors = save.error instanceof ApiError ? Object.keys(save.error.errors).filter((key) => key.startsWith('options')) : []
  const optionsError = fieldError(save.error, 'options') ?? (optionErrors.length > 0 ? "Kamida 2 ta variantni to'ldiring" : undefined)
  const known = ['text', 'answer_text', 'options', 'topic_id', 'type', 'difficulty']
  const otherError = save.isError && !known.some((key) => fieldError(save.error, key)) && optionsError === undefined

  return (
    <Modal title={question === null ? 'Yangi savol' : 'Savolni tahrirlash'} onClose={onClose} wide>
      <form
        className="col"
        style={{ gap: 14 }}
        onSubmit={(event) => {
          event.preventDefault()
          save.mutate()
        }}
      >
        <div className="row wrap" style={{ alignItems: 'flex-end' }}>
          <div className="grow">
            <Field label="Mavzu" error={fieldError(save.error, 'topic_id')}>
              {(id) => (
                <select id={id} className="select" value={topicId} onChange={(event) => setTopicId(event.target.value)}>
                  {topics.map((topic) => (
                    <option key={topic.id} value={topic.id}>
                      {topic.position}. {topic.title}
                    </option>
                  ))}
                </select>
              )}
            </Field>
          </div>
          <div className="field">
            <span className="label">Turi</span>
            <div className="seg" role="group" aria-label="Savol turi">
              <button type="button" aria-pressed={type === 'open'} onClick={() => setType('open')}>
                Ochiq
              </button>
              <button type="button" aria-pressed={type === 'test'} onClick={() => setType('test')}>
                Test (A–D)
              </button>
            </div>
          </div>
          <div className="field">
            <span className="label">Qiyinlik</span>
            <div className="seg" role="group" aria-label="Qiyinlik">
              {(['easy', 'medium', 'hard'] as const).map((value) => (
                <button key={value} type="button" aria-pressed={difficulty === value} onClick={() => setDifficulty(value)}>
                  {difficultyLabel[value]}
                </button>
              ))}
            </div>
          </div>
        </div>

        <Field label="Savol matni *" error={fieldError(save.error, 'text')}>
          {(id, invalid) => <textarea id={id} rows={3} className={`textarea ${invalid ? 'invalid' : ''}`} value={text} onChange={(event) => setText(event.target.value)} />}
        </Field>

        {type === 'test' && (
          <fieldset className="col" style={{ gap: 8, border: 0, padding: 0, margin: 0 }}>
            <legend className="label" style={{ marginBottom: 6 }}>
              Variantlar — to'g'risini belgilang
            </legend>
            {options.map((option, index) => (
              <div key={option.label} className={`option-row ${option.is_correct ? 'correct' : ''}`}>
                <label className="option-radio">
                  <input type="radio" name="correct" checked={option.is_correct} onChange={() => setOption(index, { is_correct: true })} aria-label={`${option.label} to'g'ri javob`} />
                  <span className="option-label">{option.label}</span>
                </label>
                <input className="input grow" aria-label={`${option.label} varianti`} value={option.text} onChange={(event) => setOption(index, { text: event.target.value })} placeholder={index < 2 ? 'Majburiy' : 'Ixtiyoriy'} />
              </div>
            ))}
            {optionsError && (
              <span className="help error" role="alert">
                {optionsError}
              </span>
            )}
          </fieldset>
        )}

        <Field label={type === 'test' ? 'Izoh (ixtiyoriy)' : "To'g'ri javob"} help={type === 'open' ? "Doskada «Javobni ko'rsatish»da chiqadi" : undefined} error={fieldError(save.error, 'answer_text')}>
          {(id, invalid) => <textarea id={id} rows={3} className={`textarea ${invalid ? 'invalid' : ''}`} value={answer} onChange={(event) => setAnswer(event.target.value)} />}
        </Field>
        {type === 'open' && answer.trim() === '' && <div className="banner warn">Javobsiz savol ham saqlanadi, lekin doskada to'g'ri javobni ko'rsatib bo'lmaydi.</div>}
        {otherError && <div className="banner error">{errorMessage(save.error)}</div>}

        <div className="modal-actions">
          <button type="button" className="btn" onClick={onClose}>
            Bekor
          </button>
          <button type="submit" className="btn primary" disabled={save.isPending || text.trim() === '' || topicId === ''}>
            {save.isPending && <span className="spinner" />}
            Saqlash
          </button>
        </div>
      </form>
    </Modal>
  )
}
