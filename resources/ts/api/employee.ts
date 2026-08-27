import api from "./axios";

// Types matching the frontend requirements
export interface Employee {
  id: number;
  user_id: number | null;
  name: string;
  father_name: string;
  contact: string;
  id_number: string | null;
  tax_number: string | null;
  bank_account_number: string | null;
  guarantor_id_number: string | null;
  guarantor_phone: string | null;
  guarantor_address: string | null;
  guarantor_licence: string | null;
  education_lvl: string;
  email: string | null;
  addr: string;
  position_id: number;
  employment_type: 'salaried' | 'commission';
  office_id: number | null;
  office: { id: number; name: string; code: string } | null;
  department: string | null;
  position?: {
    id: number;
    name: string;
  };
  salary: number | null;
  commissions: Array<{ service_id: number; service: string; percentage: number }>;
  commission_earned_base: number;
  total_worked_hours: number;
  ID_file: string | null;
  assure_file: string | null;
  join_date: string | null;
  contract_duration: string;
  work_start_time: string | null;
  work_end_time: string | null;
  created_at: string | null;
  updated_at: string | null;
  avatar?: string | null;
}

export interface EmployeeAccount {
  id: number
  name: string
  email: string
  employee: { id: number; name: string } | null
}

export interface EmployeeParams {
  page?: number;
  perPage?: number;
  search?: string;
  position?: string;
}

export interface CreateEmployeeData {
  user_id?: number | null;
  name: string;
  father_name: string;
  contact: string;
  id_number: string;
  tax_number?: string | null;
  bank_account_number?: string | null;
  guarantor_id_number?: string | null;
  guarantor_phone?: string | null;
  guarantor_address?: string | null;
  guarantor_licence?: string | null;
  create_user?: boolean;
  email?: string | null;
  education_lvl: string;
  position_id: number;
  employment_type: 'salaried' | 'commission';
  office_id?: number | null;
  salary?: number | null;
  commissions?: Array<{ service_id: number; percentage: number }>;
  addr: string;
  ID_file?: string | null;
  assure_file?: string | null;
  join_date: string;
  contract_duration: string;
}

export interface UpdateEmployeeData {
  user_id?: number | null;
  name?: string;
  father_name?: string;
  contact?: string;
  id_number?: string;
  tax_number?: string | null;
  bank_account_number?: string | null;
  guarantor_id_number?: string | null;
  guarantor_phone?: string | null;
  guarantor_address?: string | null;
  guarantor_licence?: string | null;
  create_user?: boolean;
  email?: string | null;
  education_lvl?: string;
  position_id?: number;
  employment_type?: 'salaried' | 'commission';
  office_id?: number | null;
  salary?: number | null;
  commissions?: Array<{ service_id: number; percentage: number }>;
  addr?: string;
  ID_file?: string | null;
  assure_file?: string | null;
  join_date?: string;
  contract_duration?: string;
}

// Employee API endpoints
const EmployeeApi = {
  getAccountOptions: async (): Promise<EmployeeAccount[]> => {
    const response = await api.get<{ data: EmployeeAccount[] }>('/employees/account-options')
    return Array.isArray(response.data.data) ? response.data.data : []
  },

  // Normalized list for selectors; keep legacy getAll response until its consumers are migrated.
  getOptions: async (): Promise<Employee[]> => {
    const response = await api.get<{ data: Employee[] }>("/employees");
    return Array.isArray(response.data.data) ? response.data.data : [];
  },

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
