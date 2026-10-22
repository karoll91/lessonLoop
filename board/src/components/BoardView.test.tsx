import { act, render, screen } from '@testing-library/react'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { config } from '../config'
import { boardState, openQuestion, testQuestion } from '../test/fixtures'
import type { BoardState, ConnectionStatus } from '../types'
import { BoardView } from './BoardView'

function show(state: BoardState, connection: ConnectionStatus = 'connected', controllerOnline = true) {
  return render(<BoardView state={state} connection={connection} controllerOnline={controllerOnline} theme="light" onToggleTheme={() => {}} />)
}

afterEach(() => {
  vi.useRealTimers()
})

describe('BoardView', () => {
  it('keeps the last screen and shows «Pult uzildi» when the phone stops sending heartbeats', () => {
    show(boardState({ screen: 'question', current: { student: { full_name: 'Akmal Karimov' }, question: openQuestion } }), 'connected', false)

    expect(screen.getByTestId('remote-lost')).toHaveTextContent('Pult uzildi')
    expect(screen.getByTestId('question-text')).toHaveTextContent(openQuestion.text)
    expect(screen.queryByText('Pult ulangan')).not.toBeInTheDocument()
  })

  it('does not show «Pult uzildi» while the phone is online or the board waits for pairing', () => {
    show(boardState({ screen: 'lobby' }))
    expect(screen.queryByTestId('remote-lost')).not.toBeInTheDocument()
    expect(screen.getByText('Pult ulangan')).toBeInTheDocument()
  })

  it('shows only the server warning when the board itself is offline', () => {
    show(boardState({ screen: 'lobby' }), 'reconnecting', false)

    expect(screen.getByText("Server bilan aloqa yo'q")).toBeInTheDocument()
    expect(screen.queryByTestId('remote-lost')).not.toBeInTheDocument()
  })

  it('does not cover the question while it follows the phone by polling (WebSocket down, server fine)', () => {
    show(boardState({ screen: 'question', current: { student: { full_name: 'Madina Yusupova' }, question: openQuestion } }), 'polling')

    expect(screen.queryByText("Server bilan aloqa yo'q")).not.toBeInTheDocument()
    expect(screen.queryByText("Aloqa yo'q")).not.toBeInTheDocument()
    expect(screen.getByText('Pult ulangan')).toBeInTheDocument()
    expect(screen.getByTestId('question-text')).toHaveTextContent(openQuestion.text)
  })

  it('shows the six-digit pairing code while waiting for the phone', () => {
    show(boardState({ screen: 'pairing', is_paired: false, lesson: undefined, pair_code: '482913', pair_code_expires_at: new Date(Date.now() + 120_000).toISOString() }))

    expect(screen.getByTestId('pair-code')).toHaveTextContent('482913')
    expect(screen.getByText('Pult kutilmoqda')).toBeInTheDocument()
  })

  it('asks the class to wait when the phone is paired but no lesson is chosen', () => {
    show(boardState({ screen: 'lobby', lesson: undefined }))

    expect(screen.getByText('Dars tanlanishini kuting')).toBeInTheDocument()
  })

  it('shows the lesson topics and counts in the lobby', () => {
    show(boardState({ screen: 'lobby' }))

    expect(screen.getByText('Jarayonlar · Oqimlar')).toBeInTheDocument()
    expect(screen.getByText('24')).toBeInTheDocument()
  })

  it('spins the drum first and reveals the drawn student afterwards', () => {
    vi.useFakeTimers()
    show(boardState({ screen: 'drawing', current: { student: { full_name: 'Akmal Karimov' } } }))

    expect(screen.queryByText('Akmal Karimov')).not.toBeInTheDocument()

    act(() => {
      vi.advanceTimersByTime(config.drawAnimationMs)
    })

    expect(screen.getByText('Akmal Karimov')).toBeInTheDocument()
  })

  it('shows the question without the correct answer', () => {
    show(boardState({ screen: 'question', current: { student: { full_name: 'Akmal Karimov' }, question: openQuestion } }))

    expect(screen.getByTestId('question-text')).toHaveTextContent(openQuestion.text)
    expect(screen.queryByTestId('correct-answer')).not.toBeInTheDocument()
    expect(screen.getByText('Javob beradi')).toBeInTheDocument()
  })

  it('counts down when the teacher turned the timer on', () => {
    vi.useFakeTimers()
    show(boardState({ screen: 'question', options: { show_student_name: true, show_score: true, show_answer: true, timer_seconds: 60 }, current: { student: { full_name: 'Akmal Karimov' }, question: openQuestion } }))

    act(() => {
      vi.advanceTimersByTime(18_000)
    })

    expect(screen.getByRole('timer')).toHaveTextContent('0:42')
  })

  it('reveals the model answer on the answer screen', () => {
    show(boardState({ screen: 'answer', current: { student: { full_name: 'Akmal Karimov' }, question: openQuestion, correct: { answer_text: "Jarayon o'z xotirasiga ega.", option_label: null, chosen_option: null } } }))

    expect(screen.getByTestId('correct-answer')).toHaveTextContent("Jarayon o'z xotirasiga ega.")
  })

  it('marks the correct option and the student choice of a test question', () => {
    show(boardState({ screen: 'answer', current: { student: { full_name: 'Sora Aliyeva' }, question: testQuestion, correct: { answer_text: null, option_label: 'C', chosen_option: 'B' } } }))

    expect(screen.getByText('fork()').closest('.option')).toHaveClass('ok')
    expect(screen.getByText('wait()').closest('.option')).toHaveClass('no')
    expect(screen.getByText('Talaba javobi')).toBeInTheDocument()
  })

  it('shows a passing score with the rest period', () => {
    show(boardState({ screen: 'score', current: { student: { full_name: 'Akmal Karimov' }, question: openQuestion, result: { score: 72, passed: true, rest_lessons: 3 } } }))

    expect(screen.getByTestId('score')).toHaveTextContent('72')
    expect(screen.getByText("O'tdi")).toBeInTheDocument()
    expect(screen.getByText('Keyingi 3 darsda savol berilmaydi')).toBeInTheDocument()
  })

  it('shows a failing score as a retry next lesson', () => {
    show(boardState({ screen: 'score', current: { student: { full_name: 'Sora Aliyeva' }, question: openQuestion, result: { score: 45, passed: false, rest_lessons: 0 } } }))

    expect(screen.getByText('Qoniqarsiz')).toBeInTheDocument()
    expect(screen.getByTestId('score')).toHaveClass('fail')
  })

  it('marks finished questions and the current one in the progress bar', () => {
    const { container } = show(boardState({ screen: 'question', current: { student: { full_name: 'Akmal Karimov' }, question: openQuestion } }))

    expect(container.querySelectorAll('.dots .done')).toHaveLength(3)
    expect(container.querySelectorAll('.dots .now')).toHaveLength(1)
    expect(screen.getByText('4 / 10')).toBeInTheDocument()
  })

  it('shows the lesson average and results at the end', () => {
    show(boardState({ screen: 'summary', summary: { average: 71, passed_count: 7, retry_count: 3, results: [{ full_name: 'Bobur Rahimov', score: 100, passed: true }] } }))

    expect(screen.getByTestId('average')).toHaveTextContent('71')
    expect(screen.getByText('Bobur Rahimov')).toBeInTheDocument()
  })

  it('hides everything on blackout', () => {
    show(boardState({ screen: 'blackout', current: { student: { full_name: 'Akmal Karimov' }, question: openQuestion } }))

    expect(screen.getByTestId('blackout')).toBeInTheDocument()
    expect(screen.queryByText(openQuestion.text)).not.toBeInTheDocument()
  })

  it('keeps the screen and warns when the connection drops', () => {
    show(boardState({ screen: 'question', current: { student: { full_name: 'Akmal Karimov' }, question: openQuestion } }), 'reconnecting')

    expect(screen.getByRole('alert')).toHaveTextContent("Server bilan aloqa yo'q")
    expect(screen.getByTestId('question-text')).toBeInTheDocument()
    expect(screen.getByText("Aloqa yo'q")).toBeInTheDocument()
  })
})
