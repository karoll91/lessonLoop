import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState, type FormEvent } from 'react'
import { Link } from 'react-router'
import { api, type Paginated } from '../api/client'
import type { Group } from '../api/types'
import { useAuth } from '../auth'
import { ConfirmDialog, EmptyState, ErrorState, errorMessage, Field, fieldError, Modal, Pager, TableSkeleton, useToast } from '../ui/components'
import { PlusIcon, SearchIcon } from '../ui/icons'

/** W3 — study groups: list, create, edit, archive, delete. */
export function GroupsPage() {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'
  const [search, setSearch] = useState('')
  const [archived, setArchived] = useState(false)
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<Group | 'new' | null>(null)
  const [deleting, setDeleting] = useState<Group | null>(null)
  const toast = useToast()
  const client = useQueryClient()

  const query = useQuery({
    queryKey: ['groups', { search, archived, page }],
    queryFn: () => api.get<Paginated<Group>>('/groups', { q: search, archived, page }),
  })

  const archive = useMutation({
    mutationFn: (group: Group) => api.patch(`/groups/${group.id}`, { archived: !group.archived }),
    onSuccess: (_, group) => {
      toast(group.archived ? `${group.name} arxivdan qaytarildi` : `${group.name} arxivlandi`)
      void client.invalidateQueries({ queryKey: ['groups'] })
    },
    onError: (error) => toast(errorMessage(error), 'error'),
  })

  const remove = useMutation({
    mutationFn: (group: Group) => api.delete(`/groups/${group.id}`),
    onSuccess: () => {
      toast("Guruh o'chirildi")
      setDeleting(null)
      void client.invalidateQueries({ queryKey: ['groups'] })
    },
  })

  return (
    <>
      <div className="page-head">
        <div className="col" style={{ gap: 4 }}>
          <h1 className="page-title">Guruhlar</h1>
          {query.data && <span className="muted">{query.data.meta.total} ta {archived ? 'arxivda' : 'faol'}</span>}
        </div>
        {isAdmin && (
          <button type="button" className="btn primary" onClick={() => setEditing('new')}>
            <PlusIcon />
            Yangi guruh
          </button>
        )}
      </div>

      <div className="filters">
        <label className="row input" style={{ width: 280, gap: 8 }}>
          <SearchIcon />
          <span className="visually-hidden">Qidirish</span>
          <input
            style={{ border: 0, outline: 0, background: 'transparent', flex: 1, minWidth: 0 }}
            placeholder="Guruh nomi"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setPage(1)
            }}
          />
        </label>
        <button type="button" className="fchip" aria-pressed={!archived} onClick={() => setArchived(false)}>
          Faol
        </button>
        <button type="button" className="fchip" aria-pressed={archived} onClick={() => setArchived(true)}>
          Arxiv
        </button>
      </div>

      <section className="card" style={{ overflow: 'hidden' }}>
        {query.isPending && <TableSkeleton />}
        {query.isError && <ErrorState error={query.error} onRetry={() => void query.refetch()} />}
        {query.data && query.data.data.length === 0 && (
          <EmptyState
            title={search ? `«${search}» topilmadi` : archived ? "Arxivda guruh yo'q" : "Hali guruh yo'q"}
            text={isAdmin && !search && !archived ? 'Birinchi guruhni yarating, keyin unga fan va talabalar qo\'shing.' : undefined}
            action={
              isAdmin && !search && !archived ? (
                <button type="button" className="btn primary" onClick={() => setEditing('new')}>
                  Yangi guruh
                </button>
              ) : undefined
            }
          />
        )}
        {query.data && query.data.data.length > 0 && (
          <>
            <div className="table-wrap">
              <table className="tb">
                <thead>
                  <tr>
                    <th>Guruh</th>
                    <th>Kurs</th>
                    <th>O'quv yili</th>
                    <th>Semestr</th>
                    <th>Talabalar</th>
                    {isAdmin && <th />}
                  </tr>
                </thead>
                <tbody>
                  {query.data.data.map((group) => (
                    <tr key={group.id}>
                      <td style={{ fontWeight: 800 }}>
                        <Link to={`/groups/${group.id}`}>{group.name}</Link>
                      </td>
                      <td>{group.course_year ?? '—'}</td>
                      <td>{group.academic_year ?? '—'}</td>
                      <td>{group.semester ?? '—'}</td>
                      <td>{group.students_count ?? 0}</td>
                      {isAdmin && (
                        <td>
                          <div className="row" style={{ justifyContent: 'flex-end' }}>
                            <button type="button" className="btn sm" onClick={() => setEditing(group)}>
                              Tahrirlash
                            </button>
                            <button type="button" className="btn sm" onClick={() => archive.mutate(group)}>
                              {group.archived ? 'Qaytarish' : 'Arxivlash'}
                            </button>
                            <button type="button" className="btn sm ghost" style={{ color: 'var(--danger)' }} onClick={() => setDeleting(group)}>
                              O'chirish
                            </button>
                          </div>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
            <Pager page={query.data.meta.current_page} lastPage={query.data.meta.last_page} total={query.data.meta.total} onPage={setPage} />
          </>
        )}
      </section>

      {editing !== null && <GroupForm group={editing === 'new' ? null : editing} onClose={() => setEditing(null)} />}
      {deleting !== null && (
        <ConfirmDialog
          title={`${deleting.name} guruhini o'chirasizmi?`}
          text="Guruh, uning talabalari va kurslari butunlay o'chadi. Dars tarixi bor guruhni faqat arxivlash mumkin."
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

function GroupForm({ group, onClose }: { group: Group | null; onClose: () => void }) {
  const [form, setForm] = useState({
    name: group?.name ?? '',
    course_year: group?.course_year?.toString() ?? '',
    academic_year: group?.academic_year ?? '2025-2026',
    semester: group?.semester?.toString() ?? '1',
  })
  const toast = useToast()
  const client = useQueryClient()

  const save = useMutation({
    mutationFn: () => {
      const body = {
        name: form.name.trim(),
        course_year: form.course_year === '' ? null : Number(form.course_year),
        academic_year: form.academic_year === '' ? null : form.academic_year,
        semester: form.semester === '' ? null : Number(form.semester),
      }
      return group === null ? api.post('/groups', body) : api.patch(`/groups/${group.id}`, body)
    },
    onSuccess: () => {
      toast(group === null ? 'Guruh yaratildi' : 'Guruh saqlandi')
      void client.invalidateQueries({ queryKey: ['groups'] })
      onClose()
    },
  })

  const submit = (event: FormEvent): void => {
    event.preventDefault()
    save.mutate()
  }

  return (
    <Modal title={group === null ? 'Yangi guruh' : `${group.name} — tahrirlash`} onClose={onClose}>
      <form className="col" style={{ gap: 14 }} onSubmit={submit}>
        <Field label="Guruh nomi *" error={fieldError(save.error, 'name')}>
          {(id, invalid) => <input id={id} className={`input ${invalid ? 'invalid' : ''}`} value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="KI-21" />}
        </Field>
        <div className="row" style={{ alignItems: 'flex-start' }}>
          <div className="grow">
            <Field label="Kurs" error={fieldError(save.error, 'course_year')}>
              {(id) => (
                <select id={id} className="select" value={form.course_year} onChange={(event) => setForm({ ...form, course_year: event.target.value })}>
                  <option value="">—</option>
                  {[1, 2, 3, 4, 5, 6].map((year) => (
                    <option key={year} value={year}>
                      {year}
                    </option>
                  ))}
                </select>
              )}
            </Field>
          </div>
          <div className="grow">
            <Field label="Semestr">
              {(id) => (
                <select id={id} className="select" value={form.semester} onChange={(event) => setForm({ ...form, semester: event.target.value })}>
                  <option value="1">1</option>
                  <option value="2">2</option>
                </select>
              )}
            </Field>
          </div>
        </div>
        <Field label="O'quv yili" help="Masalan: 2025-2026" error={fieldError(save.error, 'academic_year')}>
          {(id, invalid) => <input id={id} className={`input ${invalid ? 'invalid' : ''}`} value={form.academic_year} onChange={(event) => setForm({ ...form, academic_year: event.target.value })} />}
        </Field>
        {save.isError && !fieldError(save.error, 'name') && !fieldError(save.error, 'academic_year') && <div className="banner error">{errorMessage(save.error)}</div>}
        <div className="modal-actions">
          <button type="button" className="btn" onClick={onClose}>
            Bekor
          </button>
          <button type="submit" className="btn primary" disabled={save.isPending || form.name.trim() === ''}>
            {save.isPending && <span className="spinner" />}
            Saqlash
          </button>
        </div>
      </form>
    </Modal>
  )
}
