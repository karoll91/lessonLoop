import { useQuery } from '@tanstack/react-query'
import { api, type Paginated } from './client'
import type { Course, Group, Subject, Topic, User } from './types'

/** Groups for selects: everything the user may see, not archived. */
export function useGroupOptions() {
  return useQuery({
    queryKey: ['groups', 'options'],
    queryFn: () => api.get<Paginated<Group>>('/groups', { per_page: 100 }).then((response) => response.data),
  })
}

export function useSubjectOptions() {
  return useQuery({
    queryKey: ['subjects', 'options'],
    queryFn: () => api.get<Paginated<Subject>>('/subjects', { per_page: 100 }).then((response) => response.data),
  })
}

export function useCourseOptions(groupId?: number | null) {
  return useQuery({
    queryKey: ['courses', 'options', groupId ?? 'all'],
    queryFn: () => api.get<Paginated<Course>>('/courses', { per_page: 100, study_group_id: groupId }).then((response) => response.data),
  })
}

export function useTopics(subjectId: number | null) {
  return useQuery({
    queryKey: ['topics', subjectId],
    queryFn: () => api.get<{ data: Topic[] }>(`/subjects/${subjectId}/topics`).then((response) => response.data),
    enabled: subjectId !== null,
  })
}

/** Teachers and admins, for assigning courses (admin only). */
export function useTeacherOptions(enabled: boolean) {
  return useQuery({
    queryKey: ['users', 'options'],
    queryFn: () => api.get<Paginated<User>>('/users', { per_page: 100 }).then((response) => response.data.filter((user) => !user.blocked)),
    enabled,
  })
}

/** "KI-21 · Operatsion tizimlar" label of a course. */
export function courseLabel(course: Course): string {
  return `${course.group?.name ?? '?'} · ${course.subject?.name ?? '?'}`
}
