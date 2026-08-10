import api from './axios'

export type ApiKeyPermission = 'full-control' | 'modify' | 'read-execute' | 'list-folders' | 'read-only' | 'read-write'

export interface ApiKey {
  id: number
  name: string
  permission: ApiKeyPermission
  last_used_at: string | null
  created_at: string
}

export interface Account {
  id: number
  name: string
  email: string
  phone: string | null
  address: string | null
  avatar: string | null
  acc_status: string
  organization?: string | null
  state?: string | null
  zip?: string | null
  country?: string | null
  language?: string | null
  timezone?: string | null
  currency?: string | null
  notifications?: NotificationPreferences
  notification_frequency?: 'online' | 'anytime'
  connections: Record<string, boolean>
  two_factor_enabled: boolean
  api_keys: ApiKey[]
}

export interface NotificationPreferences {
  [event: string]: { email: boolean; browser: boolean; app: boolean }
}

export interface AccountUpdatePayload {
  name?: string
  email?: string
  phone?: string | null
  address?: string | null
  organization?: string | null
  state?: string | null
  zip?: string | null
  country?: string | null
  language?: string | null
  timezone?: string | null
  currency?: string | null
}

const AuthApi = {
  register: (data: { name: string; email: string; password: string; password_confirmation: string; phone: string; roles: string[]; address: string }) => api.post('/auth/register', data),
  login: (credentials: { email: string; password: string; two_factor_code?: string }) => api.post('/auth/login', credentials),
  logout: () => api.post('/auth/logout'),
  refreshToken: () => api.post('/auth/refresh-token'),
  me: () => api.get('/auth/me'),
  updateAccount: (data: AccountUpdatePayload) => api.put('/auth/account', data),
  uploadAvatar: (avatar: File) => {
    const formData = new FormData()
    formData.append('avatar', avatar)
    return api.post('/auth/account/avatar', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
  },
  removeAvatar: () => api.delete('/auth/account/avatar'),
  updatePassword: (data: { current_password: string; password: string; password_confirmation: string }) => api.put('/auth/account/password', data),
  updateNotifications: (data: { notifications: NotificationPreferences; notification_frequency: 'online' | 'anytime' }) => api.put('/auth/account/notifications', data),
  updateConnections: (connections: Record<string, boolean>) => api.put('/auth/account/connections', { connections }),
  beginTwoFactor: () => api.post('/auth/account/two-factor'),
  confirmTwoFactor: (code: string) => api.post('/auth/account/two-factor/confirm', { code }),
  disableTwoFactor: (password: string) => api.delete('/auth/account/two-factor', { data: { password } }),
  createApiKey: (data: { name: string; permission: ApiKeyPermission }) => api.post('/auth/account/api-keys', data),
  revokeApiKey: (id: number) => api.delete(`/auth/account/api-keys/${id}`),
  logoutOtherDevices: (password: string) => api.post('/auth/account/logout-other-devices', { password }),
  deactivate: (password: string) => api.post('/auth/account/deactivate', { password, confirm: true }),
}

export default AuthApi
