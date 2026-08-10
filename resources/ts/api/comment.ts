import api from "./axios";
import { Comment, CreateCommentData } from "./kanban";

// Comment API endpoints
const CommentApi = {
  // Create a new comment
  create: async (taskId: string, data: CreateCommentData) => {
    try {
      const response = await api.post<{ data: Comment }>(`/tasks/${taskId}/comments`, data);
      return response;
    } catch (error) {
      console.error('API create error:', error);
      throw error;
    }
  },
};

export default CommentApi;
