/**
 * Reader for the list endpoints that page on request.
 *
 * Sending `per_page` makes the backend answer with the page's rows in `data`
 * plus `current_page`, `last_page` and `total` beside them. Omitting it keeps
 * the plain array, so the same endpoint still serves pickers and reports.
 */
export interface ListPage<T> {
  rows: T[]
  total: number
  page: number
  lastPage: number
}

export interface ListPageParams {
  page?: number
  perPage?: number
}

export const emptyPage = <T>(rows: T[] = []): ListPage<T> => ({
  rows,
  total: rows.length,
  page: 1,
  lastPage: 1,
})

/** Accepts either the plain array or the paged payload of one list request. */
export const readPage = <T>(payload: unknown): ListPage<T> => {
  if (Array.isArray(payload))
    return emptyPage(payload as T[])

  const paged = (payload ?? {}) as { data?: unknown; current_page?: number; last_page?: number; total?: number }
  const rows = (Array.isArray(paged.data) ? paged.data : []) as T[]

  return {
    rows,
    total: Number(paged.total ?? rows.length),
    page: Number(paged.current_page ?? 1),
    lastPage: Number(paged.last_page ?? 1),
  }
}

/** Drops empty filter values so an unset filter is not sent as `''`. */
export const cleanListParams = (params: Record<string, unknown>): Record<string, unknown> => {
  const out: Record<string, unknown> = {}

  for (const [key, value] of Object.entries(params)) {
    if (value !== undefined && value !== null && value !== '')
      out[key] = value
  }

  return out
}
