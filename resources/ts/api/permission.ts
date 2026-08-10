import api from './axios'

// Permission API endpoints
const PermissionApi = {
  // Get all permissions
  getAll: () => api.get('/permissions'),

  // Get single permission
  getById: (id: number) => api.get(`/permissions/${id}`),

  // Create permission
  create: (data: {
    name: string;
    category: string;
  }) => api.post('/permissions', data),

  // Update permission
  update: (id: number, data: {
    name?: string;
    category?: string;
  }) => api.put(`/permissions/${id}`, data),

  // Delete permission
  delete: (id: number) => api.delete(`/permissions/${id}`),
}

export default PermissionApi
