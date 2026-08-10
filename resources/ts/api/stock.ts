import api from "./axios";

// Types matching the frontend requirements
export interface StockItem {
  id: number
  serial_number: string
  item: string
  quantity: number
  model: string
  office: string
  staff_id: number | null
  staff?: {
    id: number
    name: string
  }
}

export interface StockParams {
  search?: string
  model?: string
  office?: string
}

// Stock API endpoints
const StockApi = {
  // Get all stocks
  getAll: async (params?: StockParams) => {
    try {
      const response = await api.get<{ data: StockItem[] }>("/stocks", { params })
      return response
    } catch (error) {
      console.error('API getAll error:', error)
      throw error
    }
  },

  // Get single stock
  getById: async (id: number) => {
    try {
      const response = await api.get<{ data: StockItem }>(`/stocks/${id}`)
      return response
    } catch (error) {
      console.error('API getById error:', error)
      throw error
    }
  },

  // Create stock
  create: async (data: Omit<StockItem, 'id' | 'staff'>) => {
    try {
      const response = await api.post<{ data: StockItem }>("/stocks", data)
      return response
    } catch (error) {
      console.error('API create error:', error)
      throw error
    }
  },

  // Update stock
  update: async (id: number, data: Partial<Omit<StockItem, 'id' | 'staff'>>) => {
    try {
      const response = await api.put<{ data: StockItem }>(`/stocks/${id}`, data)
      return response
    } catch (error) {
      console.error('API update error:', error)
      throw error
    }
  },

  // Delete stock
  delete: async (id: number) => {
    try {
      const response = await api.delete<{ success: boolean; message: string }>(`/stocks/${id}`)
      return response
    } catch (error) {
      console.error('API delete error:', error)
      throw error
    }
  },
}

export default StockApi
export type { StockItem, StockParams }
