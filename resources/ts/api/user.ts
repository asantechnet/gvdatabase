import api from './axios'

// User API endpoints
const UserApi = {
  // Get all users
  getAll: () => api.get('/users'),

  // Get single user
  getById: (id: number) => api.get(`/users/${id}`),

  // Create user
  create: (data: {
    name: string;
    email: string;
    password: string;
    phone: string;
    address: string;
    acc_status: string;
    position_ids?: number[];
  }) => api.post('/users', data),

  // Update user
  update: (id: number, data: {
    name?: string;
    email?: string;
    password?: string;
    phone?: string;
    address?: string;
    acc_status?: string;
    position_ids?: number[];
  }) => api.put(`/users/${id}`, data),

  // Update user status
  updateStatus: (id: number, status: string) =>
    api.put(`/users/${id}/status`, { acc_status: status }),

  // Delete user
  delete: (id: number) => api.delete(`/users/${id}`),

  // Attach position to user
  attachPosition: (userId: number, data: {
    position_id: number;
    assigned_at?: string;
    ended_at?: string;
  }) => api.post(`/users/${userId}/positions/attach`, data),

  // Detach position from user
  detachPosition: (userId: number, positionId: number) =>
    api.post(`/users/${userId}/positions/detach`, { position_id: positionId }),
}

export default UserApi
