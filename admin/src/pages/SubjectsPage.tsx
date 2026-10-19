import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link, useSearchParams } from 'react-router'
import { api, type Paginated } from '../api/client'
import type { Subject, Topic } from '../api/types'
import { useAuth } from '../auth'
import { ConfirmDialog, EmptyState, ErrorState, errorMessage, Field, fieldError, Modal, TableSkeleton, useToast } from '../ui/components'
import { DownIcon, EditIcon, PlusIcon, SearchIcon, TrashIcon, UpIcon } from '../ui/icons'

/** W4 — subjects on the left, the selected subject's ordered topics on the right. */
export function SubjectsPage() {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'
  const [params, setParams] = useSearchParams()
  const [search, setSearch] = useState('')
  const [editing, setEditing] = useState<Subject | 'new' | null>(null)
  const selectedId = params.get('subject') ? Number(params.get('subject')) : null

  const list = useQuery({
    queryKey: ['subjects', { search }],
    queryFn: () => api.get<Paginated<Subject>>('/subjects', { q: search, per_page: 100 }),
  })

  const subjects = list.data?.data ?? []
  const activeId = selectedId ?? subjects[0]?.id ?? null

  return (
    <>
      <div className="page-head">
        <div className="col" style={{ gap: 4 }}>
          <h1 className="page-title">Fanlar va mavzular</h1>
          <span className="muted">Mavzular tartibi darsda mavzu tanlashda shunday ko'rinadi.</span>
        </div>
        {isAdmin && (
          <button type="button" className="btn primary" onClick={() => setEditing('new')}>
            <PlusIcon />
            Yangi fan
          </button>
        )}
      </div>

      <div className="split">
        <section className="card side-col" style={{ overflow: 'hidden', alignSelf: 'flex-start' }}>
          <div className="pad" style={{ paddingBottom: 8 }}>
            <label className="row input" style={{ gap: 8 }}>
              <SearchIcon />
              <span className="visually-hidden">Fan qidirish</span>
              <input style={{ border: 0, outline: 0, background: 'transparent', flex: 1, minWidth: 0 }} placeholder="Fan nomi yoki kodi" value={search} onChange={(event) => setSearch(event.target.value)} />
            </label>
          </div>
          {list.isPending && <TableSkeleton rows={4} />}
          {list.isError && <ErrorState error={list.error} onRetry={() => void list.refetch()} />}
          {list.data && subjects.length === 0 && <EmptyState title={search ? 'Fan topilmadi' : "Hali fan yo'q"} />}
          <div className="col" style={{ gap: 0 }} role="list">
            {subjects.map((subject) => (
              <button
                key={subject.id}
                type="button"
                role="listitem"
                className="subject-item"
                aria-current={subject.id === activeId}
                onClick={() => setParams({ subject: String(subject.id) })}
              >
                <span className="col grow" style={{ gap: 0, alignItems: 'flex-start' }}>
                  <span style={{ fontWeight: 700 }}>{subject.name}</span>
                  <span className="small">
                    {subject.code ?? 'Kodsiz'} · {subject.topics_count ?? 0} mavzu
                  </span>
                </span>
              </button>
            ))}
          </div>
        </section>

        <section className="card main-col" style={{ overflow: 'hidden' }}>
          {activeId === null ? (
            <EmptyState title="Fan tanlang" text="Chapdagi ro'yxatdan fanni tanlang yoki yangisini yarating." />
          ) : (
            <TopicsPane key={activeId} subjectId={activeId} onEdit={setEditing} />
          )}
        </section>
      </div>

      {editing !== null && (
        <SubjectForm
          subject={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onCreated={(subject) => setParams({ subject: String(subject.id) })}
        />
      )}
    </>
  )
}

function TopicsPane({ subjectId, onEdit }: { subjectId: number; onEdit: (subject: Subject) => void }) {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'
  const [newTitle, setNewTitle] = useState('')
  const [renaming, setRenaming] = useState<Topic | null>(null)
  const [deleting, setDeleting] = useState<Topic | 'subject' | null>(null)
  const [, setParams] = useSearchParams()
  const toast = useToast()
  const client = useQueryClient()

  const query = useQuery({
    queryKey: ['subjects', subjectId],
    queryFn: () => api.get<{ data: Subject }>(`/subjects/${subjectId}`).then((response) => response.data),
  })

  const refresh = (): void => {
    void client.invalidateQueries({ queryKey: ['subjects'] })
    void client.invalidateQueries({ queryKey: ['topics', subjectId] })
  }

  const add = useMutation({
    mutationFn: () => api.post(`/subjects/${subjectId}/topics`, { title: newTitle.trim() }),
    onSuccess: () => {
      setNewTitle('')
      refresh()
    },
  })

  const reorder = useMutation({
    mutationFn: (ids: number[]) => api.patch(`/subjects/${subjectId}/topics/order`, { topic_ids: ids }),
    onSuccess: refresh,
    onError: (error) => toast(errorMessage(error), 'error'),
  })

  const remove = useMutation({
    mutationFn: (target: Topic | 'subject') => (target === 'subject' ? api.delete(`/subjects/${subjectId}`) : api.delete(`/topics/${target.id}`)),
    onSuccess: (_, target) => {
      toast(target === 'subject' ? "Fan o'chirildi" : "Mavzu o'chirildi")
      setDeleting(null)
      if (target === 'subject') {
        setParams({})
      }
      refresh()
    },
  })

  if (query.isPending) {
    return <TableSkeleton />
  }
  if (query.isError) {
    return <ErrorState error={query.error} onRetry={() => void query.refetch()} />
  }

  const subject = query.data
  const topics = [...(subject.topics ?? [])].sort((a, b) => a.position - b.position)

  const move = (index: number, delta: number): void => {
    const ids = topics.map((topic) => topic.id)
    const [moved] = ids.splice(index, 1)
    ids.splice(index + delta, 0, moved)
    reorder.mutate(ids)
  }

  const submitTopic = (event: FormEvent): void => {
    event.preventDefault()
    if (newTitle.trim() !== '') {
      add.mutate()
    }
  }

  return (
    <>
      <div className="pad row" style={{ justifyContent: 'space-between', paddingBottom: 12 }}>
        <div className="col" style={{ gap: 2 }}>
          <h2 className="card-title">{subject.name}</h2>
          <span className="small">
            {subject.code ?? 'Kodsiz'} · {topics.length} mavzu
          </span>
        </div>
        <div className="row">
          <Link className="btn sm" to={`/questions?subject=${subject.id}`}>
            Savollar banki
          </Link>
          {isAdmin && (
            <>
              <button type="button" className="btn sm" onClick={() => onEdit(subject)}>
                Tahrirlash
              </button>
              <button type="button" className="btn sm ghost" style={{ color: 'var(--danger)' }} onClick={() => setDeleting('subject')}>
                O'chirish
              </button>
            </>
          )}
        </div>
      </div>

      {topics.length === 0 ? (
        <EmptyState title="Mavzu qo'shilmagan" text="Har bir mavzuga savollar biriktiriladi. Birinchi mavzuni pastda yozing." />
      ) : (
        <div className="table-wrap">
          <table className="tb">
            <thead>
              <tr>
                <th style={{ width: 48 }}>№</th>
                <th>Mavzu</th>
                <th>Savollar</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {topics.map((topic, index) => (
                <tr key={topic.id}>
                  <td className="num">{index + 1}</td>
                  <td style={{ fontWeight: 700 }}>
                    <Link to={`/questions?subject=${subject.id}&topic=${topic.id}`}>{topic.title}</Link>
                  </td>
                  <td>
                    {topic.questions_count ?? 0}
                    {(topic.questions_without_answer_count ?? 0) > 0 && (
                      <span className="chip warn" style={{ marginLeft: 8 }}>
                        {topic.questions_without_answer_count} javobsiz
                      </span>
                    )}
                  </td>
                  <td>
                    <div className="row" style={{ justifyContent: 'flex-end', gap: 4 }}>
                      <button type="button" className="icon-btn" aria-label={`${topic.title} — yuqoriga`} disabled={index === 0 || reorder.isPending} onClick={() => move(index, -1)}>
                        <UpIcon />
                      </button>
                      <button type="button" className="icon-btn" aria-label={`${topic.title} — pastga`} disabled={index === topics.length - 1 || reorder.isPending} onClick={() => move(index, 1)}>
                        <DownIcon />
                      </button>
                      <button type="button" className="icon-btn" aria-label={`${topic.title} — nomini o'zgartirish`} onClick={() => setRenaming(topic)}>
                        <EditIcon />
                      </button>
                      <button type="button" className="icon-btn" aria-label={`${topic.title} — o'chirish`} onClick={() => setDeleting(topic)}>
                        <TrashIcon />
                      </button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      <form className="pad row" style={{ alignItems: 'flex-start', borderTop: '1px solid var(--line)' }} onSubmit={submitTopic}>
        <div className="grow">
          <label className="visually-hidden" htmlFor="new-topic">
            Yangi mavzu
          </label>
          <input id="new-topic" className={`input ${fieldError(add.error, 'title') ? 'invalid' : ''}`} placeholder="Yangi mavzu nomi" value={newTitle} onChange={(event) => setNewTitle(event.target.value)} />
          {add.isError && (
            <span className="help error" role="alert">
              {errorMessage(add.error)}
            </span>
          )}
        </div>
        <button type="submit" className="btn primary" disabled={add.isPending || newTitle.trim() === ''}>
          <PlusIcon />
          Qo'shish
        </button>
      </form>

      {renaming !== null && <TopicForm topic={renaming} onClose={() => setRenaming(null)} onSaved={refresh} />}
      {deleting !== null && (
        <ConfirmDialog
          title={deleting === 'subject' ? `${subject.name} fanini o'chirasizmi?` : `«${deleting.title}» mavzusini o'chirasizmi?`}
          text={deleting === 'subject' ? "Fanning barcha mavzulari va savollari o'chadi. Baholar bor fanni o'chirib bo'lmaydi." : "Mavzudagi savollar ham o'chadi. Baholangan savollar bo'lsa, o'chirib bo'lmaydi."}
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

function TopicForm({ topic, onClose, onSaved }: { topic: Topic; onClose: () => void; onSaved: () => void }) {
  const [title, setTitle] = useState(topic.title)
  const save = useMutation({
    mutationFn: () => api.patch(`/topics/${topic.id}`, { title: title.trim() }),
    onSuccess: () => {
      onSaved()
      onClose()
    },
  })

  return (
    <Modal title="Mavzu nomi" onClose={onClose}>
      <form
        className="col"
        style={{ gap: 14 }}
        onSubmit={(event) => {
          event.preventDefault()
          save.mutate()
        }}
      >
        <Field label="Nomi" error={fieldError(save.error, 'title')}>
          {(id, invalid) => <input id={id} className={`input ${invalid ? 'invalid' : ''}`} value={title} onChange={(event) => setTitle(event.target.value)} />}
        </Field>
        <div className="modal-actions">
          <button type="button" className="btn" onClick={onClose}>
            Bekor
          </button>
          <button type="submit" className="btn primary" disabled={save.isPending || title.trim() === ''}>
            Saqlash
          </button>
        </div>
      </form>
    </Modal>
  )
}

function SubjectForm({ subject, onClose, onCreated }: { subject: Subject | null; onClose: () => void; onCreated: (subject: Subject) => void }) {
  const [form, setForm] = useState({ name: subject?.name ?? '', code: subject?.code ?? '' })
  const toast = useToast()
  const client = useQueryClient()

  const save = useMutation({
    mutationFn: () => {
      const body = { name: form.name.trim(), code: form.code.trim() === '' ? null : form.code.trim() }
      return subject === null ? api.post<{ data: Subject }>('/subjects', body) : api.patch<{ data: Subject }>(`/subjects/${subject.id}`, body)
    },
    onSuccess: (response) => {
      toast(subject === null ? 'Fan yaratildi' : 'Fan saqlandi')
      void client.invalidateQueries({ queryKey: ['subjects'] })
      if (subject === null) {
        onCreated(response.data)
      }
      onClose()
    },
  })

  return (
    <Modal title={subject === null ? 'Yangi fan' : `${subject.name} — tahrirlash`} onClose={onClose}>
      <form
        className="col"
        style={{ gap: 14 }}
        onSubmit={(event) => {
          event.preventDefault()
          save.mutate()
        }}
      >
        <Field label="Fan nomi *" error={fieldError(save.error, 'name')}>
          {(id, invalid) => <input id={id} className={`input ${invalid ? 'invalid' : ''}`} value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Operatsion tizimlar" />}
        </Field>
        <Field label="Kodi" help="Ixtiyoriy, takrorlanmas" error={fieldError(save.error, 'code')}>
          {(id, invalid) => <input id={id} className={`input ${invalid ? 'invalid' : ''}`} value={form.code} onChange={(event) => setForm({ ...form, code: event.target.value })} placeholder="OTX2506" />}
        </Field>
        {save.isError && !fieldError(save.error, 'name') && !fieldError(save.error, 'code') && <div className="banner error">{errorMessage(save.error)}</div>}
        <div className="modal-actions">
          <button type="button" className="btn" onClick={onClose}>
            Bekor
          </button>
          <button type="submit" className="btn primary" disabled={save.isPending || form.name.trim() === ''}>
            Saqlash
          </button>
        </div>
      </form>
    </Modal>
  )
}
