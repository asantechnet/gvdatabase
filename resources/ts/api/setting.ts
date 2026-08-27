import api from './axios'

export interface Setting {
  id: number
  primary_color: string | null
  secondary_color: string | null
  tertiary_color: string | null
  paid_off_days_per_year: number
  website_title: string
  website_logo: string | null
  website_logo_url: string | null
  company_signature: string | null
  company_signature_url: string | null
  contract_letterhead: string | null
  contract_letterhead_url: string | null
  contract_cover_design: string | null
  contract_cover_design_url: string | null
  contract_letterhead_safe_x: number
  contract_letterhead_safe_y: number
  contract_letterhead_safe_width: number
  contract_letterhead_safe_height: number
  website_address: string | null
  company_email: string | null
  mail_host: string | null
  mail_port: number | null
  mail_encryption: 'tls' | 'ssl' | null
  mail_username: string | null
  mail_password_configured: boolean
  mail_from_name: string | null
  mail_reply_to: string | null
  timezone: string
  phone: string | null
  physical_address: string | null
  short_company_name: string | null
  original_company_name: string | null
  business_licence: string | null
  office_open_time: string | null
  office_close_time: string | null
  default_break_minutes: number
  created_at?: string
  updated_at?: string
}

export interface SettingForm {
  primary_color?: string | null
  secondary_color?: string | null
  tertiary_color?: string | null
  paid_off_days_per_year: number
  website_title: string
  website_logo?: File | null
  company_signature?: File | null
  contract_letterhead?: File | null
  contract_cover_design?: File | null
  remove_contract_letterhead?: boolean
  remove_contract_cover_design?: boolean
  contract_letterhead_safe_x?: number
  contract_letterhead_safe_y?: number
  contract_letterhead_safe_width?: number
  contract_letterhead_safe_height?: number
  website_address: string
  company_email: string
  mail_host: string
  mail_port: number | null
  mail_encryption: 'tls' | 'ssl' | null
  mail_username: string
  mail_password?: string
  clear_mail_password?: boolean
  mail_from_name: string
  mail_reply_to: string
  timezone: string
  phone: string
  physical_address: string
  short_company_name: string
  original_company_name: string
  business_licence: string
  office_open_time: string
  office_close_time: string
  default_break_minutes: number
}

export interface ApiResponse<T> {
  success: boolean
  data: T
  message?: string
}

const unwrap = <T>(response: any): T => response.data.data

function toFormData(data: Partial<SettingForm>, method?: 'PUT') {
  const formData = new FormData()

  Object.entries(data).forEach(([key, value]) => {
    if (value === undefined) return
    if (value === null) {
      if (!['website_logo', 'company_signature', 'contract_letterhead', 'contract_cover_design'].includes(key))
        formData.append(key, '')
      return
    }
    if (value instanceof File) {
      formData.append(key, value)
      return
    }
    if (typeof value === 'boolean') {
      formData.append(key, value ? '1' : '0')
      return
    }
    formData.append(key, String(value))
  })

  if (method) formData.append('_method', method)

  return formData
}

const SettingApi = {
  getCompanyProfile: async (): Promise<ApiResponse<Setting | null>> => {
    const response = await api.get('/company-profile')
    return {
      success: true,
      data: response.data.data ?? null,
    }
  },

  getAll: async (): Promise<ApiResponse<Setting[]>> => {
    const response = await api.get('/settings')
    return {
      success: true,
      data: Array.isArray(response.data.data) ? response.data.data : [],
    }
  },

  getById: async (id: number): Promise<ApiResponse<Setting>> => {
    const response = await api.get(`/settings/${id}`)
    return {
      success: true,
      data: unwrap<Setting>(response),
    }
  },

  create: async (data: SettingForm): Promise<ApiResponse<Setting>> => {
    const response = await api.post('/settings', toFormData(data), {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    return {
      success: true,
      data: unwrap<Setting>(response),
    }
  },

  update: async (id: number, data: Partial<SettingForm>): Promise<ApiResponse<Setting>> => {
    const response = await api.post(`/settings/${id}`, toFormData(data, 'PUT'), {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    return {
      success: true,
      data: unwrap<Setting>(response),
    }
  },

  delete: async (id: number): Promise<ApiResponse<null>> => {
    const response = await api.delete(`/settings/${id}`)
    return {
      success: response.data.success,
      data: null,
      message: response.data.message,
    }
  },
}

export default SettingApi
