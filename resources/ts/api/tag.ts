import api from "./axios";

// Types matching the frontend requirements
export interface Tag {
  id: string;
  name: string;
  color: string;
}

export interface CreateTagData {
  name: string;
  color: string;
}

export interface UpdateTagData {
  name?: string;
  color?: string;
}

// Tag API endpoints
const TagApi = {
  // Get all tags
  getAll: async () => {
    try {
      const response = await api.get<{ data: Tag[] }>("/tags");
      return response.data.data;
    } catch (error) {
      console.error("API getAll error:", error);
      throw error;
    }
  },

  // Create a new tag
  create: async (data: CreateTagData) => {
    try {
      const response = await api.post<{ data: Tag }>("/tags", data);
      return response.data.data;
    } catch (error) {
      console.error("API create error:", error);
      throw error;
    }
  },

  // Update a tag
  update: async (id: string, data: UpdateTagData) => {
    try {
      const response = await api.put<{ data: Tag }>(`/tags/${id}`, data);
      return response.data.data;
    } catch (error) {
      console.error("API update error:", error);
      throw error;
    }
  },

  // Delete a tag
  delete: async (id: string) => {
    try {
      const response = await api.delete<{ message: string }>(`/tags/${id}`);
      return response.data;
    } catch (error) {
      console.error("API delete error:", error);
      throw error;
    }
  },
};

export default TagApi;
export type { CreateTagData, Tag, UpdateTagData };
