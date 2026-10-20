import api from './axios'
import { backgroundReadConfig } from './backgroundRead'

export interface AppVersion {
  version: string
  built_at: string | null
}

/**
 * The deployed frontend build version. Public route, never cached by the
 * service worker, so an open client can notice a new deploy.
 */
export const getAppVersion = async (): Promise<AppVersion> => {
  const response = await api.get('/version', backgroundReadConfig({ background: true }))
  const payload = response.data.data

  if (typeof payload?.version !== 'string' || !payload.version.trim())
    throw new Error('Invalid deployed app version response')

  return { version: payload.version, built_at: payload.built_at ?? null }
}
