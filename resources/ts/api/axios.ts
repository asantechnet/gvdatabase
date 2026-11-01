import axios, { type AxiosError, type AxiosInstance, type InternalAxiosRequestConfig } from 'axios'
import { type AuthTokens, clearAuthTokens, getAccessToken, getRefreshToken, isRememberedSession, storeAuthTokens } from '@/utils/authStorage'
import { type ApiError, apiErrorMessage, apiFieldErrors } from '@/utils/apiErrors'
import { isApiRequestAllowed, permissionsForApiRequest } from '@/utils/apiAuthorization'
import { validateApiRequest } from '@/utils/apiValidation'
import { clearFormErrors, replaceFormErrors } from '@/utils/formErrors'
import { currentLocale } from '@/utils/localization'
import { isBackgroundActive } from '@/utils/backgroundActivity'

export interface ApiMutationOptions { quiet?: boolean }
export const mutationHeaders = (options?: ApiMutationOptions) => options?.quiet ? { 'X-Suppress-Error-Notification': 'true' } : {}

// Create axios instance
const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
  },
})

// Refresh tokens are single-use. A shared renewal promise prevents several
// expired requests from racing and accidentally rotating the same token twice.
let refreshRequest: Promise<AuthTokens> | null = null

const renewSession = (refreshToken: string): Promise<AuthTokens> => {
  if (!refreshRequest) {
    refreshRequest = axios.post(`${import.meta.env.VITE_API_BASE_URL || '/api'}/auth/refresh-token`, {
      refresh_token: refreshToken,
      remember: isRememberedSession(),
    }, {
      headers: { 'Accept-Language': currentLocale() },
      timeout: 10000,
    }).then(response => {
      const tokens = response.data.data as AuthTokens

      storeAuthTokens(tokens, isRememberedSession())

      return tokens
    }).finally(() => {
      refreshRequest = null
    })
  }

  return refreshRequest
}

const errorNotificationsSuppressed = (config?: InternalAxiosRequestConfig) =>
  config?.headers?.['X-Suppress-Error-Notification'] === 'true'
  || config?.headers?.['X-Background-Request'] === 'true'

/**
 * The single dispatch point for `api-error`. The error is marked handled before
 * dispatching so a rejection that passes through more than one interceptor — a
 * request-interceptor validation failure rejects before any response exists —
 * can never notify the user twice. Quiet and background requests stay silent.
 */
const dispatchApiError = (error: ApiError, config?: InternalAxiosRequestConfig) => {
  error.handled = true

  if (typeof window === 'undefined' || errorNotificationsSuppressed(config))
    return

  window.dispatchEvent(new CustomEvent<ApiError>('api-error', { detail: error }))
}

// Request interceptor for adding the authorization token
api.interceptors.request.use(
  (config: InternalAxiosRequestConfig) => {
    if (config.headers['X-Background-Request'] === 'true' && !isBackgroundActive())
      return Promise.reject(new axios.CanceledError('Background refresh paused', config))

    if (['post', 'put', 'patch', 'delete'].includes((config.method ?? '').toLowerCase()))
      clearFormErrors()
    config.headers['Accept-Language'] = currentLocale()

    const errors = validateApiRequest(config)
    if (Object.keys(errors).length) {
      if (!errorNotificationsSuppressed(config))
        replaceFormErrors(errors)

      const apiError: ApiError = {
        code: 'validation_failed',
        errors,
        success: false,
      }

      dispatchApiError(apiError, config)

      return Promise.reject(apiError)
    }

    const requiredPermissions = permissionsForApiRequest(config.method, config.url, config.data)
    if (requiredPermissions && !isApiRequestAllowed(config.method, config.url, config.data)) {
      const apiError: ApiError = {
        code: 'auth.forbidden',
        success: false,
      }

      dispatchApiError(apiError, config)

      return Promise.reject(apiError)
    }

    const token = getAccessToken()
    if (token)
      config.headers.Authorization = `Bearer ${token}`

    return config
  },
  (error: AxiosError) => {
    return Promise.reject(error)
  },
)

// Response interceptor for handling errors and standardizing response format
api.interceptors.response.use(
  response => {
    // Binary exports must remain raw Blobs; wrapping them as JSON corrupts
    // downloads and prevents object URLs from being created by the browser.
    if (response.config.responseType === 'blob')
      return response

    // Standardize all successful responses to have a data property
    if (response.data && !response.data.data) {
      return {
        ...response,
        data: {
          data: response.data,
          success: true,
        },
      }
    }

    return {
      ...response,
      data: {
        ...response.data,
        success: true,
      },
    }
  },
  async (error: AxiosError) => {
    if (axios.isCancel(error) || (error as ApiError).handled)
      return Promise.reject(error)

    const originalRequest = error.config as (InternalAxiosRequestConfig & { _retry?: boolean }) | undefined
    const backgroundRequest = originalRequest?.headers?.['X-Background-Request'] === 'true'

    // Do not start token renewal after the user leaves the app. A replay after
    // an in-flight renewal also passes through the dispatch-time gate above.
    if (backgroundRequest && error.response?.status === 401 && !isBackgroundActive())
      return Promise.reject(new axios.CanceledError('Background refresh paused', originalRequest))

    // Handle 401 Unauthorized (token expired)
    const isPublicAuthRequest = originalRequest?.url?.includes('/auth/login')
      || originalRequest?.url?.includes('/auth/refresh-token')

    if (error.response?.status === 401 && originalRequest && !originalRequest._retry && !isPublicAuthRequest) {
      originalRequest._retry = true

      try {
        const refreshToken = getRefreshToken()
        if (refreshToken) {
          const tokens = await renewSession(refreshToken)

          // Retry the original request with the new token
          originalRequest.headers.Authorization = `Bearer ${tokens.access_token}`

          return api(originalRequest)
        }
      }
      catch (refreshError) {
        const response = (refreshError as AxiosError).response
        if (backgroundRequest && (!response || response.status >= 500 || response.status === 429))
          return Promise.reject(refreshError)

        // If refresh fails, redirect to login
        clearAuthTokens()
        window.location.href = '/login'

        return Promise.reject(refreshError)
      }
    }

    // Standardize error responses
    if (error.response) {
      const apiError = {
        ...(typeof error.response.data === 'object' && error.response.data !== null ? error.response.data : {}),
        status: error.response.status,
        success: false,
      } as ApiError

      apiError.message = apiErrorMessage(apiError)

      if (!errorNotificationsSuppressed(originalRequest) && apiError.status === 422 && apiError.errors)
        replaceFormErrors(apiFieldErrors(apiError))

      dispatchApiError(apiError, originalRequest)

      return Promise.reject(apiError)
    }

    // Offline writes are never queued (read-only offline is deliberate), so
    // say a connection is needed instead of surfacing a generic network error.
    if (typeof navigator !== 'undefined' && navigator.onLine === false) {
      const offlineError = {
        code: 'pwa.offline_action_blocked',
        success: false,
      } as ApiError
      dispatchApiError(offlineError, originalRequest)

      return Promise.reject(offlineError)
    }

    const apiError = {
      code: 'server.error',
      message: error.message || 'Network Error',
      success: false,
    } as ApiError

    dispatchApiError(apiError, originalRequest)

    return Promise.reject(apiError)
  },
)

export default api
