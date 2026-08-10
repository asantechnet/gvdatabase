import api from './axios'

export interface Setting {
  id: number
  navigation_clr: string
  cards_clr: string
  footer_clr: string
  website_title: string
  website_logo: string | null
  website_logo_url: string | null
  website_address: string | null
  phone: string | null
  physical_address: string | null
  short_company_name: string | null
  original_company_name: string | null
  created_at?: string
  updated_at?: string
}

export interface SettingForm {
  navigation_clr: string
  cards_clr: string
  footer_clr: string
  website_title: string
  website_logo?: File | null
  website_address: string
  phone: string
  physical_address: string
  short_company_name: string
  original_company_name: string
}

export interface ApiResponse<T> {
  success: boolean
  data: T
  message?: string
}

const unwrap = <T>(response: any): T => response.data.data

const SettingApi = {
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
    formData.append('website_title', data.website_title)
    formData.append('website_address', data.website_address)
    formData.append('phone', data.phone)
    formData.append('physical_address', data.physical_address)
    formData.append('short_company_name', data.short_company_name)
    formData.append('original_company_name', data.original_company_name)
    if (data.website_logo) {
      formData.append('website_logo', data.website_logo)
    }

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
      if (key === 'website_logo' && value instanceof File) {
        formData.append(key, value)
        return
      }
      if (typeof value === 'string') {
        formData.append(key, value)
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
