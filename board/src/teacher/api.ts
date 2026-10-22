import { ApiError } from '../api'
import { config } from '../config'
import type { BoardState } from '../types'

/**
 * API calls of teacher mode: the same endpoints the phone uses, with the
 * teacher's Sanctum token. Kept apart from the board's own (token-less) calls.
 */

export interface TeacherSession {
  token: string
  name: string
}

const SESSION_KEY = 'lessonloop.board.teacher'

export const session = {
  get(): TeacherSession | null {
    try {
      const raw = window.localStorage.getItem(SESSION_KEY)
      return raw === null ? null : (JSON.parse(raw) as TeacherSession)
    } catch {
      return null
    }
  },
  set(value: TeacherSession | null): void {
    try {
      if (value === null) {
        window.localStorage.removeItem(SESSION_KEY)
      } else {
        window.localStorage.setItem(SESSION_KEY, JSON.stringify(value))
      }
    } catch {
      // Private mode: the teacher signs in again after a reload.
    }
  },
}

/** A failed call with the server's flow code (no_students_left, ...). */
export class TeacherApiError extends ApiError {
  readonly code: string | null

  constructor(status: number, message: string, code: string | null) {
    super(status, message)
    this.code = code
  }
}

type Method = 'GET' | 'POST' | 'PATCH' | 'DELETE'

async function call<T>(token: string | null, method: Method, path: string, body?: unknown): Promise<T> {
  let response: Response
  try {
    response = await fetch(config.apiUrl + path, {
      method,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token === null ? {} : { Authorization: `Bearer ${token}` }),
      },
      body: body === undefined ? undefined : JSON.stringify(body),
    })
  } catch {
    throw new TeacherApiError(0, "Serverga ulanib bo'lmadi", null)
  }

  const json = (await response.json().catch(() => ({}))) as { message?: string; code?: string; errors?: Record<string, string[]> }
  if (!response.ok) {
    const firstError = json.errors ? Object.values(json.errors)[0]?.[0] : undefined
    throw new TeacherApiError(response.status, firstError ?? json.message ?? `HTTP ${response.status}`, json.code ?? null)
  }
  return json as T
}

/* ---------- shapes (backend Http/Resources) ---------- */

export interface Course {
  id: number
  study_group_id: number
  subject_id: number
  group?: { id: number; name: string }
  subject?: { id: number; name: string }
  lessons_count?: number
}

export interface Topic {
  id: number
  title: string
  position: number
  questions_count?: number
}

export interface Student {
  id: number
  full_name: string
}

export interface Answer {
  id: number
  status: 'pending' | 'graded' | 'no_answer' | 'absent'
}

export interface Lesson {
  id: number
  course_id: number
  sequence: number
  question_count: number
  status: 'active' | 'finished'
  group?: string | null
  subject?: string | null
  answers?: Answer[]
}

export interface Rules {
  data: Record<string, number>
}

/* ---------- calls ---------- */

export interface LoginResult {
  token: string
  user: { name: string; must_change_password: boolean }
}

export const login = (loginName: string, password: string): Promise<LoginResult> =>
  call<LoginResult>(null, 'POST', '/login', { login: loginName, password, device_name: 'board' })

export const teacherApi = (token: string) => ({
  logout: () => call<unknown>(token, 'POST', '/logout'),
  claim: async (boardToken: string) => (await call<{ state: BoardState }>(token, 'POST', '/boards/claim', { token: boardToken })).state,
  heartbeat: (boardId: number) => call<unknown>(token, 'POST', `/boards/${boardId}/heartbeat`),
  command: (boardId: number, command: string) => call<{ state: BoardState; answer_id: number | null }>(token, 'POST', `/boards/${boardId}/commands`, { command }),
  attach: (boardId: number, lessonId: number) => call<{ state: BoardState }>(token, 'POST', `/boards/${boardId}/lesson`, { lesson_id: lessonId }),
  options: (boardId: number, options: Record<string, boolean>) => call<{ state: BoardState }>(token, 'PATCH', `/boards/${boardId}/options`, options),
  courses: async () => (await call<{ data: Course[] }>(token, 'GET', '/courses?per_page=100')).data,
  topics: async (subjectId: number) => (await call<{ data: Topic[] }>(token, 'GET', `/subjects/${subjectId}/topics`)).data,
  students: async (groupId: number, courseId: number) => (await call<{ data: Student[] }>(token, 'GET', `/groups/${groupId}/students?course_id=${courseId}`)).data,
  activeLessons: async () => (await call<{ data: Lesson[] }>(token, 'GET', '/lessons?status=active&per_page=20')).data,
  lesson: async (lessonId: number) => (await call<{ data: Lesson }>(token, 'GET', `/lessons/${lessonId}`)).data,
  startLesson: async (courseId: number, body: { topic_ids: number[]; question_count: number; absent_student_ids: number[] }) =>
    (await call<{ data: Lesson }>(token, 'POST', `/courses/${courseId}/lessons`, body)).data,
  grade: (answerId: number, body: { score?: number; chosen_option?: string }) => call<unknown>(token, 'POST', `/answers/${answerId}/grade`, body),
  skip: (answerId: number, reason: 'no_answer' | 'absent') => call<unknown>(token, 'POST', `/answers/${answerId}/skip`, { reason }),
  rules: () => call<Rules>(token, 'GET', '/settings'),
})

export type TeacherApi = ReturnType<typeof teacherApi>
