import api from './axios'
import type { Attachment } from './attachments'
import { cleanListParams, readPage, type ListPage, type ListPageParams } from './pagination'

// Types matching the frontend requirements
export interface Employee {
  id: number
  user_id: number | null
  name: string
  father_name: string
  contact: string
  emergency_contact_name: string | null
  emergency_contact_phone: string | null
  id_number: string | null
  bank_account_number: string | null
  guarantor_name: string | null
  guarantor_id_number: string | null
  guarantor_phone: string | null
  guarantor_address: string | null
  guarantor_licence: string | null
  education_lvl: string
  email: string | null
  addr: string
  position_id: number
  status: 'active' | 'inactive'
  office_id: number | null
  office: { id: number; name: string; code: string } | null
  department: string | null
  department_name: string | null
  departments?: Array<{ id: number; name: string }>
  department_ids?: number[]
  position?: string
  salary: number | null
  total_worked_hours: number
  join_date: string | null
  contract_duration?: string
  off_day_allowance_days: number | null
  is_commission_based: boolean
  service_commissions?: Array<{ service: string; percentage: number }>
  work_start_time: string | null
  work_end_time: string | null
  created_at: string | null
  updated_at: string | null
  avatar?: string | null
  document?: { name: string; mimeType: string; size: number; downloadUrl: string } | null
  attachments?: Attachment[]
}

export interface EmployeeAccount {
  id: number
  name: string
  email: string
  employee: { id: number; name: string } | null
}

export interface EmployeeParams {
  page?: number
  perPage?: number
  search?: string
  position?: string
  sort?: 'created_at' | 'name' | 'id'
  dir?: 'asc' | 'desc'

  /** Defaults to `active` on the backend; `inactive`/`all` are management opt-ins. */
  status?: 'active' | 'inactive' | 'all'
}

export interface CreateEmployeeData {
  user_id?: number | null
  name: string
  father_name: string
  contact: string
  emergency_contact_name?: string | null
  emergency_contact_phone?: string | null
  id_number: string
  bank_account_number?: string | null
  guarantor_name?: string | null
  guarantor_id_number?: string | null
  guarantor_phone?: string | null
  guarantor_address?: string | null
  guarantor_licence?: string | null
  create_user?: boolean
  email?: string | null
  education_lvl: string
  position_id: number
  status?: 'active' | 'inactive'
  office_id?: number | null
  department_ids?: number[]
  salary?: number | null
  addr: string
  join_date: string
  contract_duration?: string
  off_day_allowance_days?: number | null
  is_commission_based?: boolean
  service_commissions?: Array<{ service: string; percentage: number }>
}

export interface UpdateEmployeeData {
  user_id?: number | null
  name?: string
  father_name?: string
  contact?: string
  emergency_contact_name?: string | null
  emergency_contact_phone?: string | null
  id_number?: string
  bank_account_number?: string | null
  guarantor_name?: string | null
  guarantor_id_number?: string | null
  guarantor_phone?: string | null
  guarantor_address?: string | null
  guarantor_licence?: string | null
  create_user?: boolean
  email?: string | null
  education_lvl?: string
  position_id?: number
  status?: 'active' | 'inactive'
  office_id?: number | null
  department_ids?: number[]
  salary?: number | null
  addr?: string
  join_date?: string
  contract_duration?: string
  off_day_allowance_days?: number | null
  is_commission_based?: boolean
  service_commissions?: Array<{ service: string; percentage: number }>
}

// Employee API endpoints
const EmployeeApi = {
  uploadDocument: async (id: number, document: File): Promise<Employee> => {
    const body = new FormData()
    body.append('document', document)
    return (await api.post<{ data: Employee }>(`/employees/${id}/document`, body, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })).data.data
  },
  removeDocument: async (id: number): Promise<Employee> => (await api.delete<{ data: Employee }>(`/employees/${id}/document`)).data.data,
  getAccountOptions: async (): Promise<EmployeeAccount[]> => {
    const response = await api.get<{ data: EmployeeAccount[] }>('/employees/account-options')

    return Array.isArray(response.data.data) ? response.data.data : []
  },

  getAssignmentOptions: async (specialty: 'counselor' | 'admission'): Promise<Employee[]> => {
    return (await api.get<{ data: Employee[] }>('/employees/assignment-options', { params: { specialty } })).data.data
  },

  // Normalized list for selectors; keep legacy getAll response until its consumers are migrated.
  getOptions: async (): Promise<Employee[]> => {
    // The staff list defaults to newest-first; pickers stay alphabetical.
    const response = await api.get<{ data: Employee[] }>('/employees', { params: { sort: 'name', dir: 'asc' } })

    return Array.isArray(response.data.data) ? response.data.data : []
  },

  // Get all employees; also used by the print report, which covers the whole
  // filtered set rather than the visible page.
  getAll: async (params?: EmployeeParams) => {
    try {
      return await api.get<{ data: Employee[] }>('/employees', { params: cleanListParams({ ...params }) })
    }
    catch (error) {
      console.error('API getAll error:', error)
      throw error
    }
  },

  // One page of the staff directory
  listPage: async (params?: EmployeeParams & ListPageParams): Promise<ListPage<Employee>> => {
    const response = await api.get('/employees', {
      params: cleanListParams({ ...params, page: params?.page, per_page: params?.perPage }),
    })

    return readPage<Employee>(response.data)
  },

  // Get single employee
  getById: async (id: number) => {
    try {
      return await api.get<{ data: Employee }>(`/employees/${id}`)
    }
    catch (error) {
      console.error('API getById error:', error)
      throw error
    }
  },

  // Create employee
  create: async (data: CreateEmployeeData) => {
    try {
      return await api.post<{ data: Employee }>('/employees', data)
    }
    catch (error) {
      console.error('API create error:', error)
      throw error
    }
  },

  // Update employee
  update: async (id: number, data: UpdateEmployeeData) => {
    try {
      return await api.put<{ data: Employee }>(`/employees/${id}`, data)
    }
    catch (error) {
      console.error('API update error:', error)
      throw error
    }
  },

  // Delete employee
  delete: async (id: number) => {
    try {
      return await api.delete<{ success: boolean; message: string }>(`/employees/${id}`)
    }
    catch (error) {
      console.error('API delete error:', error)
      throw error
    }
  },
}

export default EmployeeApi
