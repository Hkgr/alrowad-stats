const BASE_URL = (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? '/api/v1'

export class ApiError extends Error {
  readonly status: number
  readonly fieldErrors: Record<string, string[]>

  constructor(message: string, status: number, fieldErrors: Record<string, string[]> = {}) {
    super(message)
    this.name = 'ApiError'
    this.status = status
    this.fieldErrors = fieldErrors
  }
}

type Params = Record<string, string | null | undefined>

export async function apiGet<T>(path: string, params: Params = {}, signal?: AbortSignal): Promise<T> {
  const query = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value) query.set(key, value)
  }
  const qs = query.toString()

  let response: Response
  try {
    response = await fetch(`${BASE_URL}${path}${qs ? `?${qs}` : ''}`, {
      headers: { Accept: 'application/json' },
      signal,
    })
  } catch (error) {
    if (error instanceof DOMException && error.name === 'AbortError') throw error
    throw new ApiError('تعذّر الاتصال بالخادم. تحقق من أن الواجهة الخلفية تعمل.', 0)
  }

  if (!response.ok) {
    let message = 'حدث خطأ غير متوقع أثناء جلب البيانات.'
    let fieldErrors: Record<string, string[]> = {}
    try {
      const body = (await response.json()) as { message?: string; errors?: Record<string, string[]> }
      if (body.message) message = body.message
      if (body.errors) fieldErrors = body.errors
    } catch {
      // Non-JSON error body: keep the generic message.
    }
    throw new ApiError(message, response.status, fieldErrors)
  }

  return ((await response.json()) as { data: T }).data
}
