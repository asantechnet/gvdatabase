import api from './axios'

export type UserRole = 'super_admin' | 'admin' | 'manager' | 'staff' | 'viewer' | 'applicant'
export type UserStatus = 'active' | 'inactive' | 'deactivated'

export interface ManagedUser {
  id: number
  name: string
  email: string
  phone: string | null
  address: string | null
  roles: UserRole[]
  acc_status: UserStatus
  email_verified: boolean
  password_setup_pending: boolean
  created_at: string | null
  updated_at: string | null
}

export interface UserPayload {
  name: string
  email: string
  phone: string | null
  address: string | null
  roles: UserRole[]
  acc_status: UserStatus
}

export class UserApiError extends Error {
  public readonly validationErrors: Record<string, string[]> | undefined
  constructor(error: any) { super(error?.message || 'User request failed.'); this.name = 'UserApiError'; this.validationErrors = error?.errors }
}

const request = async <T>(operation: () => Promise<any>): Promise<T> => {
  try { return (await operation()).data.data as T }
  catch (error) { throw new UserApiError(error) }
}

export const getUsers = (): Promise<ManagedUser[]> => request(() => api.get('/users'))
export const getUser = (id: number): Promise<ManagedUser> => request(() => api.get(`/users/${id}`))
export const createUser = (data: UserPayload): Promise<ManagedUser> => request(() => api.post('/users', data))
export const updateUser = (id: number, data: Partial<UserPayload>): Promise<ManagedUser> => request(() => api.put(`/users/${id}`, data))
export const updateUserStatus = (id: number, acc_status: Exclude<UserStatus, 'deactivated'>): Promise<ManagedUser> => request(() => api.put(`/users/${id}/status`, { acc_status }))
export const deleteUser = (id: number): Promise<void> => request(() => api.delete(`/users/${id}`))

export default { getUsers, getUser, createUser, updateUser, updateUserStatus, deleteUser }
