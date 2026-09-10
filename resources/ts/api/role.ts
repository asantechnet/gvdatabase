import api from './axios'

export interface Role {
  id: number
  name: string
  permissions: string[]
  users_count: number
  created_at?: string
  updated_at?: string
}

export interface RoleCreateData {
  name: string
}

export interface RoleUpdateData {
  name?: string
}

export const getRoles = async (): Promise<Role[]> => {
  try {
    const response = await api.get('/roles')

    return response.data.data
  }
  catch (error) {
    console.error('Error fetching roles:', error)
    throw error
  }
}

export const getRole = async (id: number): Promise<Role> => {
  try {
    const response = await api.get(`/roles/${id}`)

    return response.data.data
  }
  catch (error) {
    console.error(`Error fetching role ${id}:`, error)
    throw error
  }
}

export const createRole = async (data: RoleCreateData): Promise<Role> => {
  try {
    const response = await api.post('/roles', data)

    return response.data.data
  }
  catch (error) {
    console.error('Error creating role:', error)
    throw error
  }
}

export const updateRole = async (
  id: number,
  data: RoleUpdateData,
): Promise<Role> => {
  try {
    const response = await api.put(`/roles/${id}`, data)

    return response.data.data
  }
  catch (error) {
    console.error(`Error updating role ${id}:`, error)
    throw error
  }
}

export const deleteRole = async (id: number): Promise<void> => {
  try {
    await api.delete(`/roles/${id}`)
  }
  catch (error) {
    console.error(`Error deleting role ${id}:`, error)
    throw error
  }
}

export const updateRolePermissions = async (
  roleId: number,
  permissions: string[],
): Promise<Role> => {
  try {
    const response = await api.put(`/roles/${roleId}/permissions`, {
      permissions,
    })

    return response.data.data
  }
  catch (error) {
    console.error(`Error updating permissions for role ${roleId}:`, error)
    throw error
  }
}

const RoleApi = {
  getRoles,
  getRole,
  createRole,
  updateRole,
  deleteRole,
  updateRolePermissions,
}

export default RoleApi
