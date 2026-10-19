import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { api, type Paginated } from '../api/client'
import { courseLabel } from '../api/queries'
import type { Role, User } from '../api/types'
import { useAuth } from '../auth'
import { ConfirmDialog, EmptyState, ErrorState, errorMessage, Field, fieldError, formatDateTime, Modal, Pager, TableSkeleton, useToast } from '../ui/components'
import { PlusIcon, SearchIcon } from '../ui/icons'

/** W10 — admins and teachers: create, block, reset password (admin only). */
export function UsersPage() {
  const { user: me } = useAuth()
  const [search, setSearch] = useState('')
  const [role, setRole] = useState<Role | ''>('')
  const [page, setPage] = useState(1)
  const [editing, setEditing] = useState<User | 'new' | null>(null)
  const [resetting, setResetting] = useState<User | null>(null)
  const [deleting, setDeleting] = useState<User | null>(null)
  const [password, setPassword] = useState<{ user: string; login: string; password: string } | null>(null)
  const toast = useToast()
  const client = useQueryClient()

  const list = useQuery({
    queryKey: ['users', { search, role, page }],
    queryFn: () => api.get<Paginated<User>>('/users', { q: search, role, page }),
  })

  const refresh = (): void => void client.invalidateQueries({ queryKey: ['users'] })

  const block = useMutation({
    mutationFn: (user: User) => api.patch(`/users/${user.id}`, { blocked: !user.blocked }),
    onSuccess: (_, user) => {
      toast(user.blocked ? `${user.name} blokdan chiqarildi` : `${user.name} bloklandi va barcha qurilmalardan chiqarildi`)
      refresh()
    },
    onError: (error) => toast(errorMessage(error), 'error'),
  })

  const reset = useMutation({
    mutationFn: (user: User) => api.post<{ temporary_password: string }>(`/users/${user.id}/reset-password`),
    onSuccess: (response, user) => {
      setResetting(null)
      setPassword({ user: user.name, login: user.login, password: response.temporary_password })
      refresh()
    },
  })

  const remove = useMutation({
    mutationFn: (user: User) => api.delete(`/users/${user.id}`),
    onSuccess: () => {
      toast("Foydalanuvchi o'chirildi")
      setDeleting(null)
      refresh()
    },
  })

  const users = list.data?.data ?? []

  return (
    <>
      <div className="page-head">
        <div className="col" style={{ gap: 4 }}>
          <h1 className="page-title">Foydalanuvchilar</h1>
          {list.data && <span className="muted">{list.data.meta.total} ta hisob</span>}
        </div>
        <button type="button" className="btn primary" onClick={() => setEditing('new')}>
          <PlusIcon />
          Yangi foydalanuvchi
        </button>
      </div>

      <div className="filters">
        <label className="row input" style={{ width: 260, gap: 8 }}>
          <SearchIcon />
          <span className="visually-hidden">Qidirish</span>
          <input
            style={{ border: 0, outline: 0, background: 'transparent', flex: 1, minWidth: 0 }}
            placeholder="Ism yoki login"
            value={search}
            onChange={(event) => {
              setSearch(event.target.value)
              setPage(1)
            }}
          />
        </label>
        {(['', 'teacher', 'admin'] as const).map((value) => (
          <button key={value} type="button" className="fchip" aria-pressed={role === value} onClick={() => (setRole(value), setPage(1))}>
            {value === '' ? 'Hammasi' : value === 'admin' ? 'Adminlar' : "O'qituvchilar"}
          </button>
        ))}
      </div>

      <section className="card" style={{ overflow: 'hidden' }}>
        {list.isPending && <TableSkeleton />}
        {list.isError && <ErrorState error={list.error} onRetry={() => void list.refetch()} />}
        {list.data && users.length === 0 && <EmptyState title="Foydalanuvchi topilmadi" />}
        {users.length > 0 && list.data && (
          <>
            <div className="table-wrap">
              <table className="tb">
                <thead>
                  <tr>
                    <th>Ism</th>
                    <th>Login</th>
                    <th>Rol</th>
                    <th>Kurslar</th>
                    <th>Oxirgi kirish</th>
                    <th />
                  </tr>
                </thead>
                <tbody>
                  {users.map((user) => (
                    <tr key={user.id} style={user.blocked ? { opacity: 0.65 } : undefined}>
                      <td style={{ fontWeight: 700 }}>
                        {user.name}
                        {user.blocked && (
                          <span className="chip danger" style={{ marginLeft: 8 }}>
                            Bloklangan
                          </span>
                        )}
                        {user.must_change_password && !user.blocked && (
                          <span className="chip warn" style={{ marginLeft: 8 }}>
                            Vaqtinchalik parol
                          </span>
                        )}
                      </td>
                      <td className="muted">{user.login}</td>
                      <td>
                        <span className={`chip ${user.role === 'admin' ? 'accent' : ''}`}>{user.role === 'admin' ? 'Admin' : "O'qituvchi"}</span>
                      </td>
                      <td className="small" style={{ maxWidth: 260 }}>
                        {user.courses && user.courses.length > 0 ? user.courses.map(courseLabel).join(', ') : '—'}
                      </td>
                      <td className="small">{user.last_login_at ? formatDateTime(user.last_login_at) : 'Kirmagan'}</td>
                      <td>
                        <div className="row" style={{ justifyContent: 'flex-end' }}>
                          <button type="button" className="btn sm" onClick={() => setEditing(user)}>
                            Tahrirlash
                          </button>
                          <button type="button" className="btn sm" onClick={() => setResetting(user)}>
                            Parolni tiklash
                          </button>
                          {user.id !== me?.id && (
                            <>
                              <button type="button" className="btn sm" onClick={() => block.mutate(user)}>
                                {user.blocked ? 'Blokdan chiqarish' : 'Bloklash'}
                              </button>
                              <button type="button" className="btn sm ghost" style={{ color: 'var(--danger)' }} onClick={() => setDeleting(user)}>
                                O'chirish
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
            <Pager page={list.data.meta.current_page} lastPage={list.data.meta.last_page} total={list.data.meta.total} onPage={setPage} />
          </>
        )}
      </section>

      {editing !== null && (
        <UserForm
          user={editing === 'new' ? null : editing}
          onClose={() => setEditing(null)}
          onCreated={(user, temporary) => temporary && setPassword({ user: user.name, login: user.login, password: temporary })}
          onSaved={refresh}
        />
      )}
      {resetting !== null && (
        <ConfirmDialog
          title={`${resetting.name} parolini tiklaysizmi?`}
          text="Yangi vaqtinchalik parol yaratiladi va foydalanuvchi barcha qurilmalardan chiqariladi. Kirgach o'z parolini o'rnatadi."
          confirmLabel="Tiklash"
          busy={reset.isPending}
          error={reset.isError ? errorMessage(reset.error) : null}
          onConfirm={() => reset.mutate(resetting)}
          onClose={() => {
            setResetting(null)
            reset.reset()
          }}
        />
      )}
      {deleting !== null && (
        <ConfirmDialog
          title={`${deleting.name}ni o'chirasizmi?`}
          text="Dars o'tgan hisobni o'chirib bo'lmaydi — uni bloklang."
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
      {password !== null && <TemporaryPassword {...password} onClose={() => setPassword(null)} />}
    </>
  )
}

/** The temporary password is shown only once; it is not stored anywhere. */
function TemporaryPassword({ user, login, password, onClose }: { user: string; login: string; password: string; onClose: () => void }) {
  const [copied, setCopied] = useState(false)

  const copy = async (): Promise<void> => {
    try {
      await navigator.clipboard.writeText(`Login: ${login}\nParol: ${password}`)
      setCopied(true)
    } catch {
      setCopied(false)
    }
  }

  return (
    <Modal title={`${user} — vaqtinchalik parol`} onClose={onClose}>
      <div className="banner warn">Parol faqat hozir ko'rsatiladi. Uni foydalanuvchiga bering — birinchi kirishda o'zgartiradi.</div>
      <div className="secret">
        <div>
          <span className="small">Login</span>
          <div className="mono">{login}</div>
        </div>
        <div>
          <span className="small">Parol</span>
          <div className="mono">{password}</div>
        </div>
      </div>
      <div className="modal-actions">
        <button type="button" className="btn" onClick={() => void copy()}>
          {copied ? 'Nusxalandi' : 'Nusxalash'}
        </button>
        <button type="button" className="btn primary" onClick={onClose}>
          Tayyor
        </button>
      </div>
    </Modal>
  )
}

interface SavedUser {
  data: User
  temporary_password?: string | null
}

function UserForm({ user, onClose, onCreated, onSaved }: { user: User | null; onClose: () => void; onCreated: (user: User, temporary: string | null) => void; onSaved: () => void }) {
  const [form, setForm] = useState({ name: user?.name ?? '', login: user?.login ?? '', email: user?.email ?? '', role: user?.role ?? ('teacher' as Role) })
  const toast = useToast()

  const save = useMutation({
    mutationFn: () => {
      const body = { name: form.name.trim(), login: form.login.trim(), email: form.email.trim() === '' ? null : form.email.trim(), role: form.role }
      return user === null ? api.post<SavedUser>('/users', body) : api.patch<SavedUser>(`/users/${user.id}`, body)
    },
    onSuccess: (response) => {
      toast(user === null ? 'Hisob yaratildi' : 'Saqlandi')
      onSaved()
      onClose()
      if (user === null) {
        onCreated(response.data, response.temporary_password ?? null)
      }
    },
  })

  const known = ['name', 'login', 'email', 'role']

  return (
    <Modal title={user === null ? 'Yangi foydalanuvchi' : `${user.name} — tahrirlash`} onClose={onClose}>
      <form
        className="col"
        style={{ gap: 14 }}
        onSubmit={(event) => {
          event.preventDefault()
          save.mutate()
        }}
      >
        <Field label="F.I.Sh. *" error={fieldError(save.error, 'name')}>
          {(id, invalid) => <input id={id} className={`input ${invalid ? 'invalid' : ''}`} value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} />}
        </Field>
        <Field label="Login *" help="Kichik lotin harflari, raqam, . _ -" error={fieldError(save.error, 'login')}>
          {(id, invalid) => <input id={id} className={`input ${invalid ? 'invalid' : ''}`} autoComplete="off" value={form.login} onChange={(event) => setForm({ ...form, login: event.target.value.toLowerCase() })} />}
        </Field>
        <Field label="Email" error={fieldError(save.error, 'email')}>
          {(id, invalid) => <input id={id} type="email" className={`input ${invalid ? 'invalid' : ''}`} value={form.email} onChange={(event) => setForm({ ...form, email: event.target.value })} />}
        </Field>
        <div className="field">
          <span className="label">Rol</span>
          <div className="seg" role="group" aria-label="Rol">
            <button type="button" aria-pressed={form.role === 'teacher'} onClick={() => setForm({ ...form, role: 'teacher' })}>
              O'qituvchi
            </button>
            <button type="button" aria-pressed={form.role === 'admin'} onClick={() => setForm({ ...form, role: 'admin' })}>
              Admin
            </button>
          </div>
        </div>
        {user === null && <span className="small">Saqlagach vaqtinchalik parol bir marta ko'rsatiladi.</span>}
        {save.isError && !known.some((key) => fieldError(save.error, key)) && <div className="banner error">{errorMessage(save.error)}</div>}
        <div className="modal-actions">
          <button type="button" className="btn" onClick={onClose}>
            Bekor
          </button>
          <button type="submit" className="btn primary" disabled={save.isPending || form.name.trim() === '' || form.login.trim() === ''}>
            Saqlash
          </button>
        </div>
      </form>
    </Modal>
  )
}
