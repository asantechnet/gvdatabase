import api, { mutationHeaders, type ApiMutationOptions } from './axios'

export type SupportTicketCategory = 'technical' | 'account' | 'billing' | 'feature_request' | 'other'
export type SupportTicketSeverity = 'low' | 'normal' | 'high' | 'critical'

export interface SupportTicket {
  id: number
  ticket_number: string
  category: SupportTicketCategory
  category_label: string
  severity: SupportTicketSeverity
  subject: string
  description: string | null
  page_url: string | null
  status: string
  submitted_at: string | null
  created_at: string | null
  attachments: SupportTicketAttachment[]
}

export interface SupportTicketAttachment {
  id: number
  name: string
  mime_type: string
  size: number
  download_url: string
}

export interface CreateSupportTicketData {
  category: SupportTicketCategory
  severity: SupportTicketSeverity
  subject: string
  description?: string
  page_url?: string
}

export class SupportTicketApiError extends Error {
  public readonly validationErrors: Record<string, string[]> | undefined
  public readonly errors: Record<string, string[]> | undefined
  public readonly messages: Record<string, string[]> | undefined
  public readonly code: string | undefined

  constructor(error: any) {
    super(error?.message || 'Your support request could not be sent.')
    this.name = 'SupportTicketApiError'
    this.validationErrors = error?.errors
    this.errors = error?.errors
    this.messages = error?.messages
    this.code = error?.code
  }
}

const request = async <T>(operation: () => Promise<any>): Promise<T> => {
  try {
    const response = await operation()

    return response.data.data as T
  }
  catch (error) {
    throw new SupportTicketApiError(error)
  }
}

export const getSupportTickets = (limit = 50): Promise<SupportTicket[]> => request(() => api.get('/support-tickets', { params: { limit } }))
export const createSupportTicket = (data: CreateSupportTicketData, attachments: File[] = [], options?: ApiMutationOptions): Promise<SupportTicket> => {
  const payload = new FormData()
  Object.entries(data).forEach(([key, value]) => {
    if (value)
      payload.append(key, value)
  })
  attachments.forEach(file => payload.append('attachments[]', file))

  return request(() => api.post('/support-tickets', payload, {
    headers: { 'Content-Type': 'multipart/form-data', ...mutationHeaders(options) },
  }))
}
