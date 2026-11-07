import api from './axios'
import type { Board } from './kanban'

// Board reads only. Boards are created by the product (one per service and one
// personal board per employee), so there is no create/update/delete surface.
const BoardApi = {
  getAll: async () => {
    try {
      return await api.get<{ data: Board[] }>('/boards')
    }
    catch (error) {
      console.error('API getAll error:', error)
      throw error
    }
  },
}

export default BoardApi
