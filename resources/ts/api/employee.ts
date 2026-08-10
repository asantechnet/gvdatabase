import api from "./axios";

// Types matching the frontend requirements
export interface Employee {
  id: number;
  user_id: number | null;
  name: string;
  father_name: string;
  contact: string;
  education_lvl: string;
  email: string | null;
  addr: string;
  position_id: number;
  position?: {
    id: number;
    name: string;
  };
  salary: number | null;
  bonus: boolean;
  ID_file: string | null;
  assure_file: string | null;
  join_date: string | null;
  contract_duration: string;
  work_start_time: string | null;
  work_end_time: string | null;
  created_at: string | null;
  updated_at: string | null;
  avatar_url?: string | null;
}

export interface EmployeeParams {
  page?: number;
  perPage?: number;
  search?: string;
  position?: string;
  bonus?: string;
}

export interface CreateEmployeeData {
  name: string;
  father_name: string;
  contact: string;
  create_user?: boolean;
  email?: string | null;
  education_lvl: string;
  position_id: number;
  salary: number;
  bonus: boolean;
  addr: string;
  ID_file?: string | null;
  assure_file?: string | null;
  join_date: string;
  contract_duration: string;
}

export interface UpdateEmployeeData {
  name?: string;
  father_name?: string;
  contact?: string;
  create_user?: boolean;
  email?: string | null;
  education_lvl?: string;
  position_id?: number;
  salary?: number;
  bonus?: boolean;
  addr?: string;
  ID_file?: string | null;
  assure_file?: string | null;
  join_date?: string;
  contract_duration?: string;
}

// Employee API endpoints
const EmployeeApi = {
  // Get all employees
  getAll: async (params?: EmployeeParams) => {
    try {
      const response = await api.get<{ data: Employee[] }>("/employees", { params });
      return response;
    } catch (error) {
      console.error('API getAll error:', error);
      throw error;
    }
  },

  // Get single employee
  getById: async (id: number) => {
    try {
      const response = await api.get<{ data: Employee }>(`/employees/${id}`);
      return response;
    } catch (error) {
      console.error('API getById error:', error);
      throw error;
    }
  },

  // Create employee
  create: async (data: CreateEmployeeData) => {
    try {
      const response = await api.post<{ data: Employee }>("/employees", data);
      return response;
    } catch (error) {
      console.error('API create error:', error);
      throw error;
    }
  },

  // Update employee
  update: async (id: number, data: UpdateEmployeeData) => {
    try {
      const response = await api.put<{ data: Employee }>(`/employees/${id}`, data);
      return response;
    } catch (error) {
      console.error('API update error:', error);
      throw error;
    }
  },

  // Delete employee
  delete: async (id: number) => {
    try {
      const response = await api.delete<{ success: boolean; message: string }>(`/employees/${id}`);
      return response;
    } catch (error) {
      console.error('API delete error:', error);
      throw error;
    }
  },
};

export default EmployeeApi;
