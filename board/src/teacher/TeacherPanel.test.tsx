import { act, fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { config } from '../config'
import { boardState, openQuestion, testQuestion } from '../test/fixtures'
import type { BoardState } from '../types'
import type { TeacherApi } from './api'
import { TeacherLogin, TeacherPanel } from './TeacherPanel'

function fakeApi(overrides: Partial<Record<keyof TeacherApi, unknown>> = {}): TeacherApi {
  const api = {
    logout: vi.fn().mockResolvedValue({}),
    claim: vi.fn().mockResolvedValue(boardState()),
    heartbeat: vi.fn().mockResolvedValue({}),
    command: vi.fn().mockResolvedValue({ state: boardState(), answer_id: 41 }),
    attach: vi.fn().mockResolvedValue({ state: boardState() }),
    options: vi.fn().mockResolvedValue({ state: boardState() }),
    courses: vi.fn().mockResolvedValue([{ id: 3, study_group_id: 5, subject_id: 7, group: { id: 5, name: 'KI-21' }, subject: { id: 7, name: 'Operatsion tizimlar' } }]),
    topics: vi.fn().mockResolvedValue([{ id: 11, title: 'Jarayonlar', position: 1 }]),
    students: vi.fn().mockResolvedValue([{ id: 21, full_name: 'Madina Yusupova' }]),
    activeLessons: vi.fn().mockResolvedValue([]),
    lesson: vi.fn().mockResolvedValue({ id: 7, answers: [{ id: 41, status: 'pending' }] }),
    startLesson: vi.fn().mockResolvedValue({ id: 9 }),
    grade: vi.fn().mockResolvedValue({}),
    skip: vi.fn().mockResolvedValue({}),
    rules: vi.fn().mockResolvedValue({ data: { default_question_count: 6 } }),
    phoneCode: vi.fn().mockResolvedValue('482913'),
    ...overrides,
  }
  return api as unknown as TeacherApi
}

function panel(state: BoardState, api: TeacherApi) {
  return render(<TeacherPanel state={state} api={api} name="Shoxrux" onSignOut={() => {}} />)
}

afterEach(() => {
  vi.useRealTimers()
})

describe('TeacherPanel', () => {
  it('shows a code for the phone mid-lesson and closes it once the phone has paired', async () => {
    const api = fakeApi()
    const lesson = { screen: 'question' as const, current: { student: { full_name: 'Madina Yusupova' }, question: openQuestion } }
    const view = panel(boardState(lesson), api)

    fireEvent.click(screen.getByText('Telefonni ulash'))
    expect(await screen.findByTestId('phone-code')).toHaveTextContent('482 913')
    expect(api.phoneCode).toHaveBeenCalledWith(1)

    // The board's state carries the code, then loses it when the phone pairs.
    view.rerender(<TeacherPanel state={boardState({ ...lesson, pair_code: '482913' })} api={api} name="Shoxrux" onSignOut={() => {}} />)
    expect(screen.getByTestId('phone-code')).toBeInTheDocument()
    view.rerender(<TeacherPanel state={boardState(lesson)} api={api} name="Shoxrux" onSignOut={() => {}} />)
    await waitFor(() => expect(screen.queryByTestId('phone-code')).not.toBeInTheDocument())
  })

  it('Random draws a student and then puts the question on the board', async () => {
    vi.useFakeTimers()
    const api = fakeApi()
    panel(boardState({ screen: 'lobby' }), api)

    fireEvent.click(screen.getByText('Random — keyingi talaba'))
    await act(async () => {
      await vi.advanceTimersByTimeAsync(config.drawAnimationMs + 10)
    })

    expect(api.command).toHaveBeenNthCalledWith(1, 1, 'draw')
    expect(api.command).toHaveBeenNthCalledWith(2, 1, 'show_question')
  })

  it('grades an open question with the chosen score and shows it on the board', async () => {
    const api = fakeApi()
    panel(boardState({ screen: 'question', current: { student: { full_name: 'Madina Yusupova' }, question: openQuestion } }), api)
    fireEvent.click(await screen.findByText('75'))
    fireEvent.click(screen.getByText("75 ball qo'yish"))

    await waitFor(() => expect(api.command).toHaveBeenCalledWith(1, 'show_score'))
    expect(api.grade).toHaveBeenCalledWith(41, { score: 75 })
  })

  it('grades a test question by the option the student named, without showing which one is right', async () => {
    const api = fakeApi()
    panel(boardState({ screen: 'question', current: { student: { full_name: 'Madina Yusupova' }, question: testQuestion } }), api)
    const optionC = await screen.findByRole('button', { name: 'C' })

    expect(screen.queryByText(/to'g'ri variant/i)).not.toBeInTheDocument()
    fireEvent.click(optionC)

    await waitFor(() => expect(api.grade).toHaveBeenCalledWith(41, { chosen_option: 'C' }))
  })

  it('a student who did not answer is skipped and the board goes back to the lobby', async () => {
    const api = fakeApi()
    panel(boardState({ screen: 'question', current: { student: { full_name: 'Madina Yusupova' }, question: openQuestion } }), api)
    fireEvent.click(await screen.findByText('Javob bermadi'))

    await waitFor(() => expect(api.command).toHaveBeenCalledWith(1, 'show_lobby'))
    expect(api.skip).toHaveBeenCalledWith(41, 'no_answer')
  })

  it('offers to finish when nobody is left to ask', async () => {
    const error = Object.assign(new Error("Bu darsda so'raladigan talaba qolmadi."), { status: 422, code: 'no_students_left' })
    const { TeacherApiError } = await import('./api')
    Object.setPrototypeOf(error, TeacherApiError.prototype)
    const api = fakeApi({ command: vi.fn().mockRejectedValue(error) })
    panel(boardState({ screen: 'lobby' }), api)

    fireEvent.click(screen.getByText('Random — keyingi talaba'))

    expect(await screen.findByText("Bu darsda so'raladigan talaba qolmadi.")).toBeInTheDocument()
    expect(screen.getAllByText('Darsni yakunlash').length).toBeGreaterThan(1)
  })

  it('on a black screen the way back is always there: with no lesson, with the panel folded, and with Esc', async () => {
    const api = fakeApi()
    panel(boardState({ screen: 'blackout', lesson: undefined }), api)

    expect(screen.getByText("Qora ekran yoqilgan — talabalar hech narsani ko'rmaydi")).toBeInTheDocument()
    fireEvent.click(screen.getByText('Yashirish ▾'))
    fireEvent.click(screen.getByText('Davom ettirish (Esc)'))
    await waitFor(() => expect(api.command).toHaveBeenCalledWith(1, 'resume'))

    fireEvent.keyDown(window, { key: 'Escape' })
    await waitFor(() => expect(api.command).toHaveBeenCalledTimes(2))
  })

  it('without a lesson on the board it starts a new one and puts it on the board', async () => {
    const api = fakeApi()
    panel(boardState({ screen: 'lobby', lesson: undefined }), api)

    fireEvent.click(await screen.findByText('KI-21 · Operatsion tizimlar'))
    fireEvent.click(await screen.findByText('1. Jarayonlar'))
    fireEvent.click(screen.getByText('Madina Yusupova'))
    fireEvent.click(screen.getByText('Darsni boshlash'))

    await waitFor(() => expect(api.attach).toHaveBeenCalledWith(1, 9))
    expect(api.startLesson).toHaveBeenCalledWith(3, { topic_ids: [11], question_count: 6, absent_student_ids: [21] })
  })
})

describe('TeacherLogin', () => {
  it('signs in with the typed login and password and shows a refusal', async () => {
    const onSignIn = vi.fn().mockRejectedValue(new Error("Login yoki parol noto'g'ri."))
    render(<TeacherLogin onSignIn={onSignIn} onClose={() => {}} />)

    fireEvent.change(screen.getByPlaceholderText('Login'), { target: { value: 'shoxrux' } })
    fireEvent.change(screen.getByPlaceholderText('Parol'), { target: { value: 'secret-1' } })
    fireEvent.click(screen.getByText('Kirish'))

    expect(await screen.findByText("Login yoki parol noto'g'ri.")).toBeInTheDocument()
    expect(onSignIn).toHaveBeenCalledWith('shoxrux', 'secret-1')
  })
})
