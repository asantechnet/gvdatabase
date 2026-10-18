import api from './axios'

export interface AppVersion {
  version: string
  built_at: string | null
}

/**
 * The deployed frontend build version. Public route, never cached by the
 * service worker, so an open client can notice a new deploy.
 */
export const getAppVersion = async (): Promise<AppVersion> => {
  const response = await api.get('/version')

  return response.data.data
}
