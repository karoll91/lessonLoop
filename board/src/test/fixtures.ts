import type { BoardState } from '../types'

const lesson = {
  id: 7,
  group: 'KI-21',
  subject: 'Operatsion tizimlar',
  sequence: 5,
  question_count: 10,
  asked_count: 4,
  present_count: 24,
  topics: ['Jarayonlar', 'Oqimlar'],
}

const options = { show_student_name: true, show_score: true, show_answer: true, timer_seconds: null }

/** A board state as BoardPresenter sends it, with overrides for the case at hand. */
export function boardState(overrides: Partial<BoardState> = {}): BoardState {
  return {
    board_id: 1,
    name: '214-xona',
    screen: 'lobby',
    is_paired: true,
    options,
    lesson,
    ...overrides,
  }
}

export const openQuestion = {
  text: "Jarayon va oqim o'rtasidagi farq nimada?",
  type: 'open' as const,
  difficulty: 'medium' as const,
  topic: 'Jarayonlar',
  image_path: null,
  options: [],
}

export const testQuestion = {
  text: 'Qaysi chaqiruv yangi jarayon yaratadi?',
  type: 'test' as const,
  difficulty: 'easy' as const,
  topic: 'Oqimlar',
  image_path: null,
  options: [
    { label: 'A', text: 'exec()' },
    { label: 'B', text: 'wait()' },
    { label: 'C', text: 'fork()' },
    { label: 'D', text: 'kill()' },
  ],
}
