import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { useSearchParams } from 'react-router'
import { api, type Paginated } from '../api/client'
import { useCourseOptions, useGroupOptions } from '../api/queries'
import type { QueueState, Student } from '../api/types'
import { ConfirmDialog, EmptyState, ErrorState, errorMessage, Field, fieldError, Modal, Pager, queueLabel, ScoreText, TableSkeleton, useToast } from '../ui/components'
import { ImportModal } from '../ui/ImportModal'
import { PlusIcon, SearchIcon, UploadIcon } from '../ui/icons'

interface StudentList extends Paginated<Student> {
  meta: Paginated<Student>['meta'] & { queue_counts?: Record<QueueState, number>; sequence?: number }
}

const states: QueueState[] = ['never_asked', 'queue', 'debtor', 'resting']

/** W5 — students of a group, with their queue status in one of its courses. */
export function StudentsPage() {
  const [params, setParams] = useSearchParams()
  const groups = useGroupOptions()
  const groupId = params.get('group') ? Number(params.get('group')) : (groups.data?.[0]?.id ?? null)
  const courses = useCourseOptions(groupId)
  const courseId = params.get('course') ? Number(params.get('course')) : null
  const [search, setSearch] = useState('')
  const [state, setState] = useState<QueueState | null>(null)
  const [inactive, setInactive] = useState(false)
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<Student | 'new' | null>(null)
  const [deleting, setDeleting] = useState<Student | null>(null)
  const [importing, setImporting] = useState(false)
  const toast = useToast()
  const client = useQueryClient()

  const list = useQuery({
    queryKey: ['students', groupId, { courseId, search, state, inactive, page }],
    queryFn: () =>
      api.get<StudentList>(`/groups/${groupId}/students`, {
        course_id: courseId,
        queue_state: courseId === null ? null : state,
        q: search,
        include_inactive: inactive,
        page: courseId === null ? page : null,
      }),
    enabled: groupId !== null,
  })

  const refresh = (): void => {
    void client.invalidateQueries({ queryKey: ['students'] })
    void client.invalidateQueries({ queryKey: ['groups'] })
  }

  const toggleActive = useMutation({
    mutationFn: (student: Student) => api.patch(`/students/${student.id}`, { is_active: !student.is_active }),
    onSuccess: (_, student) => {
      toast(student.is_active ? `${student.full_name} nofaol qilindi` : `${student.full_name} faollashtirildi`)
      refresh()
    },
    onError: (error) => toast(errorMessage(error), 'error'),
  })

  const remove = useMutation({
    mutationFn: (student: Student) => api.delete(`/students/${student.id}`),
    onSuccess: () => {
      toast("Talaba o'chirildi")
      setDeleting(null)
      refresh()
    },
  })

  const select = (key: 'group' | 'course', value: string): void => {
    const next = new URLSearchParams(params)
    if (value === '') {
      next.delete(key)
    } else {
      next.set(key, value)
    }
    if (key === 'group') {
      next.delete('course')
    }
    setParams(next)
    setState(null)
    setPage(1)
  }

  const counts = list.data?.meta.queue_counts
  const students = list.data?.data ?? []

  return (
    <>
      <div className="page-head">
        <div className="col" style={{ gap: 4 }}>
          <h1 className="page-title">Talabalar</h1>
          {list.data && <span className="muted">{courseId === null ? list.data.meta.total : students.length} ta talaba</span>}
        </div>
        {groupId !== null && (
          <div className="row">
            <button type="button" className="btn" onClick={() => setImporting(true)}>
              <UploadIcon />
              Import
            </button>
            <button type="button" className="btn primary" onClick={() => setEditing('new')}>
              <PlusIcon />
              Talaba qo'shish
            </button>
          </div>
        )}
      </div>

      <div className="filters">
        <label className="visually-hidden" htmlFor="student-group">
          Guruh
        </label>
        <select id="student-group" className="select" style={{ width: 160 }} value={groupId ?? ''} onChange={(event) => select('group', event.target.value)}>
          {groups.data?.map((group) => (
            <option key={group.id} value={group.id}>
              {group.name}
            </option>
          ))}
        </select>
        <label className="visually-hidden" htmlFor="student-course">
          Fan (navbat holati)
        </label>
        <select id="student-course" className="select" style={{ width: 240 }} value={courseId ?? ''} onChange={(event) => select('course', event.target.value)}>
          <option value="">Navbat holatisiz</option>
          {courses.data?.map((course) => (
            <option key={course.id} value={course.id}>
              {course.subject?.name}
            </option>
          ))}
        </select>
        <label className="row input" style={{ width: 240, gap: 8 }}>
          <SearchIcon />
          <span className="visually-hidden">Talaba qidirish</span>
          <input
            style={{ border: 0, outline: 0, background: 'transparent', flex: 1, minWidth: 0 }}
            placeholder="F.I.Sh."
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setPage(1)
            }}
          />
        </label>
        <label className="check">
          <input type="checkbox" checked={inactive} onChange={(event) => setInactive(event.target.checked)} />
          Nofaollar ham
        </label>
      </div>

      {courseId !== null && counts && (
        <div className="filters">
          <button type="button" className="fchip" aria-pressed={state === null} onClick={() => setState(null)}>
            Hammasi
          </button>
          {states.map((key) => (
            <button key={key} type="button" className="fchip" aria-pressed={state === key} onClick={() => setState(key)}>
              {queueLabel[key].text} · {counts[key]}
            </button>
          ))}
        </div>
      )}

      <section className="card" style={{ overflow: 'hidden' }}>
        {groups.data?.length === 0 && <EmptyState title="Avval guruh yarating" text="Talabalar guruhga qo'shiladi." />}
        {list.isPending && groupId !== null && <TableSkeleton />}
        {list.isError && <ErrorState error={list.error} onRetry={() => void list.refetch()} />}
        {list.data && students.length === 0 && (
          <EmptyState
            title={search || state ? 'Talaba topilmadi' : "Guruhda talaba yo'q"}
            text={search || state ? undefined : "Ro'yxatni Excel'dan import qiling yoki bittalab qo'shing."}
            action={
              !search && !state ? (
                <button type="button" className="btn primary" onClick={() => setImporting(true)}>
                  Import qilish
                </button>
              ) : undefined
            }
          />
        )}
        {students.length > 0 && (
          <>
            <div className="table-wrap">
              <table className="tb">
                <thead>
                  <tr>
                    <th style={{ width: 48 }}>№</th>
                    <th>F.I.Sh.</th>
                    <th>Telefon</th>
                    {courseId !== null && (
                      <>
                        <th>Holat</th>
                        <th>So'ralgan</th>
                        <th>Oxirgi baho</th>
                      </>
                    )}
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {students.map((student, index) => (
                    <tr key={student.id} style={student.is_active ? undefined : { opacity: 0.6 }}>
                      <td className="num">{(courseId === null ? ((list.data?.meta.current_page ?? 1) - 1) * (list.data?.meta.per_page ?? 0) : 0) + index + 1}</td>
                      <td style={{ fontWeight: 700 }}>
                        {student.full_name} {!student.is_active && <span className="chip">Nofaol</span>}
                      </td>
                      <td className="muted">{student.phone ?? '—'}</td>
                      {courseId !== null && student.queue && (
                        <>
                          <td>
                            <span className={`chip ${queueLabel[student.queue.state].tone}`}>{queueLabel[student.queue.state].text}</span>
                            {student.queue.state === 'resting' && <span className="small"> · yana {student.queue.rest_lessons_left} dars</span>}
                          </td>
                          <td>{student.queue.asked_count}</td>
                          <td>
                            <ScoreText score={student.queue.last_score} />
                          </td>
                        </>
                      )}
                      <td>
                        <div className="row" style={{ justifyContent: 'flex-end' }}>
                          <button type="button" className="btn sm" onClick={() => setEditing(student)}>
                            Tahrirlash
                          </button>
                          <button type="button" className="btn sm" onClick={() => toggleActive.mutate(student)}>
                            {student.is_active ? 'Nofaol qilish' : 'Faollashtirish'}
                          </button>
                          <button type="button" className="btn sm ghost" style={{ color: 'var(--danger)' }} onClick={() => setDeleting(student)}>
                            O'chirish
                          </button>
                        </div>
                      </td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            {courseId === null && list.data && <Pager page={list.data.meta.current_page} lastPage={list.data.meta.last_page} total={list.data.meta.total} onPage={setPage} />}
          </>
        )}
      </section>

      {editing !== null && groupId !== null && <StudentForm groupId={groupId} student={editing === 'new' ? null : editing} onClose={() => setEditing(null)} onSaved={refresh} />}
      {importing && groupId !== null && (
        <ImportModal
          title={`${groups.data?.find((group) => group.id === groupId)?.name ?? ''} — talabalar importi`}
          endpoint={`/groups/${groupId}/students/import`}
          template="students"
          hint="Har qatorda bitta talaba: F.I.Sh. va ixtiyoriy telefon. Guruhda bor ismlar takror sifatida o'tkaziladi."
          placeholder={'Aliyev Vali Karimovich\nKarimova Nodira, +998901234567'}
          onClose={() => setImporting(false)}
          onImported={refresh}
        />
      )}
      {deleting !== null && (
        <ConfirmDialog
          title={`${deleting.full_name}ni o'chirasizmi?`}
          text="Bahosi bor talabani o'chirib bo'lmaydi — uni nofaol qiling, shunda random tanlovga tushmaydi."
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

function StudentForm({ groupId, student, onClose, onSaved }: { groupId: number; student: Student | null; onClose: () => void; onSaved: () => void }) {
  const groups = useGroupOptions()
  const [form, setForm] = useState({ full_name: student?.full_name ?? '', phone: student?.phone ?? '', study_group_id: String(student?.study_group_id ?? groupId) })
  const toast = useToast()

  const save = useMutation({
    mutationFn: () => {
      const body = { full_name: form.full_name.trim(), phone: form.phone.trim() === '' ? null : form.phone.trim() }
      return student === null ? api.post(`/groups/${groupId}/students`, body) : api.patch(`/students/${student.id}`, { ...body, study_group_id: Number(form.study_group_id) })
    },
    onSuccess: () => {
      toast(student === null ? "Talaba qo'shildi" : 'Saqlandi')
      onSaved()
      onClose()
    },
  })

  return (
    <Modal title={student === null ? "Talaba qo'shish" : 'Talabani tahrirlash'} onClose={onClose}>
      <form
        className="col"
        style={{ gap: 14 }}
        onSubmit={(event) => {
          event.preventDefault()
          save.mutate()
        }}
      >
        <Field label="F.I.Sh. *" error={fieldError(save.error, 'full_name')}>
          {(id, invalid) => <input id={id} className={`input ${invalid ? 'invalid' : ''}`} value={form.full_name} onChange={(event) => setForm({ ...form, full_name: event.target.value })} />}
        </Field>
        <Field label="Telefon" error={fieldError(save.error, 'phone')}>
          {(id, invalid) => <input id={id} className={`input ${invalid ? 'invalid' : ''}`} value={form.phone} onChange={(event) => setForm({ ...form, phone: event.target.value })} placeholder="+998" />}
        </Field>
        {student !== null && (
          <Field label="Guruh" help="Boshqa guruhga ko'chirish">
            {(id) => (
              <select id={id} className="select" value={form.study_group_id} onChange={(event) => setForm({ ...form, study_group_id: event.target.value })}>
                {groups.data?.map((group) => (
                  <option key={group.id} value={group.id}>
                    {group.name}
                  </option>
                ))}
              </select>
            )}
          </Field>
        )}
        {save.isError && !fieldError(save.error, 'full_name') && !fieldError(save.error, 'phone') && <div className="banner error">{errorMessage(save.error)}</div>}
        <div className="modal-actions">
          <button type="button" className="btn" onClick={onClose}>
            Bekor
          </button>
          <button type="submit" className="btn primary" disabled={save.isPending || form.full_name.trim() === ''}>
            Saqlash
          </button>
        </div>
      </form>
    </Modal>
  )
}
