import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useParams } from 'react-router'
import { api } from '../api/client'
import { useSubjectOptions, useTeacherOptions } from '../api/queries'
import type { Course, Group } from '../api/types'
import { useAuth } from '../auth'
import { ConfirmDialog, EmptyState, ErrorState, errorMessage, Field, fieldError, Modal, TableSkeleton, useToast } from '../ui/components'
import { PlusIcon } from '../ui/icons'

/** W3 detail — a group with its courses (subject + teacher). */
export function GroupDetailPage() {
  const { groupId } = useParams()
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'
  const [assigning, setAssigning] = useState<Course | 'new' | null>(null)
  const [removing, setRemoving] = useState<Course | null>(null)
  const toast = useToast()
  const client = useQueryClient()

  const query = useQuery({
    queryKey: ['groups', Number(groupId)],
    queryFn: () => api.get<{ data: Group }>(`/groups/${groupId}`).then((response) => response.data),
  })

  const remove = useMutation({
    mutationFn: (course: Course) => api.delete(`/courses/${course.id}`),
    onSuccess: () => {
      toast('Fan guruhdan ajratildi')
      setRemoving(null)
      void client.invalidateQueries({ queryKey: ['groups'] })
      void client.invalidateQueries({ queryKey: ['courses'] })
    },
  })

  if (query.isPending) {
    return (
      <div className="card">
        <TableSkeleton />
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

  const group = query.data
  const courses = group.courses ?? []

  return (
    <>
      <div className="col" style={{ gap: 6 }}>
        <div className="crumb">
          <Link to="/groups">Guruhlar</Link>
          <span>/</span>
          <span>{group.name}</span>
        </div>
        <div className="page-head">
          <div className="col" style={{ gap: 4 }}>
            <h1 className="page-title">
              {group.name} {group.archived && <span className="chip">Arxivda</span>}
            </h1>
            <span className="muted">
              {group.course_year ? `${group.course_year}-kurs · ` : ''}
              {group.academic_year ?? ''} {group.semester ? `· ${group.semester}-semestr` : ''} · {group.students_count ?? 0} talaba
            </span>
          </div>
          <Link className="btn" to={`/students?group=${group.id}`}>
            Talabalar ro'yxati
          </Link>
        </div>
      </div>

      <section className="card" style={{ overflow: 'hidden' }}>
        <div className="pad row" style={{ justifyContent: 'space-between', paddingBottom: 12 }}>
          <h2 className="card-title">Fanlar va o'qituvchilar</h2>
          {isAdmin && (
            <button type="button" className="btn sm primary" onClick={() => setAssigning('new')}>
              <PlusIcon />
              Fan biriktirish
            </button>
          )}
        </div>
        {courses.length === 0 ? (
          <EmptyState title="Guruhga fan biriktirilmagan" text="Dars boshlash uchun fan va o'qituvchini biriktiring." />
        ) : (
          <div className="table-wrap">
            <table className="tb">
              <thead>
                <tr>
                  <th>Fan</th>
                  <th>O'qituvchi</th>
                  <th />
                </tr>
              </thead>
              <tbody>
                {courses.map((course) => (
                  <tr key={course.id}>
                    <td style={{ fontWeight: 700 }}>
                      {course.subject?.name} {course.subject?.code && <span className="small">{course.subject.code}</span>}
                    </td>
                    <td>{course.teacher?.name ?? <span className="chip warn">Biriktirilmagan</span>}</td>
                    <td>
                      <div className="row" style={{ justifyContent: 'flex-end' }}>
                        <Link className="btn sm" to={`/journal?course=${course.id}`}>
                          Jurnal
                        </Link>
                        {isAdmin && (
                          <>
                            <button type="button" className="btn sm" onClick={() => setAssigning(course)}>
                              O'qituvchini almashtirish
                            </button>
                            <button type="button" className="btn sm ghost" style={{ color: 'var(--danger)' }} onClick={() => setRemoving(course)}>
                              Ajratish
                            </button>
                          </>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </section>

      {assigning !== null && <CourseForm group={group} course={assigning === 'new' ? null : assigning} onClose={() => setAssigning(null)} />}
      {removing !== null && (
        <ConfirmDialog
          title={`${removing.subject?.name} fanini ajratasizmi?`}
          text="Darslari bo'lmagan kursni ajratish mumkin. Dars o'tilgan bo'lsa, o'qituvchini almashtiring."
          confirmLabel="Ajratish"
          danger
          busy={remove.isPending}
          error={remove.isError ? errorMessage(remove.error) : null}
          onConfirm={() => remove.mutate(removing)}
          onClose={() => {
            setRemoving(null)
            remove.reset()
          }}
        />
      )}
    </>
  )
}

function CourseForm({ group, course, onClose }: { group: Group; course: Course | null; onClose: () => void }) {
  const subjects = useSubjectOptions()
  const teachers = useTeacherOptions(true)
  const [subjectId, setSubjectId] = useState(course?.subject_id.toString() ?? '')
  const [teacherId, setTeacherId] = useState(course?.teacher_id?.toString() ?? '')
  const toast = useToast()
  const client = useQueryClient()

  const save = useMutation({
    mutationFn: () =>
      course === null
        ? api.post('/courses', { study_group_id: group.id, subject_id: Number(subjectId), teacher_id: teacherId === '' ? null : Number(teacherId) })
        : api.patch(`/courses/${course.id}`, { teacher_id: teacherId === '' ? null : Number(teacherId) }),
    onSuccess: () => {
      toast('Saqlandi')
      void client.invalidateQueries({ queryKey: ['groups'] })
      void client.invalidateQueries({ queryKey: ['courses'] })
      onClose()
    },
  })

  const taken = new Set((group.courses ?? []).map((existing) => existing.subject_id))

  return (
    <Modal title={course === null ? 'Fan biriktirish' : `${course.subject?.name} — o'qituvchi`} onClose={onClose}>
      <form
        className="col"
        style={{ gap: 14 }}
        onSubmit={(event) => {
          event.preventDefault()
          save.mutate()
        }}
      >
        {course === null && (
          <Field label="Fan *" error={fieldError(save.error, 'subject_id')}>
            {(id, invalid) => (
              <select id={id} className={`select ${invalid ? 'invalid' : ''}`} value={subjectId} onChange={(event) => setSubjectId(event.target.value)}>
                <option value="">Fanni tanlang</option>
                {subjects.data
                  ?.filter((subject) => !taken.has(subject.id))
                  .map((subject) => (
                    <option key={subject.id} value={subject.id}>
                      {subject.name}
                    </option>
                  ))}
              </select>
            )}
          </Field>
        )}
        <Field label="O'qituvchi" error={fieldError(save.error, 'teacher_id')}>
          {(id) => (
            <select id={id} className="select" value={teacherId} onChange={(event) => setTeacherId(event.target.value)}>
              <option value="">Keyinroq tanlanadi</option>
              {teachers.data?.map((teacher) => (
                <option key={teacher.id} value={teacher.id}>
                  {teacher.name} ({teacher.login})
                </option>
              ))}
            </select>
          )}
        </Field>
        {save.isError && !fieldError(save.error, 'subject_id') && <div className="banner error">{errorMessage(save.error)}</div>}
        <div className="modal-actions">
          <button type="button" className="btn" onClick={onClose}>
            Bekor
          </button>
          <button type="submit" className="btn primary" disabled={save.isPending || (course === null && subjectId === '')}>
            Saqlash
          </button>
        </div>
      </form>
    </Modal>
  )
}
