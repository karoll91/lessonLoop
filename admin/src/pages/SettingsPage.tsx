import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useState } from 'react'
import { Link, useOutletContext } from 'react-router'
import { api } from '../api/client'
import type { Settings } from '../api/types'
import { useAuth } from '../auth'
import type { LayoutContext } from '../layout/AppLayout'
import { ConfirmDialog, ErrorState, errorMessage, TableSkeleton, useToast } from '../ui/components'

const rules: { key: string; title: string; text: string; unit: string }[] = [
  { key: 'pass_score', title: "O'tish bali", text: 'Shundan past baho olgan talaba qarzdor bo\'lib, random tanlovga qaytadi.', unit: 'ball' },
  { key: 'cooldown_lessons', title: 'Dam olish', text: "O'tgan talaba shuncha dars davomida qayta chiqmaydi.", unit: 'dars' },
  { key: 'debtor_weight', title: 'Qarzdor vazni', text: 'Qarzdor talabaning tanlanish ehtimoli necha barobar yuqori.', unit: '×' },
  { key: 'never_asked_weight', title: "So'ralmagan vazni", text: "Hali so'ralmagan talabaning tanlanish ehtimoli.", unit: '×' },
  { key: 'default_question_count', title: 'Savollar soni', text: 'Yangi darsda taklif qilinadigan savollar soni.', unit: 'ta' },
  { key: 'grade_edit_hours', title: 'Bahoni tuzatish muddati', text: "Baho qo'yilgandan keyin shuncha soat ichida o'zgartirish mumkin.", unit: 'soat' },
]

/** W11 — queue rules (admin edits, teachers read), appearance and account. */
export function SettingsPage() {
  const { user } = useAuth()
  const isAdmin = user?.role === 'admin'
  const { theme, toggleTheme } = useOutletContext<LayoutContext>()
  const [draft, setDraft] = useState<Record<string, number> | null>(null)
  const [confirmReset, setConfirmReset] = useState(false)
  const toast = useToast()
  const client = useQueryClient()

  const query = useQuery({ queryKey: ['settings'], queryFn: () => api.get<Settings>('/settings') })

  const save = useMutation({
    mutationFn: (values: Record<string, number>) => api.patch<Settings>('/settings', values),
    onSuccess: (settings) => {
      client.setQueryData(['settings'], settings)
      setDraft(null)
      toast('Qoidalar saqlandi. Keyingi tanlovdan boshlab amal qiladi.')
    },
  })

  const reset = useMutation({
    mutationFn: () => api.delete('/settings'),
    onSuccess: () => {
      setDraft(null)
      setConfirmReset(false)
      void client.invalidateQueries({ queryKey: ['settings'] })
      toast('Standart qiymatlar tiklandi')
    },
  })

  const values = draft ?? query.data?.data ?? {}
  const changed = draft !== null && Object.keys(draft).some((key) => draft[key] !== query.data?.data[key])

  const set = (key: string, value: number): void => {
    const limits = query.data?.limits[key]
    const clamped = limits ? Math.min(limits.max, Math.max(limits.min, value)) : value
    setDraft({ ...values, [key]: clamped })
  }

  return (
    <>
      <div className="page-head">
        <h1 className="page-title">Sozlamalar</h1>
      </div>

      <section className="card" style={{ overflow: 'hidden' }}>
        <div className="pad row" style={{ justifyContent: 'space-between', paddingBottom: 12 }}>
          <div className="col" style={{ gap: 2 }}>
            <h2 className="card-title">Random tanlov qoidalari</h2>
            {!isAdmin && <span className="small">Faqat admin o'zgartira oladi.</span>}
          </div>
          {isAdmin && (
            <button type="button" className="btn sm ghost" onClick={() => setConfirmReset(true)}>
              Standartga qaytarish
            </button>
          )}
        </div>
        {query.isPending && <TableSkeleton rows={6} />}
        {query.isError && <ErrorState error={query.error} onRetry={() => void query.refetch()} />}
        {query.data && (
          <div className="col" style={{ gap: 0 }}>
            {rules.map((rule) => {
              const limits = query.data.limits[rule.key]
              const value = values[rule.key] ?? 0
              const isDefault = value === query.data.defaults[rule.key]
              const inputId = `setting-${rule.key}`
              return (
                <div key={rule.key} className="setting-row">
                  <div className="col grow" style={{ gap: 2 }}>
                    <label htmlFor={inputId} style={{ fontWeight: 700 }}>
                      {rule.title}
                    </label>
                    <span className="small">
                      {rule.text} {!isDefault && `Standart: ${query.data.defaults[rule.key]}.`}
                    </span>
                  </div>
                  <div className="stepper">
                    <button type="button" className="icon-btn" aria-label={`${rule.title} — kamaytirish`} disabled={!isAdmin || value <= (limits?.min ?? 0)} onClick={() => set(rule.key, value - 1)}>
                      −
                    </button>
                    <input
                      id={inputId}
                      type="number"
                      className="input"
                      min={limits?.min}
                      max={limits?.max}
                      value={value}
                      readOnly={!isAdmin}
                      onChange={(event) => set(rule.key, Number(event.target.value))}
                    />
                    <button type="button" className="icon-btn" aria-label={`${rule.title} — oshirish`} disabled={!isAdmin || value >= (limits?.max ?? 100)} onClick={() => set(rule.key, value + 1)}>
                      +
                    </button>
                    <span className="small" style={{ width: 36 }}>
                      {rule.unit}
                    </span>
                  </div>
                </div>
              )
            })}
          </div>
        )}
        {save.isError && (
          <div className="pad" style={{ paddingTop: 0 }}>
            <div className="banner error">{errorMessage(save.error)}</div>
          </div>
        )}
        {isAdmin && changed && (
          <div className="pad row" style={{ justifyContent: 'flex-end', borderTop: '1px solid var(--line)' }}>
            <button type="button" className="btn" onClick={() => setDraft(null)}>
              Bekor qilish
            </button>
            <button type="button" className="btn primary" disabled={save.isPending} onClick={() => draft && save.mutate(draft)}>
              {save.isPending && <span className="spinner" />}
              Saqlash
            </button>
          </div>
        )}
      </section>

      <div className="split">
        <section className="card pad main-col col" style={{ gap: 12 }}>
          <h2 className="card-title">Ko'rinish</h2>
          <div className="row" style={{ justifyContent: 'space-between' }}>
            <span>Mavzu</span>
            <div className="seg" role="group" aria-label="Mavzu">
              <button type="button" aria-pressed={theme === 'light'} onClick={() => theme !== 'light' && toggleTheme()}>
                Yorug'
              </button>
              <button type="button" aria-pressed={theme === 'dark'} onClick={() => theme !== 'dark' && toggleTheme()}>
                Qorong'i
              </button>
            </div>
          </div>
          <span className="small">Doska ekrani o'z mavzusini telefondan oladi.</span>
        </section>
        <section className="card pad side-col col" style={{ gap: 12 }}>
          <h2 className="card-title">Hisob</h2>
          <span>
            {user?.name} <span className="muted">({user?.login})</span>
          </span>
          <Link className="btn" to="/change-password">
            Parolni o'zgartirish
          </Link>
        </section>
      </div>

      {confirmReset && (
        <ConfirmDialog
          title="Standart qoidalarga qaytarasizmi?"
          text="O'tish bali 60, dam olish 4 dars va boshqa qiymatlar boshlang'ich holatga qaytadi."
          confirmLabel="Qaytarish"
          busy={reset.isPending}
          error={reset.isError ? errorMessage(reset.error) : null}
          onConfirm={() => reset.mutate()}
          onClose={() => setConfirmReset(false)}
        />
      )}
    </>
  )
}
