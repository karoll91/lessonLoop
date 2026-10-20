import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { createBrowserRouter, RouterProvider } from 'react-router'
import { ApiError } from './api/client'
import { AuthProvider } from './auth'
import { AdminOnly, AppLayout } from './layout/AppLayout'
import { ChangePasswordPage } from './pages/ChangePasswordPage'
import { DashboardPage } from './pages/DashboardPage'
import { RouteError, NotFoundPage } from './pages/ErrorPages'
import { GroupDetailPage } from './pages/GroupDetailPage'
import { GroupsPage } from './pages/GroupsPage'
import { JournalPage } from './pages/JournalPage'
import { LessonDetailPage } from './pages/LessonDetailPage'
import { LessonsPage } from './pages/LessonsPage'
import { LoginPage } from './pages/LoginPage'
import { QuestionsPage } from './pages/QuestionsPage'
import { ReportsPage } from './pages/ReportsPage'
import { SettingsPage } from './pages/SettingsPage'
import { StudentsPage } from './pages/StudentsPage'
import { SubjectsPage } from './pages/SubjectsPage'
import { UsersPage } from './pages/UsersPage'
import { ToastProvider } from './ui/components'

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // 4xx answers will not change on retry; network and 5xx errors may.
      retry: (count, error) => !(error instanceof ApiError && error.status >= 400 && error.status < 500) && count < 2,
      refetchOnWindowFocus: false,
      staleTime: 15_000,
    },
  },
})

// basename: /admin when built for the classroom server, empty in dev.
const router = createBrowserRouter([
  { path: '/login', element: <LoginPage />, errorElement: <RouteError /> },
  { path: '/change-password', element: <ChangePasswordPage />, errorElement: <RouteError /> },
  {
    path: '/',
    element: <AppLayout />,
    errorElement: <RouteError />,
    children: [
      { index: true, element: <DashboardPage /> },
      { path: 'groups', element: <GroupsPage /> },
      { path: 'groups/:groupId', element: <GroupDetailPage /> },
      { path: 'subjects', element: <SubjectsPage /> },
      { path: 'students', element: <StudentsPage /> },
      { path: 'questions', element: <QuestionsPage /> },
      { path: 'lessons', element: <LessonsPage /> },
      { path: 'lessons/:lessonId', element: <LessonDetailPage /> },
      { path: 'journal', element: <JournalPage /> },
      { path: 'reports', element: <ReportsPage /> },
      {
        path: 'users',
        element: (
          <AdminOnly>
            <UsersPage />
          </AdminOnly>
        ),
      },
      { path: 'settings', element: <SettingsPage /> },
      { path: '*', element: <NotFoundPage /> },
    ],
  },
], { basename: import.meta.env.BASE_URL.replace(/\/$/, '') || '/' })

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <AuthProvider>
        <ToastProvider>
          <RouterProvider router={router} />
        </ToastProvider>
      </AuthProvider>
    </QueryClientProvider>
  )
}
