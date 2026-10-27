import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { lazy, Suspense } from 'react'
import { BrowserRouter, Navigate, Route, Routes } from 'react-router-dom'
import { DashboardPage } from './features/dashboard/DashboardPage'
import { EntryPage } from './features/home/HomePage'
import { ApiError } from './services/api/client'

// The print report is its own chunk: it is opened in a separate tab and is not part of the dashboard's first load.
const ReportPage = lazy(() => import('./features/report/ReportPage').then((m) => ({ default: m.ReportPage })))
const OrganizationPage = lazy(() => import('./features/organization/OrganizationPage').then((m) => ({ default: m.OrganizationPage })))
const OrganizationReportPage = lazy(() => import('./features/organization/OrganizationReportPage').then((m) => ({ default: m.OrganizationReportPage })))

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
          <Route path="/" element={<EntryPage />} />
          <Route path="/institution" element={<DashboardPage />} />
          <Route
            path="/organization"
            element={
              <Suspense fallback={null}>
                <OrganizationPage />
              </Suspense>
            }
          />
          <Route
            path="/organization/report"
            element={
              <Suspense fallback={null}>
                <OrganizationReportPage />
              </Suspense>
            }
          />
          <Route
            path="/report"
            element={
              <Suspense fallback={null}>
                <ReportPage />
              </Suspense>
            }
          />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </BrowserRouter>
    </QueryClientProvider>
  )
}
