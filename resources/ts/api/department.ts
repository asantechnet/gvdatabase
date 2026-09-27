import api from './axios'

export interface Department {
  id: number
  name: string
  code: string | null
  description: string | null
  is_active: boolean
  manager_employee_id: number | null
  manager_name: string | null
  employees_count: number
}

export interface DepartmentPayload {
  name: string
  code?: string | null
  description?: string | null
  manager_employee_id?: number | null
  is_active: boolean
}

const DepartmentApi = {
  getAll: async (includeInactive = false): Promise<Department[]> => (await api.get<{ data: Department[] }>('/departments', { params: includeInactive ? { include_inactive: true } : undefined })).data.data,
  create: async (data: DepartmentPayload): Promise<Department> => (await api.post<{ data: Department }>('/departments', data)).data.data,
  update: async (id: number, data: Partial<DepartmentPayload>): Promise<Department> => (await api.put<{ data: Department }>(`/departments/${id}`, data)).data.data,
  delete: async (id: number): Promise<void> => { await api.delete(`/departments/${id}`) },
}

export default DepartmentApi
