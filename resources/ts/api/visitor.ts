import api from "./axios";

// Types matching the frontend requirements
export interface Visitor {
  id: number;
  name: string;
  company: string;
  contact: string;
  service: string;
  marketer: string;
  date: string;
  created_at?: string;
  updated_at?: string;
}

export interface VisitorParams {
  page?: number;
  perPage?: number;
  search?: string;
  company?: string;
  marketer?: string;
}

export interface CreateVisitorData {
  name: string;
  company: string;
  contact: string;
  service: string;
  marketer: string;
  date: string;
}

export interface UpdateVisitorData {
  name?: string;
  company?: string;
  contact?: string;
  service?: string;
  marketer?: string;
  date?: string;
}

// Visitor API endpoints
const VisitorApi = {
  // Get all visitors
  getAll: async (params?: VisitorParams) => {
    try {
      const response = await api.get<{ data: Visitor[] }>("/visitors", { params });
      return response.data.data;
    } catch (error) {
      console.error('API getAll error:', error);
      throw error;
    }
  },

  // Get single visitor
  getById: async (id: number) => {
    try {
      const response = await api.get<{ data: Visitor }>(`/visitors/${id}`);
      return response.data.data;
    } catch (error) {
      console.error('API getById error:', error);
      throw error;
    }
  },

  // Create visitor
  create: async (data: CreateVisitorData) => {
    try {
      const response = await api.post<{ data: Visitor }>("/visitors", data);
      return response.data.data;
    } catch (error) {
      console.error('API create error:', error);
      throw error;
    }
  },

  // Update visitor
  update: async (id: number, data: UpdateVisitorData) => {
    try {
      const response = await api.put<{ data: Visitor }>(`/visitors/${id}`, data);
      return response.data.data;
    } catch (error) {
      console.error('API update error:', error);
      throw error;
    }
  },

  // Delete visitor
  delete: async (id: number) => {
    try {
      await api.delete(`/visitors/${id}`);
    } catch (error) {
      console.error('API delete error:', error);
      throw error;
    }
  },
};

export default VisitorApi;
