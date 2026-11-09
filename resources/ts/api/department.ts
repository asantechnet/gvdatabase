import api from './axios'

export interface DepartmentMember {
  id: number
  name: string
  position: string | null
  role: string | null
  status: 'active' | 'inactive'
  is_manager: boolean
}

export interface DepartmentManager {
  id: number
  name: string
}

export interface Department {
  id: number
  name: string
  description: string | null
  is_active: boolean
  manager_employee_id: number | null
  manager_name: string | null
  manager_employee_ids: number[]
  managers: DepartmentManager[]
  board_id: number | null
  board_title: string | null
  employees_count: number
  members?: DepartmentMember[]
}

export interface DepartmentManagerOption {
  id: number
  name: string
  employeeId: number
  isSenior: boolean
  departmentId: number | null
  departmentName?: string | null
}

export interface DepartmentDeleteRefusal {
  message?: string
  blocking_employee_count?: number
  blocking_employees?: string[]
}

export interface DepartmentPayload {
  name: string
  description?: string | null
  manager_employee_id?: number | null
  manager_employee_ids?: number[]
  create_task_board?: boolean
  is_active: boolean
  employee_ids?: number[]
}

const DepartmentApi = {
  getAll: async (includeInactive = false, withMembers = false): Promise<Department[]> => {
    const params: Record<string, boolean> = {}

    if (includeInactive)
      params.include_inactive = true

    if (withMembers)
      params.with_members = true

    return (await api.get<{ data: Department[] }>('/departments', { params })).data.data
  },
  getManagerOptions: async (departmentId?: number | null): Promise<DepartmentManagerOption[]> => (await api.get<{ data: DepartmentManagerOption[] }>('/departments/manager-options', { params: departmentId ? { department_id: departmentId } : undefined })).data.data,
  create: async (data: DepartmentPayload): Promise<Department> => (await api.post<{ data: Department }>('/departments', data)).data.data,
  update: async (id: number, data: Partial<DepartmentPayload>): Promise<Department> => (await api.put<{ data: Department }>(`/departments/${id}`, data)).data.data,
  delete: async (id: number): Promise<void> => { await api.delete(`/departments/${id}`) },
}

export default DepartmentApi
