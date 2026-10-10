import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { lazy, Suspense } from 'react'
import { BrowserRouter, Route, Routes } from 'react-router-dom'
import { DashboardPage } from './features/dashboard/DashboardPage'
import { ApiError } from './services/api/client'

// The print report is its own chunk: it is opened in a separate tab and is not part of the dashboard's first load.
const ReportPage = lazy(() => import('./features/report/ReportPage').then((m) => ({ default: m.ReportPage })))

const queryClient = new QueryClient({
  defaultOptions: {
    queries: {
      // Client errors (e.g. an invalid filter) will not fix themselves; only retry network/server failures.
      retry: (count, error) => !(error instanceof ApiError && error.status >= 400 && error.status < 500) && count < 1,
      staleTime: 30_000,
      refetchOnWindowFocus: false,
    },
  },
})

export default function App() {
  return (
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <Routes>
          <Route
            path="/report"
            element={
              <Suspense fallback={null}>
                <ReportPage />
              </Suspense>
            }
          />
          <Route path="*" element={<DashboardPage />} />
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  )
}
