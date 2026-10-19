/** Shapes of the backend API resources (app/Http/Resources). */

export type Role = 'admin' | 'teacher'

export interface User {
  id: number
  name: string
  login: string
  email?: string | null
  role: Role
  blocked?: boolean
  must_change_password: boolean
  last_login_at?: string | null
  courses?: Course[]
}

export interface Group {
  id: number
  name: string
  course_year: number | null
  academic_year: string | null
  semester: number | null
  archived: boolean
  students_count?: number
  courses?: Course[]
}

export interface Subject {
  id: number
  name: string
  code: string | null
  topics_count?: number
  topics?: Topic[]
}

export interface Topic {
  id: number
  subject_id: number
  title: string
  position: number
  questions_count?: number
  questions_without_answer_count?: number
}

export interface Course {
  id: number
  study_group_id: number
  subject_id: number
  teacher_id: number | null
  group?: { id: number; name: string }
  subject?: { id: number; name: string; code: string | null }
  teacher?: { id: number; name: string } | null
  lessons_count?: number
}

export type QueueState = 'never_asked' | 'queue' | 'debtor' | 'resting'

export interface Student {
  id: number
  study_group_id: number
  full_name: string
  phone: string | null
  is_active: boolean
  queue?: {
    state: QueueState
    asked_count: number
    last_score: number | null
    last_sequence: number | null
    rest_lessons_left: number
  }
}

export type QuestionType = 'open' | 'test'
export type Difficulty = 'easy' | 'medium' | 'hard'

export interface QuestionOption {
  label: string
  text: string
  is_correct: boolean
}

export interface Question {
  id: number
  topic_id: number
  type: QuestionType
  difficulty: Difficulty
  text: string
  answer_text: string | null
  image_path: string | null
  has_answer: boolean
  options?: QuestionOption[]
  times_asked?: number
  average_score?: number | null
}

export type AnswerStatus = 'pending' | 'graded' | 'no_answer' | 'absent'

export interface Answer {
  id: number
  status: AnswerStatus
  score: number | null
  passed: boolean | null
  chosen_option: string | null
  comment: string | null
  pick_weight: number
  pick_reason: string | null
  graded_at: string | null
  editable_until: string | null
  student?: { id: number; full_name: string }
  question?: { id: number; type: QuestionType; difficulty: Difficulty; text: string; answer_text: string | null; options: QuestionOption[] }
  edits?: { old_score: number | null; new_score: number | null; old_status: string; reason: string; by: string | null; at: string | null }[]
}

export interface Lesson {
  id: number
  course_id: number
  sequence: number
  question_count: number
  status: 'active' | 'paused' | 'finished'
  started_at: string | null
  finished_at: string | null
  group?: string | null
  subject?: string | null
  asked_count?: number
  retry_count?: number
  average_score?: number | null
  topic_ids?: number[]
  answers?: Answer[]
}

export interface Dashboard {
  counts: { groups: number; students: number; questions: number; questions_without_answer: number; never_asked: number }
  running_lesson: DashboardLesson | null
  today_lessons: DashboardLesson[]
  debtors: { student_id: number; full_name: string; course_id: number; group: string; subject: string; last_score: number | null }[]
}

export interface DashboardLesson {
  id: number
  course_id: number
  group: string
  subject: string
  sequence: number
  status: string
  started_at: string | null
  question_count: number
  asked_count: number
  average_score: number | null
}

export interface Journal {
  course: { id: number; group: string; subject: string; subject_code: string | null }
  lessons: { id: number; sequence: number; date: string | null }[]
  rows: {
    student_id: number
    full_name: string
    is_active: boolean
    cells: { lesson_id: number; status: 'graded' | 'no_answer' | 'absent' | 'pending' | 'not_asked'; score: number | null }[]
    average: number | null
    asked_count: number
  }[]
}

export interface ImportReport {
  dry_run: boolean
  created_count: number
  duplicate_count: number
  error_count: number
  created: Record<string, unknown>[]
  duplicates: { row: number; value: string }[]
  errors: { row: number; value: string; message: string }[]
}

export interface Settings {
  data: Record<string, number>
  defaults: Record<string, number>
  limits: Record<string, { min: number; max: number }>
}

export interface Report {
  course: { id: number; group: string; subject: string }
  summary: { lessons: number; answers: number; average: number | null; pass_rate: number | null; debtors: number }
  coverage: { students: number; asked: number; never_asked: number }
  lessons: { id: number; sequence: number; date: string | null; answers: number; average: number | null; passed: number }[]
  distribution: { from: number; to: number; count: number }[]
  topics: { id: number; title: string; answers: number; average: number | null }[]
  hardest_questions: { id: number; text: string; topic: string; times_asked: number; average: number | null }[]
}
