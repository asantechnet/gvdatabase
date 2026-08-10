import api from './axios'
import type { Subtask } from './kanban'

export interface CreateSubtaskData {
  title: string
  category: string
  priority: Subtask['priority']
}

export interface UpdateSubtaskData {
  is_completed?: boolean
}

interface SubtaskResponse {
  data: Subtask
  taskProgress: number
}

interface DeleteSubtaskResponse {
  data: { id: string }
  taskProgress: number
}

const SubtaskApi = {
  create: (taskId: string, data: CreateSubtaskData) =>
    api.post<SubtaskResponse>(`/tasks/${taskId}/subtasks`, data),

  update: (taskId: string, subtaskId: string, data: UpdateSubtaskData) =>
    api.put<SubtaskResponse>(`/tasks/${taskId}/subtasks/${subtaskId}`, data),

  delete: (taskId: string, subtaskId: string) =>
    api.delete<DeleteSubtaskResponse>(`/tasks/${taskId}/subtasks/${subtaskId}`),
}

export default SubtaskApi
