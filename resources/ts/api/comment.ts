import api from './axios'
import type { Comment, CreateCommentData } from './kanban'

// Comment API endpoints
const CommentApi = {
  // Create a new comment
  create: async (taskId: string, data: CreateCommentData) => {
    try {
      return await api.post<{ data: Comment }>(`/tasks/${taskId}/comments`, data)
    }
    catch (error) {
      console.error('API create error:', error)
      throw error
    }
  },
  update: (taskId: string, commentId: string, content: string) =>
    api.put<{ data: Comment }>(`/tasks/${taskId}/comments/${commentId}`, { content }),
  delete: (taskId: string, commentId: string) =>
    api.delete(`/tasks/${taskId}/comments/${commentId}`),
}

export default CommentApi
