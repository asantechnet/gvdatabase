import api from "./axios";
import {
  Task,
  CreateTaskData,
  UpdateTaskData,
  MoveTaskData
} from "./kanban";

// Task API endpoints
const TaskApi = {
  // Create a new task
  create: async (data: CreateTaskData) => {
    try {
      const response = await api.post<{ data: Task }>("/tasks", data);
      return response;
    } catch (error) {
      console.error('API create error:', error);
      throw error;
    }
  },

  // Update a task
  update: async (id: string, data: UpdateTaskData) => {
    try {
      const response = await api.put<{ message: string }>(`/tasks/${id}`, data);
      return response;
    } catch (error) {
      console.error('API update error:', error);
      throw error;
    }
  },

  // Delete a task
  delete: async (id: string) => {
    try {
      const response = await api.delete<{ message: string }>(`/tasks/${id}`);
      return response;
    } catch (error) {
      console.error('API delete error:', error);
      throw error;
    }
  },

  // Move a task (drag and drop)
  move: async (id: string, data: MoveTaskData) => {
    try {
      const response = await api.put<{
        message: string
        positions: Array<{ id: string; boardId: string; order: number }>
      }>(`/tasks/${id}/move`, data);
      return response;
    } catch (error) {
      console.error('API move error:', error);
      throw error;
    }
  },

  reviewBonusInputs: async (id: string, data: { quality_score: number; rejected?: boolean; contributions?: Record<string, number> }) => {
    const response = await api.patch<{ data: Task }>(`/tasks/${id}/bonus-review`, data)
    return response.data.data
  },
};

export default TaskApi;
