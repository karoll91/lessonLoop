import { useCallback, useEffect, useMemo, useState } from 'react'
import { login, session, teacherApi, TeacherApiError, type TeacherApi, type TeacherSession } from './api'

/** Same rhythm as the phone: the board shows «Pult uzildi» after 45 s. */
const HEARTBEAT_MS = 15_000

export interface UseTeacher {
  teacher: TeacherSession | null
  api: TeacherApi | null
  signIn: (loginName: string, password: string) => Promise<void>
  signOut: () => Promise<void>
}

/**
 * Teacher mode on the board: the teacher signs in here, takes control of this
 * board (by its secret token) and keeps it alive with heartbeats, exactly as
 * the phone does. The session survives a reload of the board page.
 */
export function useTeacher(boardToken: string | null, boardId: number | null): UseTeacher {
  const [teacher, setTeacher] = useState<TeacherSession | null>(() => session.get())
  const api = useMemo(() => (teacher === null ? null : teacherApi(teacher.token)), [teacher])

  const forget = useCallback(() => {
    session.set(null)
    setTeacher(null)
  }, [])

  const signIn = useCallback(
    async (loginName: string, password: string) => {
      const result = await login(loginName.trim(), password)
      if (result.user.must_change_password) {
        await teacherApi(result.token).logout().catch(() => undefined)
        throw new TeacherApiError(422, "Avval telefonda yoki admin panelda vaqtinchalik parolni almashtiring.", null)
      }
      if (boardToken !== null) {
        await teacherApi(result.token).claim(boardToken)
      }
      const value = { token: result.token, name: result.user.name }
      session.set(value)
      setTeacher(value)
    },
    [boardToken],
  )

  const signOut = useCallback(async () => {
    await api?.logout().catch(() => undefined)
    forget()
  }, [api, forget])

  // After a reload (or a new board session) take the board again.
  useEffect(() => {
    if (api === null || boardToken === null) {
      return
    }
    api.claim(boardToken).catch((error: unknown) => {
      if (error instanceof TeacherApiError && error.status === 401) {
        forget()
      }
    })
  }, [api, boardToken, forget])

  useEffect(() => {
    if (api === null || boardId === null) {
      return
    }
    const timer = window.setInterval(() => {
      api.heartbeat(boardId).catch((error: unknown) => {
        // 401: signed out elsewhere; 403: another teacher took the board.
        if (error instanceof TeacherApiError && (error.status === 401 || error.status === 403)) {
          forget()
        }
      })
    }, HEARTBEAT_MS)
    return () => window.clearInterval(timer)
  }, [api, boardId, forget])

  return { teacher, api, signIn, signOut }
}
