import api from "./axios";
import { Board, CreateBoardData, UpdateBoardData } from "./kanban";

// Board API endpoints
const BoardApi = {
  // Get all boards
  getAll: async () => {
    try {
      const response = await api.get<{ data: Board[] }>("/boards");
      return response;
    } catch (error) {
      console.error('API getAll error:', error);
      throw error;
    }
  },

  // Create a new board
  create: async (data: CreateBoardData) => {
    try {
      const response = await api.post<{ data: Board }>("/boards", data);
      return response;
    } catch (error) {
      console.error('API create error:', error);
      throw error;
    }
  },

  // Update a board
  update: async (id: string, data: UpdateBoardData) => {
    try {
      const response = await api.put<{ message: string }>(`/boards/${id}`, data);
      return response;
    } catch (error) {
      console.error('API update error:', error);
      throw error;
    }
  },

  // Delete a board
  delete: async (id: string) => {
    try {
      const response = await api.delete<{ message: string }>(`/boards/${id}`);
      return response;
    } catch (error) {
      console.error('API delete error:', error);
      throw error;
    }
  },

  // Persist the complete column order after a board drag-and-drop.
  reorder: async (boardIds: string[]) => {
    try {
      return await api.put<{ message: string }>("/boards/reorder", { board_ids: boardIds });
    } catch (error) {
      console.error('API reorder error:', error);
      throw error;
    }
  },
};

export default BoardApi;
