import api from './axios'

export interface Currency {
  id: number
  name: string
  code: string
  symbol: string
  rate: number
  isBase: boolean
  isActive: boolean
  decimalPlaces: number
  updatedAt: string
}

export interface CurrencyForm {
  name: string
  code: string
  symbol: string
  rate: number
  isActive: boolean
  decimalPlaces: number
  isBase?: boolean
}

export interface ApiResponse<T> {
  success: boolean
  data: T
  message?: string
}

const CurrencyApi = {
  // Get all currencies
  getAll: async (params?: {
    page?: number
    perPage?: number
  }): Promise<ApiResponse<Currency[]>> => {
    const response = await api.get('/currencies', { params })

    return {
      success: true,
      data: response.data.data,
    }
  },

  // Get single currency
  getById: async (id: number): Promise<ApiResponse<Currency>> => {
    const response = await api.get(`/currencies/${id}`)

    return {
      success: true,
      data: response.data.data,
    }
  },

  // Create currency
  create: async (
    data: CurrencyForm,
  ): Promise<ApiResponse<Currency>> => {
    const payload = {
      name: data.name,
      code: data.code.toUpperCase(),
      symbol: data.symbol,
      rate: data.isBase ? 1 : Number(data.rate),
      is_active: data.isActive,
      decimal_places: data.decimalPlaces,
      is_base: data.isBase,
    }

    const response = await api.post('/currencies', payload)

    return {
      success: true,
      data: response.data.data,
    }
  },

  // Update currency
  update: async (
    id: number,
    data: Partial<CurrencyForm>,
  ): Promise<ApiResponse<Currency>> => {
    const payload: Record<string, any> = {}
    if (data.name !== undefined)
      payload.name = data.name
    if (data.code !== undefined)
      payload.code = data.code.toUpperCase()
    if (data.symbol !== undefined)
      payload.symbol = data.symbol
    if (data.rate !== undefined)
      payload.rate = data.isBase ? 1.0 : Number(data.rate)
    if (data.isActive !== undefined)
      payload.is_active = data.isActive
    if (data.decimalPlaces !== undefined)
      payload.decimal_places = data.decimalPlaces
    if (data.isBase !== undefined)
      payload.is_base = data.isBase

    const response = await api.put(`/currencies/${id}`, payload)

    return {
      success: true,
      data: response.data.data,
    }
  },

  // Delete currency
  delete: async (id: number): Promise<ApiResponse<null>> => {
    const response = await api.delete(`/currencies/${id}`)

    return {
      success: response.data.success,
      data: null,
      message: response.data.message,
    }
  },
}

export default CurrencyApi
