import api from './axios'

export interface Account {
  id: number
  name: string
  email: string
  phone: string | null
  address: string | null
  avatar: string | null
  acc_status: string
  roles: string[]
  permissions: string[]
  employee: { id: number; position_id: number; is_marketer: boolean } | null
  is_marketer: boolean
  organization?: string | null
  state?: string | null
  zip?: string | null
  country?: string | null
  language?: string | null
  timezone?: string | null
  currency?: string | null
  notifications?: NotificationPreferences
  two_factor_enabled: boolean
  email_verified: boolean
  email_verified_at: string | null
}

export interface NotificationPreferences { [event: string]: { in_app: boolean } }

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
  register: (data: { name: string; email: string; password: string; password_confirmation: string; phone: string; address: string }) => api.post('/auth/register', data),
  login: (credentials: { email: string; password: string; remember?: boolean; two_factor_code?: string }) => api.post('/auth/login', credentials),
  logout: (refreshToken?: string | null) => api.post('/auth/logout', { refresh_token: refreshToken }),
  refreshToken: (refreshToken: string, remember: boolean) => api.post('/auth/refresh-token', { refresh_token: refreshToken, remember }),
  forgotPassword: (email: string) => api.post('/auth/forgot-password', { email }),
  resetPassword: (data: { email: string; token: string; password: string; password_confirmation: string }) => api.post('/auth/reset-password', data),
  completeInvitation: (data: { email: string; token: string; password: string; password_confirmation: string }) => api.post('/auth/complete-invitation', data),
  resendEmailVerification: (email: string) => api.post('/auth/email/verification-notification', { email }),
  me: () => api.get('/auth/me'),
  updateAccount: (data: AccountUpdatePayload) => api.put('/auth/account', data),
  uploadAvatar: (avatar: File) => {
    const formData = new FormData()

    formData.append('avatar', avatar)

    return api.post('/auth/account/avatar', formData, { headers: { 'Content-Type': 'multipart/form-data' } })
  },
  removeAvatar: () => api.delete('/auth/account/avatar'),
  updatePassword: (data: { current_password: string; password: string; password_confirmation: string }) => api.put('/auth/account/password', data),
  updateNotifications: (data: { notifications: NotificationPreferences }) => api.put('/auth/account/notifications', data),
  beginTwoFactor: () => api.post('/auth/account/two-factor'),
  confirmTwoFactor: (code: string) => api.post('/auth/account/two-factor/confirm', { code }),
  disableTwoFactor: (password: string) => api.delete('/auth/account/two-factor', { data: { password } }),
  logoutOtherDevices: (password: string) => api.post('/auth/account/logout-other-devices', { password }),
}

export default AuthApi
