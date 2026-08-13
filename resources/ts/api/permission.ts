import api from './axios'

export interface Permission {
  id: number
  name: string
  category: string
}

export interface RolePermissions {
  name: string
  permissions: string[]
}

const PermissionApi = {
  getAll: async (): Promise<Permission[]> => (await api.get('/permissions')).data.data,
  getRoles: async (): Promise<RolePermissions[]> => (await api.get('/roles')).data.data,
  updateRolePermissions: async (role: string, permissions: string[]): Promise<RolePermissions> =>
    (await api.put(`/roles/${role}/permissions`, { permissions })).data.data,
  create: async (data: Pick<Permission, 'name' | 'category'>): Promise<Permission> =>
    (await api.post('/permissions', data)).data.data,
  update: async (id: number, data: Partial<Pick<Permission, 'name' | 'category'>>): Promise<Permission> =>
    (await api.put(`/permissions/${id}`, data)).data.data,
  delete: async (id: number): Promise<void> => { await api.delete(`/permissions/${id}`) },
}

export default PermissionApi
