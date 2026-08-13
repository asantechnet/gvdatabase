import api from './axios'

export interface Setting {
  id: number
  navigation_clr: string
  cards_clr: string
  footer_clr: string
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
  website_address: string | null
  company_email: string | null
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
  navigation_clr: string
  cards_clr: string
  footer_clr: string
  paid_off_days_per_year: number
  website_title: string
  website_logo?: File | null
  company_signature?: File | null
  contract_letterhead?: File | null
  contract_cover_design?: File | null
  remove_contract_letterhead?: boolean
  remove_contract_cover_design?: boolean
  website_address: string
  company_email: string
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
    const formData = new FormData()
    formData.append('navigation_clr', data.navigation_clr)
    formData.append('cards_clr', data.cards_clr)
    formData.append('footer_clr', data.footer_clr)
    formData.append('paid_off_days_per_year', String(data.paid_off_days_per_year))
    formData.append('website_title', data.website_title)
    formData.append('website_address', data.website_address)
    formData.append('company_email', data.company_email)
    formData.append('timezone', data.timezone)
    formData.append('phone', data.phone)
    formData.append('physical_address', data.physical_address)
    formData.append('short_company_name', data.short_company_name)
    formData.append('original_company_name', data.original_company_name)
    formData.append('business_licence', data.business_licence)
    formData.append('office_open_time', data.office_open_time)
    formData.append('office_close_time', data.office_close_time)
    formData.append('default_break_minutes', String(data.default_break_minutes))
    if (data.website_logo) {
      formData.append('website_logo', data.website_logo)
    }
    if (data.company_signature) formData.append('company_signature', data.company_signature)
    if (data.contract_letterhead) formData.append('contract_letterhead', data.contract_letterhead)
    if (data.contract_cover_design) formData.append('contract_cover_design', data.contract_cover_design)

    const response = await api.post('/settings', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })

    return {
      success: true,
      data: unwrap<Setting>(response),
    }
  },

  update: async (id: number, data: Partial<SettingForm>): Promise<ApiResponse<Setting>> => {
    const formData = new FormData()
    Object.entries(data).forEach(([key, value]) => {
      if (value === undefined || value === null) return
      if (['website_logo', 'company_signature', 'contract_letterhead', 'contract_cover_design'].includes(key) && value instanceof File) {
        formData.append(key, value)
        return
      }
      if (typeof value === 'boolean') {
        if (value)
          formData.append(key, '1')
        return
      }
      if (typeof value === 'string' || typeof value === 'number') {
        formData.append(key, String(value))
      }
    })
    formData.append('_method', 'PUT')

    const response = await api.post(`/settings/${id}`, formData, {
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
