import api from './axios'

// Types matching the frontend requirements
export interface Board {
  id: string
  title: string
  order: number
  /** Non-null for a private personal board (one per user). */
  user_id: string | null
  is_personal: boolean
  is_owned: boolean
  department_id: number | null
  is_department: boolean
  tasks: Task[]
}

export interface Task {
  id: string
  boardId: string
  creatorId: string | null
  creator: User | null
  title: string
  description: string
  attachments: TaskAttachment[]
  priority: 'low' | 'medium' | 'high' | 'urgent'
  progress: number
  startDate: string
  dueDate: string
  order: number
  assignees: User[]
  tags: Tag[]
  subtasks: Subtask[]
  comments: Comment[]
  dependencies: string[]
  completedAt: string | null
  reopenedCount: number
  rejectedCount: number
  managerQualityScore: number | null
}

export interface TaskAttachment {
  id: string
  section: 'input'
  name: string
  mimeType: string | null
  size: number
  uploadedAt: string | null
  downloadUrl: string
  url: string
}

export interface User {
  id: string
  name: string
  email: string
  avatar: string | null
  contributionPercent?: number | null
}

export interface Tag {
  id: string
  name: string
  color: string
}

export interface Subtask {
  id: string
  taskId: string
  userId: string
  title: string
  category: string
  priority: 'low' | 'medium' | 'high' | 'urgent'
  isCompleted: boolean
  createdAt: string
  editableUntil: string
  canEdit: boolean
  editWindowExpired: boolean
}

export interface Comment {
  id: string
  taskId: string
  senderName: string
  senderId: string
  senderAvatar: string | null
  content: string
  mentionIds: string[]
  mentions: Array<{ id: string; name: string }>
  timestamp: string
  createdAt: string | null
  editableUntil: string
  canEdit: boolean
  editWindowExpired: boolean
}

export interface CreateTaskData {
  board_id: string
  title: string
  description?: string
  priority?: 'low' | 'medium' | 'high' | 'urgent'
  start_date?: string
  due_date?: string
  customer_id?: number | null
  applicant_id?: number | null
  assignees?: number[]
  tags?: number[]
}

export interface UpdateTaskData {
  title?: string
  description?: string
  priority?: 'low' | 'medium' | 'high' | 'urgent'
  progress?: number
  start_date?: string
  due_date?: string
  assignees?: string[]
  tags?: string[]
  dependencies?: string[]
  customer_id?: number | null
  applicant_id?: number | null
}

export interface MoveTaskData {
  board_id: string
  order: number
}

export interface CreateCommentData {
  content: string
  mention_ids?: number[]
}

// Kanban API endpoints
const KanbanApi = {
  // Get entire kanban board state
  getBoard: async () => {
    try {
      return await api.get<{ data: Board[] }>('/kanban')
    }
    catch (error) {
      console.error('API getBoard error:', error)
      throw error
    }
  },

  // Task endpoints
  createTask: async (data: CreateTaskData) => {
    try {
      return await api.post<{ data: Task }>('/tasks', data)
    }
    catch (error) {
      console.error('API createTask error:', error)
      throw error
    }
  },

  updateTask: async (id: string, data: UpdateTaskData) => {
    try {
      return await api.put<{ message: string }>(`/tasks/${id}`, data)
    }
    catch (error) {
      console.error('API updateTask error:', error)
      throw error
    }
  },

  deleteTask: async (id: string) => {
    try {
      return await api.delete<{ message: string }>(`/tasks/${id}`)
    }
    catch (error) {
      console.error('API deleteTask error:', error)
      throw error
    }
  },

  moveTask: async (id: string, data: MoveTaskData) => {
    try {
      return await api.put<{ message: string }>(`/tasks/${id}/move`, data)
    }
    catch (error) {
      console.error('API moveTask error:', error)
      throw error
    }
  },

  // Comment endpoints
  createComment: async (taskId: string, data: CreateCommentData) => {
    try {
      return await api.post<{ data: Comment }>(`/tasks/${taskId}/comments`, data)
    }
    catch (error) {
      console.error('API createComment error:', error)
      throw error
    }
  },
}

export default KanbanApi
