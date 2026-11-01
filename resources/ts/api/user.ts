import api from './axios'

export type UserRole = 'super_admin' | 'admin' | 'manager' | 'team_lead' | 'senior_counselor' | 'finance_officer' | 'counselor' | 'case_officer' | 'staff' | 'document_officer' | 'viewer' | 'applicant'
export type UserStatus = 'active' | 'inactive' | 'deactivated'

export interface ManagedUser {
  id: number
  is_primary: boolean
  is_self: boolean
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

/**
 * Creation-only extension of {@link UserPayload}.
 *
 * Supplying a password creates a ready-to-use account and the backend sends no
 * email at all; leaving it out keeps the invitation flow where the user
 * verifies their address and picks their own password. The confirmation field
 * mirrors the backend `confirmed` rule.
 */
export interface UserCreatePayload extends UserPayload {
  password?: string | null
  password_confirmation?: string | null
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
export const createUser = (data: UserCreatePayload): Promise<ManagedUser> => request(() => api.post('/users', data))
export const updateUser = (id: number, data: Partial<UserPayload>): Promise<ManagedUser> => request(() => api.put(`/users/${id}`, data))
export const updateUserStatus = (id: number, acc_status: Exclude<UserStatus, 'deactivated'>): Promise<ManagedUser> => request(() => api.put(`/users/${id}/status`, { acc_status }))
export const deleteUser = (id: number): Promise<void> => request(() => api.delete(`/users/${id}`))

export default { getUsers, getUser, createUser, updateUser, updateUserStatus, deleteUser }
