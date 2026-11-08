import api from './axios'
import type {
  CreateTaskData,
  MoveTaskData,
  Task,
  UpdateTaskData,
} from './kanban'

export interface TaskBonusRules {
  minimum_completed_tasks: number
  amount_per_completed_task: number
  minimum_cases_won: number
  amount_per_case_won: number
  task_quality_weighting_enabled: boolean
  maximum_monthly_bonus: number
  excluded_employee_ids: number[]
  excluded_office_ids: number[]
}

export interface TaskBonusNomination {
  id: string
  isGranted: boolean
  suggestedAmount: number
  calculatedAt: string | null
}

export interface TaskBonusAssigneeProjection {
  employeeId: number
  userId: number
  name: string
  contributionPercent: number
  thisTaskCredit: number
  otherCredit: number
  monthCredit: number
  casesWon: number
  eligible: boolean
  reasons: string[]
  projectedAmount: number | null
  capped: boolean
  nomination: TaskBonusNomination | null
}

export interface TaskBonusPreview {
  month: string
  completedAt: string | null
  alreadyProcessed: boolean
  ruleVersion: number
  rules: TaskBonusRules
  currency: { code: string; symbol: string; decimalPlaces: number } | null
  assignees: TaskBonusAssigneeProjection[]
}

// Task API endpoints
const TaskApi = {
  // Create a new task
  create: async (data: CreateTaskData) => {
    try {
      return await api.post<{ data: Task }>('/tasks', data)
    }
    catch (error) {
      console.error('API create error:', error)
      throw error
    }
  },

  // Update a task
  update: async (id: string, data: UpdateTaskData) => {
    try {
      return await api.put<{ data: Task }>(`/tasks/${id}`, data)
    }
    catch (error) {
      console.error('API update error:', error)
      throw error
    }
  },

  // Delete a task
  delete: async (id: string) => {
    try {
      return await api.delete<{ message: string }>(`/tasks/${id}`)
    }
    catch (error) {
      console.error('API delete error:', error)
      throw error
    }
  },

  // Move a task (drag and drop)
  move: async (id: string, data: MoveTaskData) => {
    try {
      return await api.put<{
        message: string
        positions: Array<{ id: string; boardId: string; order: number }>
      }>(`/tasks/${id}/move`, data)
    }
    catch (error) {
      console.error('API move error:', error)
      throw error
    }
  },

  reviewBonusInputs: async (id: string, data: { quality_score: number; rejected?: boolean; contributions?: Record<string, number> }) => {
    const response = await api.patch<{ data: Task }>(`/tasks/${id}/bonus-review`, data)

    return response.data.data
  },

  getBonusPreview: async (id: string): Promise<TaskBonusPreview> => {
    const response = await api.get<{ data: TaskBonusPreview }>(`/tasks/${id}/bonus-preview`)

    return response.data.data
  },

  uploadAttachment: async (taskId: string, file: File) => {
    const formData = new FormData()

    formData.append('file', file)
    formData.append('section', 'input')

    return api.post(`/tasks/${taskId}/attachments`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
  },

  deleteAttachment: async (taskId: string, attachmentId: string) => api.delete(`/tasks/${taskId}/attachments/${attachmentId}`),

  downloadAttachment: async (taskId: string, attachmentId: string) => api.get(
    `/tasks/${taskId}/attachments/${attachmentId}/download`,
    { responseType: 'blob' },
  ),
}

export default TaskApi
