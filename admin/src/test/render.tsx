import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { render } from '@testing-library/react'
import type { ReactNode } from 'react'
import { createMemoryRouter, RouterProvider } from 'react-router'
import { vi } from 'vitest'
import { AuthProvider } from '../auth'
import { ToastProvider } from '../ui/components'

export interface Call {
  method: string
  path: string
  body: unknown
}

/**
 * Replace fetch with a router of canned answers; returns the list of calls.
 * A handler returns [status, json].
 */
export function mockApi(handler: (call: Call) => [number, unknown]): Call[] {
  const calls: Call[] = []
  vi.stubGlobal(
    'fetch',
    vi.fn(async (url: string, init?: RequestInit) => {
      const path = new URL(url).pathname.replace(/^\/api/, '')
      const body = init?.body instanceof FormData ? Object.fromEntries(init.body.entries()) : init?.body ? JSON.parse(String(init.body)) : undefined
      const call = { method: init?.method ?? 'GET', path, body }
      calls.push(call)
      const [status, json] = handler(call)
      return new Response(status === 204 ? null : JSON.stringify(json), { status, headers: { 'Content-Type': 'application/json' } })
    }),
  )
  return calls
}

/** Render a page with the app providers at a given address. */
export function renderPage(element: ReactNode, path = '/', route = '/') {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
  const router = createMemoryRouter(
    [
      { path: route, element },
      { path: '*', element: <div>other page</div> },
    ],
    { initialEntries: [path] },
  )

  return render(
    <QueryClientProvider client={client}>
      <AuthProvider>
        <ToastProvider>
          <RouterProvider router={router} />
        </ToastProvider>
      </AuthProvider>
    </QueryClientProvider>,
  )
}
