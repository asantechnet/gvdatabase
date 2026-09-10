import api from './axios'
import type { Board, CreateBoardData, UpdateBoardData } from './kanban'

// Board API endpoints
const BoardApi = {
  // Get all boards
  getAll: async (params?: { project_id?: number }) => {
    try {
      return await api.get<{ data: Board[] }>('/boards', { params })
    }
    catch (error) {
      console.error('API getAll error:', error)
      throw error
    }
  },

  // Create a new board
  create: async (data: CreateBoardData) => {
    try {
      return await api.post<{ data: Board }>('/boards', data)
    }
    catch (error) {
      console.error('API create error:', error)
      throw error
    }
  },

  // Update a board
  update: async (id: string, data: UpdateBoardData) => {
    try {
      return await api.put<{ message: string }>(`/boards/${id}`, data)
    }
    catch (error) {
      console.error('API update error:', error)
      throw error
    }
  },

  // Delete a board
  delete: async (id: string) => {
    try {
      return await api.delete<{ message: string }>(`/boards/${id}`)
    }
    catch (error) {
      console.error('API delete error:', error)
      throw error
    }
  },

  // Persist the complete column order after a board drag-and-drop.
  reorder: async (boardIds: string[], projectId?: number | null) => {
    try {
      return await api.put<{ message: string }>('/boards/reorder', { board_ids: boardIds, project_id: projectId ?? null })
    }
    catch (error) {
      console.error('API reorder error:', error)
      throw error
    }
  },
}

export default BoardApi
