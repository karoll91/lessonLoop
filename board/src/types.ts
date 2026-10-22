/**
 * Shape of the board state sent by the backend (App\Services\BoardPresenter).
 * The board only ever receives what students are allowed to see.
 */

export type BoardScreen =
  | 'pairing'
  | 'lobby'
  | 'drawing'
  | 'question'
  | 'answer'
  | 'score'
  | 'summary'
  | 'blackout'
  | 'pause'

export interface BoardOptions {
  show_student_name: boolean
  show_score: boolean
  show_answer: boolean
  timer_seconds: number | null
  resume_screen?: string | null
}

export interface BoardLesson {
  id: number
  group: string
  subject: string
  sequence: number
  question_count: number
  asked_count: number
  present_count: number
  topics: string[]
}

export interface BoardQuestion {
  text: string
  type: 'open' | 'test'
  difficulty: 'easy' | 'medium' | 'hard'
  topic: string
  image_path: string | null
  options: { label: string; text: string }[]
}

export interface BoardCurrent {
  student: { full_name: string } | null
  question?: BoardQuestion
  correct?: {
    answer_text: string | null
    option_label: string | null
    chosen_option: string | null
  }
  result?: {
    score: number
    passed: boolean
    rest_lessons: number
  }
}

export interface BoardSummary {
  average: number | null
  passed_count: number
  retry_count: number
  results: { full_name: string; score: number | null; passed: boolean }[]
}

export interface BoardState {
  board_id: number
  name: string | null
  screen: BoardScreen
  is_paired: boolean
  /** The paired phone sent a heartbeat within controller_timeout seconds. */
  controller_online?: boolean
  controller_seen_seconds_ago?: number | null
  controller_timeout?: number
  options: BoardOptions
  pair_code?: string | null
  pair_code_expires_at?: string | null
  lesson?: BoardLesson
  current?: BoardCurrent
  summary?: BoardSummary
}

/**
 * 'polling': the WebSocket is down but the server answers, so the board keeps
 * up by asking every few seconds -- nothing to warn the class about.
 */
export type ConnectionStatus = 'connected' | 'polling' | 'disconnected' | 'connecting' | 'reconnecting' | 'failed'

/** The board gets updates (live or by asking): nothing to warn about. */
export const isOnline = (connection: ConnectionStatus): boolean => connection === 'connected' || connection === 'polling'
