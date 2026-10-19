import { screen, waitFor } from '@testing-library/react'
import userEvent from '@testing-library/user-event'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { ApiError } from '../api/client'
import type { Topic } from '../api/types'
import { LoginPage } from '../pages/LoginPage'
import { QuestionEditor } from '../pages/QuestionsPage'
import { errorMessage, fieldError } from '../ui/components'
import { ImportModal } from '../ui/ImportModal'
import { mockApi, renderPage } from './render'

afterEach(() => {
  vi.unstubAllGlobals()
  window.localStorage.clear()
})

describe('API errors', () => {
  it('shows the first validation message for 422 and the server message otherwise', () => {
    const invalid = new ApiError(422, 'The given data was invalid.', null, { login: ["Login yoki parol noto'g'ri."] })
    const inUse = new ApiError(409, "Guruhda baholar bor. Uni arxivlang.", 'resource_in_use')

    expect(errorMessage(invalid)).toBe("Login yoki parol noto'g'ri.")
    expect(fieldError(invalid, 'login')).toBe("Login yoki parol noto'g'ri.")
    expect(fieldError(invalid, 'password')).toBeUndefined()
    expect(errorMessage(inUse)).toBe('Guruhda baholar bor. Uni arxivlang.')
  })
})

describe('LoginPage', () => {
  it('shows the field error from the server and keeps the token unset', async () => {
    mockApi(() => [422, { message: 'invalid', errors: { login: ["Login yoki parol noto'g'ri."] } }])
    renderPage(<LoginPage />, '/login', '/login')

    await userEvent.type(screen.getByLabelText('Login'), 'admin')
    await userEvent.type(screen.getByLabelText('Parol'), 'wrong-pass')
    await userEvent.click(screen.getByRole('button', { name: /Kirish/ }))

    expect(await screen.findByRole('alert')).toHaveTextContent("Login yoki parol noto'g'ri.")
    expect(window.localStorage.getItem('lessonloop.admin.token')).toBeNull()
  })

  it('sends a user with a temporary password to the change-password page', async () => {
    mockApi(() => [200, { token: 'abc', user: { id: 1, name: 'Ali', login: 'ali', role: 'teacher', must_change_password: true } }])
    renderPage(<LoginPage />, '/login', '/login')

    await userEvent.type(screen.getByLabelText('Login'), 'ali')
    await userEvent.type(screen.getByLabelText('Parol'), 'Temp12345')
    await userEvent.click(screen.getByRole('button', { name: /Kirish/ }))

    expect(await screen.findByText('other page')).toBeInTheDocument()
    expect(window.localStorage.getItem('lessonloop.admin.token')).toBe('abc')
  })
})

describe('ImportModal', () => {
  it('previews a dry run, then imports the same text', async () => {
    const report = (dryRun: boolean) => ({
      dry_run: dryRun,
      created_count: 1,
      duplicate_count: 1,
      error_count: 1,
      created: [{ row: 1, full_name: 'Aliyev Vali' }],
      duplicates: [{ row: 2, value: 'Karimova Nodira' }],
      errors: [{ row: 3, value: 'A. K.', message: "Ism juda qisqa" }],
    })
    const calls = mockApi((call) => [call.body && (call.body as { dry_run: string }).dry_run === '1' ? 200 : 201, report((call.body as { dry_run: string }).dry_run === '1')])
    const onImported = vi.fn()
    renderPage(<ImportModal title="Import" endpoint="/groups/5/students/import" template="students" hint="" placeholder="" onClose={() => {}} onImported={onImported} />)

    await userEvent.click(screen.getByRole('button', { name: 'Matn' }))
    await userEvent.type(screen.getByLabelText('Import matni'), 'Aliyev Vali')
    await userEvent.click(screen.getByRole('button', { name: /Tekshirish/ }))

    expect(await screen.findByText("1 ta qo'shiladi")).toBeInTheDocument()
    expect(screen.getByText('Ism juda qisqa')).toBeInTheDocument()
    expect(screen.getByText(/Allaqachon bor/)).toBeInTheDocument()

    await userEvent.click(screen.getByRole('button', { name: /1 tasini import qilish/ }))

    await waitFor(() => expect(onImported).toHaveBeenCalledOnce())
    expect(calls.map((call) => [call.path, (call.body as { dry_run: string }).dry_run, (call.body as { text: string }).text])).toEqual([
      ['/groups/5/students/import', '1', 'Aliyev Vali'],
      ['/groups/5/students/import', '0', 'Aliyev Vali'],
    ])
  })
})

describe('QuestionEditor', () => {
  const topics: Topic[] = [{ id: 7, subject_id: 1, title: 'Jarayonlar', position: 1 }]

  it('sends only filled variants with the one marked correct', async () => {
    const calls = mockApi(() => [201, { data: { id: 1 } }])
    const onSaved = vi.fn()
    renderPage(<QuestionEditor question={null} topics={topics} defaultTopicId={7} onClose={() => {}} onSaved={onSaved} />)

    await userEvent.type(screen.getByLabelText('Savol matni *'), 'Jarayon nima?')
    await userEvent.click(screen.getByRole('button', { name: 'Test (A–D)' }))
    await userEvent.type(screen.getByLabelText('A varianti'), 'Fayl')
    await userEvent.type(screen.getByLabelText('B varianti'), 'Bajarilayotgan dastur')
    await userEvent.type(screen.getByLabelText('C varianti'), 'Drayver')
    await userEvent.click(screen.getByLabelText("B to'g'ri javob"))
    await userEvent.click(screen.getByRole('button', { name: 'Saqlash' }))

    await waitFor(() => expect(onSaved).toHaveBeenCalledOnce())
    expect(calls[0].path).toBe('/topics/7/questions')
    expect(calls[0].body).toMatchObject({
      type: 'test',
      text: 'Jarayon nima?',
      options: [
        { label: 'A', text: 'Fayl', is_correct: false },
        { label: 'B', text: 'Bajarilayotgan dastur', is_correct: true },
        { label: 'C', text: 'Drayver', is_correct: false },
      ],
    })
  })

  it('shows the options error returned by the server', async () => {
    mockApi(() => [422, { message: 'invalid', errors: { options: ["Aynan bitta variant to'g'ri deb belgilanishi kerak."] } }])
    renderPage(<QuestionEditor question={null} topics={topics} defaultTopicId={7} onClose={() => {}} onSaved={() => {}} />)

    await userEvent.type(screen.getByLabelText('Savol matni *'), 'Savol')
    await userEvent.click(screen.getByRole('button', { name: 'Test (A–D)' }))
    await userEvent.click(screen.getByRole('button', { name: 'Saqlash' }))

    expect(await screen.findByText("Aynan bitta variant to'g'ri deb belgilanishi kerak.")).toBeInTheDocument()
  })
})
