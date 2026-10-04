import api from './axios'

export type AttachmentType = 'expenses' | 'incomes' | 'employees'

export interface Attachment {
  id: string
  section: string | null
  name: string
  mimeType: string | null
  size: number
  uploadedAt?: string | null
  downloadUrl: string
}

const AttachmentsApi = {
  list: async (type: AttachmentType, id: number): Promise<Attachment[]> =>
    (await api.get<{ data: Attachment[] }>(`/attachments/${type}/${id}`)).data.data,

  upload: async (type: AttachmentType, id: number, file: File, section?: string): Promise<Attachment> => {
    const body = new FormData()
    body.append('file', file)
    if (section)
      body.append('section', section)

    return (await api.post<{ data: Attachment }>(`/attachments/${type}/${id}`, body, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data.data
  },

  remove: async (type: AttachmentType, id: number, attachmentId: string): Promise<void> => {
    await api.delete(`/attachments/${type}/${id}/${attachmentId}`)
  },

  download: async (type: AttachmentType, id: number, attachmentId: string) =>
    api.get(`/attachments/${type}/${id}/${attachmentId}/download`, { responseType: 'blob' }),
}

export default AttachmentsApi
