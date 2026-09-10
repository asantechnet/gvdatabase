import api from './axios'

export interface PlaceholderField {
  key: string
  label: string
}

/** A context-aware group of placeholders served by GET /contract-placeholders. */
export interface PlaceholderGroup {
  key: string
  label: string
  fields: PlaceholderField[]
}

export const getPlaceholders = async (params: {
  template_id?: number
  contract_type_id?: number
}): Promise<PlaceholderGroup[]> => {
  try {
    const response = await api.get<{ success: boolean; data: PlaceholderGroup[] }>('/contract-placeholders', { params })

    return Array.isArray(response.data.data) ? response.data.data : []
  }
  catch (error) {
    console.error('Error fetching contract placeholders:', error)
    throw error
  }
}
