import api from './axios'

// Position API endpoints
const PositionApi = {
  // Get all positions
  getAll: () => api.get('/positions'),

  // Get single position
  getById: (id: number) => api.get(`/positions/${id}`),

  // Create position
  create: (data: {
    title: string;
    salary: number;
    status: string;
    is_sys_user: boolean;
    description?: string;
    permission_ids?: number[];
  }) => api.post('/positions', data),

  // Update position
  update: (id: number, data: {
    title?: string;
    salary?: number;
    status?: string;
    is_sys_user?: boolean;
    description?: string;
    permission_ids?: number[];
  }) => api.put(`/positions/${id}`, data),

  // Delete position
  delete: (id: number) => api.delete(`/positions/${id}`),

  // Attach permission to position
  attachPermission: (positionId: number, permissionId: number) =>
    api.post(`/positions/${positionId}/permissions/attach`, { permission_id: permissionId }),

  // Detach permission from position
  detachPermission: (positionId: number, permissionId: number) =>
    api.post(`/positions/${positionId}/permissions/detach`, { permission_id: permissionId }),
}

export default PositionApi
