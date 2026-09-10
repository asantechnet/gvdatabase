export interface AuthTokens {
  access_token: string
  refresh_token?: string
}

const ACCESS_TOKEN_KEY = 'access_token'
const REFRESH_TOKEN_KEY = 'refresh_token'
const PERMISSIONS_KEY = 'permission_names'

const browserStorage = () => typeof window === 'undefined' ? null : window

export const getAccessToken = () => {
  const browser = browserStorage()

  return browser?.localStorage.getItem(ACCESS_TOKEN_KEY)
    || browser?.sessionStorage.getItem(ACCESS_TOKEN_KEY)
    || null
}

export const getRefreshToken = () => {
  const browser = browserStorage()

  return browser?.localStorage.getItem(REFRESH_TOKEN_KEY)
    || browser?.sessionStorage.getItem(REFRESH_TOKEN_KEY)
    || null
}

export const isRememberedSession = () => {
  return Boolean(browserStorage()?.localStorage.getItem(REFRESH_TOKEN_KEY))
}

export const clearAuthTokens = () => {
  const browser = browserStorage()
  if (!browser)
    return

  for (const storage of [browser.localStorage, browser.sessionStorage]) {
    storage.removeItem(ACCESS_TOKEN_KEY)
    storage.removeItem(REFRESH_TOKEN_KEY)
    storage.removeItem(PERMISSIONS_KEY)
  }

  document.cookie = 'accessToken=; Path=/; Max-Age=0; SameSite=Lax'
  document.cookie = 'userData=; Path=/; Max-Age=0; SameSite=Lax'
}

export const storeAuthTokens = (tokens: AuthTokens, remember: boolean) => {
  const browser = browserStorage()
  if (!browser)
    return

  clearAuthTokens()

  const storage = remember ? browser.localStorage : browser.sessionStorage

  storage.setItem(ACCESS_TOKEN_KEY, tokens.access_token)
  if (tokens.refresh_token)
    storage.setItem(REFRESH_TOKEN_KEY, tokens.refresh_token)
}

export const getStoredPermissions = (): string[] => {
  const browser = browserStorage()
  if (!browser)
    return []

  const raw = browser.localStorage.getItem(PERMISSIONS_KEY)
    || browser.sessionStorage.getItem(PERMISSIONS_KEY)

  if (!raw)
    return []

  try {
    const permissions = JSON.parse(raw)

    return Array.isArray(permissions)
      ? permissions.filter((permission): permission is string => typeof permission === 'string')
      : []
  }
  catch {
    return []
  }
}

export const storePermissions = (permissions: string[]) => {
  const browser = browserStorage()
  if (!browser)
    return

  const storage = isRememberedSession() ? browser.localStorage : browser.sessionStorage

  browser.localStorage.removeItem(PERMISSIONS_KEY)
  browser.sessionStorage.removeItem(PERMISSIONS_KEY)
  storage.setItem(PERMISSIONS_KEY, JSON.stringify(permissions))
}

export const clearStoredPermissions = () => {
  const browser = browserStorage()
  if (!browser)
    return

  browser.localStorage.removeItem(PERMISSIONS_KEY)
  browser.sessionStorage.removeItem(PERMISSIONS_KEY)
}
