import api from './axios'

export type LetterheadSource = 'none' | 'contract' | 'custom'

export interface SafeArea {
  x: number
  y: number
  width: number
  height: number
}

export interface ResolvedLetterhead {
  source: LetterheadSource
  url: string | null
  raw: string | null
  safe: SafeArea
}

export interface Setting {
  id: number
  primary_color: string
  secondary_color: string
  success_color: string
  info_color: string
  warning_color: string
  error_color: string
  light_background_color: string
  light_surface_color: string
  dark_background_color: string
  dark_surface_color: string
  paid_leave_days_per_year: number
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
  receipt_letterhead: string | null
  receipt_letterhead_url: string | null
  receipt_letterhead_source: LetterheadSource
  receipt_letterhead_safe_x: number
  receipt_letterhead_safe_y: number
  receipt_letterhead_safe_width: number
  receipt_letterhead_safe_height: number
  payslip_letterhead: string | null
  payslip_letterhead_url: string | null
  payslip_letterhead_source: LetterheadSource
  payslip_letterhead_safe_x: number
  payslip_letterhead_safe_y: number
  payslip_letterhead_safe_width: number
  payslip_letterhead_safe_height: number
  letterheads?: Record<'receipt' | 'payslip', ResolvedLetterhead>
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
  automatic_attendance_enabled: boolean
  created_at?: string
  updated_at?: string
}

export interface SettingForm {
  primary_color?: string | null
  secondary_color?: string | null
  success_color?: string | null
  info_color?: string | null
  warning_color?: string | null
  error_color?: string | null
  light_background_color?: string | null
  light_surface_color?: string | null
  dark_background_color?: string | null
  dark_surface_color?: string | null
  paid_leave_days_per_year: number
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
  receipt_letterhead?: File | null
  remove_receipt_letterhead?: boolean
  receipt_letterhead_source?: LetterheadSource
  receipt_letterhead_safe_x?: number
  receipt_letterhead_safe_y?: number
  receipt_letterhead_safe_width?: number
  receipt_letterhead_safe_height?: number
  payslip_letterhead?: File | null
  remove_payslip_letterhead?: boolean
  payslip_letterhead_source?: LetterheadSource
  payslip_letterhead_safe_x?: number
  payslip_letterhead_safe_y?: number
  payslip_letterhead_safe_width?: number
  payslip_letterhead_safe_height?: number
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
  automatic_attendance_enabled: boolean
}

export interface ApiResponse<T> {
  success: boolean
  data: T
  message?: string
}

const unwrap = <T>(response: any): T => response.data.data

function toFormData(data: Partial<SettingForm>) {
  const formData = new FormData()

  Object.entries(data).forEach(([key, value]) => {
    if (value === undefined)
      return
    if (value === null) {
      if (!['website_logo', 'company_signature', 'contract_letterhead', 'contract_cover_design', 'receipt_letterhead', 'payslip_letterhead'].includes(key))
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

  return formData
}

const hasFiles = (data: Partial<SettingForm>) => Object.values(data).some(value => value instanceof File)

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
    if (hasFiles(data)) {
      // PHP only reliably populates uploaded files for multipart POST requests.
      // Laravel honours this override and still dispatches to the PATCH route.
      const formData = toFormData(data)

      formData.append('_method', 'PATCH')

      const response = await api.post(`/settings/${id}`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      })

      return {
        success: true,
        data: unwrap<Setting>(response),
      }
    }

    const response = await api.patch(`/settings/${id}`, data)

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
