import api from './axios'

/** The record kind a template is written for. Mirrors App\Support\WhatsappAudience. */
export type WhatsappAudience = 'visitor' | 'applicant' | 'employee'

/** One `{{token}}` the app can fill from the record the message is sent to. */
export interface WhatsappPlaceholderField {
  token: string
  /** English fallback; the UI translates `label_key` and falls back to this. */
  label: string
  label_key: string
  /** Dot path into the target record, e.g. `counselor.name`. */
  source: string
  /** Example value used by the editor preview. */
  sample: string
}

export interface WhatsappPlaceholderGroup {
  audience: WhatsappAudience
  label: string
  label_key: string
  fields: WhatsappPlaceholderField[]
}

export interface WhatsappTemplate {
  id: number
  audience: WhatsappAudience
  title: string
  labels: string[]
  message: string
  active: boolean
  sort_order: number
  created_at: string | null
  updated_at: string | null
}

export interface WhatsappTemplatePayload {
  audience: WhatsappAudience
  title: string
  labels: string[]
  message: string
  active: boolean
  sort_order: number
}

export interface WhatsappTemplateParams {
  audience?: WhatsappAudience
  /** 1 = active templates only (what the quick-contact menus offer). */
  active?: 0 | 1
}

export const getWhatsappTemplates = async (params?: WhatsappTemplateParams): Promise<WhatsappTemplate[]> =>
  (await api.get<{ data: WhatsappTemplate[] }>('/whatsapp-templates', { params })).data.data

export const getWhatsappPlaceholders = async (): Promise<WhatsappPlaceholderGroup[]> =>
  (await api.get<{ data: WhatsappPlaceholderGroup[] }>('/whatsapp-template-placeholders')).data.data

export const createWhatsappTemplate = async (data: WhatsappTemplatePayload): Promise<WhatsappTemplate> =>
  (await api.post<{ data: WhatsappTemplate }>('/whatsapp-templates', data)).data.data

export const updateWhatsappTemplate = async (id: number, data: Partial<WhatsappTemplatePayload>): Promise<WhatsappTemplate> =>
  (await api.put<{ data: WhatsappTemplate }>(`/whatsapp-templates/${id}`, data)).data.data

export const deleteWhatsappTemplate = async (id: number): Promise<void> => {
  await api.delete(`/whatsapp-templates/${id}`)
}
